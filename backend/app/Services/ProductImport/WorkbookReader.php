<?php

namespace App\Services\ProductImport;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Reads an uploaded .xlsx / .xls / .csv into plain rows keyed by column key. Headers are
 * matched loosely ("Selling price (₹) *", "selling price", "price" all work), columns can be
 * in any order, unknown columns are ignored, and empty or sample (EXAMPLE-…) rows are skipped.
 */
class WorkbookReader
{
    /** @var list<string> */
    public array $notices = [];

    /**
     * @return array<string, list<array{row: int, values: array<string, mixed>}>>
     */
    public function read(string $path, string $originalName): array
    {
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        try {
            // Trust the content, not the name: an .xlsx/.xls upload must really be that format.
            $reader = match ($ext) {
                'csv' => new Csv,
                'xlsx' => IOFactory::createReader('Xlsx'),
                'xls' => IOFactory::createReader('Xls'),
                default => IOFactory::createReaderForFile($path),
            };
            if (! $reader->canRead($path)) {
                $alt = $ext === 'xlsx' ? IOFactory::createReader('Xls') : ($ext === 'xls' ? IOFactory::createReader('Xlsx') : null);
                if (! $alt || ! $alt->canRead($path)) {
                    throw new \RuntimeException('unreadable');
                }
                $reader = $alt;   // e.g. an old .xls saved with an .xlsx name
            }
            if ($reader instanceof Csv) {
                $reader->setInputEncoding(Csv::GUESS_ENCODING);
                $reader->setDelimiter($this->guessDelimiter($path));
            }
            $reader->setReadDataOnly(true);
            $reader->setReadEmptyCells(false);
            $book = $reader->load($path);
        } catch (\Throwable $e) {
            throw new ImportFileException('This file could not be opened. Please upload the .xlsx template (or a .xls/.csv file) without password protection.');
        }

        try {
            $out = array_fill_keys(ImportSchema::dataSheets(), []);
            $byName = [];
            foreach ($book->getWorksheetIterator() as $ws) {
                $byName[ImportSchema::normaliseHeader($ws->getTitle())] = $ws;
            }
            $productsSheet = $byName[ImportSchema::normaliseHeader(ImportSchema::PRODUCTS)] ?? null;
            if (! $productsSheet) {
                // A single-sheet file (CSV or a renamed sheet) is treated as the Products sheet.
                $first = $book->getSheet(0);
                if ($book->getSheetCount() === 1 || $this->looksLikeProducts($first)) {
                    $productsSheet = $first;
                } else {
                    throw new ImportFileException('No "Products" sheet was found. Please use the downloadable template.');
                }
            }

            foreach (ImportSchema::dataSheets() as $name) {
                $ws = $name === ImportSchema::PRODUCTS ? $productsSheet : ($byName[ImportSchema::normaliseHeader($name)] ?? null);
                if ($ws) {
                    $out[$name] = $this->rows($ws, $name);
                }
            }
        } finally {
            $book->disconnectWorksheets();
        }

        if (count($out[ImportSchema::PRODUCTS]) > ImportSchema::MAX_PRODUCT_ROWS) {
            throw new ImportFileException('The Products sheet has '.count($out[ImportSchema::PRODUCTS]).' rows. Please split the file — up to '.number_format(ImportSchema::MAX_PRODUCT_ROWS).' products per upload.');
        }
        $children = count($out[ImportSchema::COMPATIBILITY]) + count($out[ImportSchema::VARIANTS]) + count($out[ImportSchema::FAQS]);
        if ($children > ImportSchema::MAX_CHILD_ROWS) {
            throw new ImportFileException('The file has more than '.number_format(ImportSchema::MAX_CHILD_ROWS).' compatibility/variant/FAQ rows. Please split it into smaller files.');
        }
        if (! array_filter($out)) {
            throw new ImportFileException('The file has no product rows to import. Rows whose SKU starts with '.ImportSchema::EXAMPLE_PREFIX.' are samples and are ignored.');
        }

        return $out;
    }

    private function looksLikeProducts(Worksheet $ws): bool
    {
        $map = ImportSchema::headerMap(ImportSchema::PRODUCTS);
        $headers = $ws->rangeToArray('A1:'.$ws->getHighestDataColumn(1).'1', null, true, false)[0] ?? [];
        $keys = array_filter(array_map(fn ($h) => $map[ImportSchema::normaliseHeader((string) $h)] ?? null, $headers));

        return in_array('sku', $keys, true);
    }

    /** @return list<array{row: int, values: array<string, mixed>}> */
    private function rows(Worksheet $ws, string $sheet): array
    {
        $lastRow = $ws->getHighestDataRow();
        $lastCol = $ws->getHighestDataColumn();
        if ($lastRow < 1) {
            return [];
        }
        $data = $ws->rangeToArray('A1:'.$lastCol.$lastRow, null, true, false, false);
        $headerRow = array_shift($data) ?? [];

        $map = ImportSchema::headerMap($sheet);
        $columns = [];   // column index => key
        foreach ($headerRow as $i => $h) {
            $key = $map[ImportSchema::normaliseHeader((string) $h)] ?? null;
            if ($key && ! in_array($key, $columns, true)) {
                $columns[$i] = $key;
            } elseif (trim((string) $h) !== '' && ! $key) {
                $this->notices[] = 'Column "'.trim((string) $h).'" on the '.$sheet.' sheet is not recognised and was ignored.';
            }
        }

        $required = $sheet === ImportSchema::PRODUCTS ? ['sku'] : array_keys(array_filter(ImportSchema::columns($sheet), fn ($c) => $c[1]));
        $hasData = false;
        foreach ($data as $cells) {
            if (array_filter($cells, fn ($v) => $v !== null && trim((string) $v) !== '')) {
                $hasData = true;
                break;
            }
        }
        if (! $hasData) {
            return [];
        }
        $missing = array_diff($required, $columns);
        if ($missing) {
            $names = implode(', ', array_map(fn ($k) => '"'.ImportSchema::header($sheet, $k).'"', $missing));
            throw new ImportFileException("The {$sheet} sheet is missing the {$names} column. Please keep the template's header row.");
        }

        $rows = [];
        foreach ($data as $offset => $cells) {
            $values = [];
            foreach ($columns as $i => $key) {
                $v = $cells[$i] ?? null;
                if (is_string($v)) {
                    $v = trim(str_replace("\r\n", "\n", $v));
                    $v = $v === '' ? null : $v;
                } elseif (is_float($v) && floor($v) === $v && abs($v) < 1e15 && in_array(ImportSchema::columns($sheet)[$key][6], ['text', 'long'], true)) {
                    $v = (string) (int) $v;   // e.g. a numeric SKU typed into a General cell
                }
                $values[$key] = $v;
            }
            if (! array_filter($values, fn ($v) => $v !== null && $v !== '')) {
                continue;
            }
            $sku = strtoupper(trim((string) ($values['sku'] ?? '')));
            if (str_starts_with($sku, ImportSchema::EXAMPLE_PREFIX)) {
                continue;
            }
            $rows[] = ['row' => $offset + 2, 'values' => $values];
        }

        return $rows;
    }

    private function guessDelimiter(string $path): string
    {
        $fh = fopen($path, 'r');
        $line = $fh ? (string) fgets($fh) : '';
        $fh && fclose($fh);
        $counts = [',' => substr_count($line, ','), ';' => substr_count($line, ';'), "\t" => substr_count($line, "\t")];
        arsort($counts);

        return array_key_first($counts);
    }
}
