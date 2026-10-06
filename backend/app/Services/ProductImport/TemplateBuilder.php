<?php

namespace App\Services\ProductImport;

use App\Models\Product;
use App\Support\Media;
use Illuminate\Database\Eloquent\Builder;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\NamedRange;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Builds the import workbook: Instructions, Products, Compatibility, Variants, FAQs and a
 * protected Lists sheet that feeds every dropdown with live data (categories, brands,
 * vehicles, filters). Optionally pre-filled with existing products for bulk editing.
 */
class TemplateBuilder
{
    private const BRAND = 'D7263D';
    private const INK = '1F2937';
    private const SOFT = 'F3F4F6';
    private const VALIDATION_ROWS = 5000;

    /** list name => [named range, header on the Lists sheet] */
    private const LISTS = [
        'categories' => ['CategoryList', 'Categories'],
        'brands' => ['BrandList', 'Brands'],
        'vehicles' => ['VehicleList', 'Vehicles (for the Compatibility sheet)'],
        'attributes' => ['FilterList', 'Filters (for the Filters column)'],
        'vehicle_types' => ['VehicleTypeList', 'Vehicle types'],
        'yes_no' => ['YesNoList', 'Yes / No'],
        'gst' => ['GstList', 'GST %'],
    ];

    private ImportLookups $lookups;

    public function __construct(?ImportLookups $lookups = null)
    {
        $this->lookups = $lookups ?? ImportLookups::load();
    }

    /**
     * @param  Builder<Product>|null  $products  products to pre-fill, or null for a blank template with a sample row
     * @return string path of the temporary .xlsx file
     */
    public function build(?Builder $products = null, string $storeName = 'MotoGears'): string
    {
        $book = new Spreadsheet;
        $book->getProperties()->setCreator($storeName)->setTitle($storeName.' product import')->setSubject('Product import template');
        $book->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);

        $instructions = $book->getActiveSheet()->setTitle(ImportSchema::INSTRUCTIONS);
        $sheets = [];
        foreach (ImportSchema::dataSheets() as $name) {
            $sheets[$name] = $this->dataSheet($book->createSheet()->setTitle($name), $name);
        }
        $lists = $this->listsSheet($book, $book->createSheet()->setTitle(ImportSchema::LISTS));

        if ($products) {
            $this->fill($sheets, $products);
        } else {
            $this->sampleRows($sheets);
        }
        foreach (ImportSchema::dataSheets() as $name) {
            $this->validations($sheets[$name], $name);
        }
        $this->instructions($instructions, $storeName, $products !== null);

        foreach ($book->getWorksheetIterator() as $ws) {
            // Print/PDF: landscape, all columns on one page width.
            $ws->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
                ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4)->setFitToWidth(1)->setFitToHeight(0);
        }
        $book->setActiveSheetIndex($products ? 1 : 0);
        $path = tempnam(sys_get_temp_dir(), 'tpl').'.xlsx';
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();

        return $path;
    }

    // ── Data sheets ───────────────────────────────────────────────
    private function dataSheet(Worksheet $sheet, string $name): Worksheet
    {
        $cols = ImportSchema::columns($name);
        $i = 1;
        foreach ($cols as $key => [$header, $required, $help, , $width, , $format]) {
            $col = Coordinate::stringFromColumnIndex($i++);
            $cell = $sheet->getCell($col.'1');
            $cell->setValue(ImportSchema::headerLabel($name, $key));
            $cell->getStyle()->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $required ? self::BRAND : self::INK]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            ]);
            if ($help) {
                $comment = $sheet->getComment($col.'1');
                $comment->setAuthor('Import guide');
                $comment->getText()->createTextRun($header.($required ? ' (required)' : ' (optional)'))->getFont()->setBold(true);
                $comment->getText()->createText("\n".$help);
                $comment->setWidth('260pt')->setHeight('110pt');
            }
            $sheet->getColumnDimension($col)->setWidth($width);
            // Whole-column styles are stored once per column instead of once per cell.
            $range = $col.':'.$col;
            match ($format) {
                'text' => $sheet->getStyle($range)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT),
                'money' => $sheet->getStyle($range)->getNumberFormat()->setFormatCode('#,##0.00'),
                'long' => $sheet->getStyle($range)->getAlignment()->setWrapText(true),
                default => null,
            };
        }
        $last = Coordinate::stringFromColumnIndex(count($cols));
        $i = 1;
        foreach ($cols as $key => $c) {
            $sheet->getStyle(Coordinate::stringFromColumnIndex($i++).'1')->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $c[1] ? self::BRAND : self::INK]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                'numberFormat' => ['formatCode' => NumberFormat::FORMAT_GENERAL],
            ]);
        }
        $sheet->getRowDimension(1)->setRowHeight(32);
        $sheet->freezePane($name === ImportSchema::PRODUCTS ? 'C2' : 'B2');
        $sheet->setAutoFilter('A1:'.$last.'1');
        $sheet->getTabColor()->setRGB($name === ImportSchema::PRODUCTS ? self::BRAND : '6B7280');

        return $sheet;
    }

    private function validations(Worksheet $sheet, string $name): void
    {
        $i = 1;
        foreach (ImportSchema::columns($name) as $key => [$header, , , , , $list, $format]) {
            $col = Coordinate::stringFromColumnIndex($i++);
            $range = $col.'2:'.$col.(self::VALIDATION_ROWS + 1);
            $v = new DataValidation;
            $v->setAllowBlank(true)->setShowErrorMessage(true)->setShowInputMessage(false)->setShowDropDown(true);
            if ($list) {
                $v->setType(DataValidation::TYPE_LIST)->setFormula1('='.self::LISTS[$list][0])
                    ->setErrorStyle($list === 'vehicle_types' || $list === 'yes_no' || $list === 'gst' ? DataValidation::STYLE_STOP : DataValidation::STYLE_WARNING)
                    ->setErrorTitle('Pick from the list')
                    ->setError('Please choose a value from the dropdown. All valid values are on the "Lists" sheet.');
            } elseif (in_array($format, ['money', 'decimal'], true)) {
                $v->setType(DataValidation::TYPE_DECIMAL)->setOperator($key === 'price_adjustment' ? DataValidation::OPERATOR_BETWEEN : DataValidation::OPERATOR_GREATERTHANOREQUAL)
                    ->setFormula1($key === 'price_adjustment' ? '-9999999' : '0')->setFormula2('9999999')
                    ->setErrorStyle(DataValidation::STYLE_STOP)->setErrorTitle('Numbers only')->setError($header.' must be a number (no ₹ sign or text).');
            } elseif ($format === 'int') {
                [$min, $max] = in_array($key, ['year_from', 'year_to'], true) ? ['1950', '2100'] : ['0', '1000000'];
                $v->setType(DataValidation::TYPE_WHOLE)->setOperator(DataValidation::OPERATOR_BETWEEN)->setFormula1($min)->setFormula2($max)
                    ->setErrorStyle(DataValidation::STYLE_STOP)->setErrorTitle('Whole number')->setError($header.' must be a whole number between '.$min.' and '.$max.'.');
            } else {
                continue;
            }
            $sheet->setDataValidation($range, $v);
        }
    }

    // ── Lists ─────────────────────────────────────────────────────
    private function listsSheet(Spreadsheet $book, Worksheet $sheet): Worksheet
    {
        $values = [
            'categories' => array_values($this->lookups->categoryPaths),
            'brands' => array_values($this->lookups->brandNames),
            'vehicles' => array_column($this->lookups->vehicles, 'label'),
            'attributes' => array_column($this->lookups->attributeValueLabels, 'label'),
            'vehicle_types' => ['Car', 'Motorcycle', 'Universal'],
            'yes_no' => ['Yes', 'No'],
            'gst' => [0, 5, 12, 18, 28],
        ];
        $i = 1;
        foreach (self::LISTS as $list => [$rangeName, $title]) {
            $col = Coordinate::stringFromColumnIndex($i++);
            $sheet->setCellValue($col.'1', $title);
            $rows = $values[$list] ?: ['(none yet)'];
            foreach ($rows as $r => $value) {
                $sheet->setCellValueExplicit($col.($r + 2), $value, is_int($value) ? \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC : \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }
            $book->addNamedRange(new NamedRange($rangeName, $sheet, '$'.$col.'$2:$'.$col.'$'.(count($rows) + 1)));
            $sheet->getColumnDimension($col)->setWidth(match ($list) { 'vehicles' => 60, 'categories' => 40, 'attributes' => 32, default => 18 });
        }
        $last = Coordinate::stringFromColumnIndex(count(self::LISTS));
        $sheet->getStyle('A1:'.$last.'1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::INK]],
        ]);
        $sheet->freezePane('A2');
        $sheet->getTabColor()->setRGB('9CA3AF');
        // Read-only so dropdown sources are not edited by accident (no password — Review → Unprotect).
        $sheet->getProtection()->setSheet(true)->setSort(false)->setAutoFilter(false);

        return $sheet;
    }

    // ── Rows ──────────────────────────────────────────────────────
    private function sampleRows(array $sheets): void
    {
        foreach (ImportSchema::dataSheets() as $name) {
            // Only the key columns get sample values, so typing over the sample row never leaves
            // stray example data behind. Every column's example is on the Instructions sheet.
            $keep = ['sku', 'name', 'category', 'brand', 'vehicle_type', 'mrp', 'price', 'tax_rate', 'stock', 'is_active',
                'vehicle', 'variant_sku', 'variant_name', 'options', 'price_adjustment', 'question', 'answer'];
            $row = [];
            foreach (ImportSchema::columns($name) as $key => $c) {
                $row[$key] = in_array($key, $keep, true) ? $c[3] : null;
            }
            if ($name === ImportSchema::PRODUCTS) {
                $row['category'] = reset($this->lookups->categoryPaths) ?: null;
                $row['brand'] = reset($this->lookups->brandNames) ?: null;
            }
            if ($name === ImportSchema::COMPATIBILITY) {
                $row['vehicle'] = $this->lookups->vehicles[1]['label'] ?? $this->lookups->vehicles[0]['label'] ?? null;
            }
            $this->writeRow($sheets[$name], $name, 2, $row);
            $sheets[$name]->getStyle('A2:'.Coordinate::stringFromColumnIndex(count($row)).'2')->getFont()->setItalic(true)->getColor()->setRGB('6B7280');
        }
    }

    private function fill(array $sheets, Builder $products): void
    {
        $next = array_fill_keys(ImportSchema::dataSheets(), 2);
        $products->with(['category', 'brand', 'inventory', 'images', 'attributeValues', 'compatibilities', 'variants', 'faqs'])
            ->orderBy('id')
            ->chunkById(200, function ($chunk) use ($sheets, &$next) {
                foreach ($chunk as $p) {
                    $this->writeRow($sheets[ImportSchema::PRODUCTS], ImportSchema::PRODUCTS, $next[ImportSchema::PRODUCTS]++, $this->productRow($p));
                    foreach ($p->compatibilities as $c) {
                        $this->writeRow($sheets[ImportSchema::COMPATIBILITY], ImportSchema::COMPATIBILITY, $next[ImportSchema::COMPATIBILITY]++, [
                            'sku' => $p->sku,
                            'vehicle' => $this->lookups->vehicleLabelFor($c->vehicle_manufacturer_id, $c->vehicle_model_id, $c->vehicle_variant_id),
                            'year_from' => $c->year_from, 'year_to' => $c->year_to, 'notes' => $c->notes,
                        ]);
                    }
                    foreach ($p->variants as $v) {
                        $this->writeRow($sheets[ImportSchema::VARIANTS], ImportSchema::VARIANTS, $next[ImportSchema::VARIANTS]++, [
                            'sku' => $p->sku, 'variant_sku' => $v->sku, 'variant_name' => $v->name,
                            'options' => self::pairs($v->options ?? []), 'price_adjustment' => (float) $v->price_adjustment,
                            'is_active' => $v->is_active ? 'Yes' : 'No',
                        ]);
                    }
                    foreach ($p->faqs as $f) {
                        $this->writeRow($sheets[ImportSchema::FAQS], ImportSchema::FAQS, $next[ImportSchema::FAQS]++, ['sku' => $p->sku, 'question' => $f->question, 'answer' => $f->answer]);
                    }
                }
            });
    }

    /** One Products row for an existing product, in exactly the format the reader accepts. */
    public function productRow(Product $p): array
    {
        $specs = collect($p->specifications ?? [])->map(fn ($s) => trim(($s['label'] ?? '').': '.($s['value'] ?? '')))->implode(' | ');

        return [
            'sku' => $p->sku,
            'name' => $p->name,
            'category' => $this->lookups->categoryPaths[$p->category_id] ?? $p->category?->name,
            'brand' => $this->lookups->brandNames[$p->brand_id] ?? $p->brand?->name,
            'vehicle_type' => ucfirst($p->vehicle_type),
            'is_universal' => $p->is_universal ? 'Yes' : 'No',
            'part_number' => $p->part_number,
            'mrp' => (float) $p->mrp,
            'price' => (float) $p->price,
            'cost_price' => (float) $p->cost_price ?: null,
            'tax_rate' => (int) $p->tax_rate,
            'stock' => $p->inventory?->quantity ?? 0,
            'low_stock_threshold' => $p->inventory?->low_stock_threshold,
            'allow_backorder' => $p->inventory?->allow_backorder ? 'Yes' : 'No',
            'short_description' => $p->short_description,
            'description' => $p->description,
            'attributes' => $p->attributeValues->map(fn ($v) => $v->attribute?->name.': '.$v->value)->implode(' | '),
            'specifications' => $specs,
            'whats_included' => implode(' | ', $p->whats_included ?? []),
            'tags' => implode(', ', $p->tags ?? []),
            'position' => $p->position,
            'material' => $p->material,
            'dimensions' => $p->dimensions,
            'weight_kg' => $p->weight_kg !== null ? (float) $p->weight_kg : null,
            'warranty' => $p->warranty,
            'installation_info' => $p->installation_info,
            'image_urls' => $p->images->map(fn ($i) => Media::url($i->path))->implode(' | '),
            'video_url' => $p->video_url,
            'slug' => $p->slug,
            'meta_title' => $p->meta_title,
            'meta_description' => $p->meta_description,
            'is_active' => $p->is_active ? 'Yes' : 'No',
            'is_featured' => $p->is_featured ? 'Yes' : 'No',
        ];
    }

    private function writeRow(Worksheet $sheet, string $name, int $r, array $values): void
    {
        $i = 1;
        foreach (ImportSchema::columns($name) as $key => $c) {
            $value = $values[$key] ?? null;
            $coord = Coordinate::stringFromColumnIndex($i++).$r;
            if ($value === null || $value === '') {
                continue;
            }
            if (in_array($c[6], ['text', 'long', 'bool'], true)) {
                $sheet->setCellValueExplicit($coord, (string) $value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            } else {
                $sheet->setCellValue($coord, $value);
            }
        }
    }

    public static function pairs(array $assoc): string
    {
        return collect($assoc)->map(fn ($v, $k) => $k.': '.$v)->implode(' | ');
    }

    // ── Instructions ──────────────────────────────────────────────
    private function instructions(Worksheet $sheet, string $store, bool $prefilled): void
    {
        $sheet->getColumnDimension('A')->setWidth(22);
        $sheet->getColumnDimension('B')->setWidth(24);
        $sheet->getColumnDimension('C')->setWidth(11);
        $sheet->getColumnDimension('D')->setWidth(90);
        $sheet->getColumnDimension('E')->setWidth(34);
        $sheet->setShowGridlines(false);

        $sheet->setCellValue('A1', $store.' — product import');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(18)->getColor()->setRGB(self::BRAND);
        $sheet->setCellValue('A2', $prefilled
            ? 'This file contains your current products. Edit what you need, then upload it on Admin → Products → Import. Rows you don\'t change are simply updated with the same values.'
            : 'Fill in the Products sheet (one row per product), optionally add Compatibility, Variants and FAQs, then upload this file on Admin → Products → Import.');
        $sheet->mergeCells('A2:E2');
        $sheet->getStyle('A2')->getAlignment()->setWrapText(true);
        $sheet->getRowDimension(2)->setRowHeight(32);

        $steps = [
            'How it works',
            '1.  Products sheet — one row per product. Red headers are required for new products; dark headers are optional. Hover over a header for help.',
            '2.  Use the dropdowns for Category, Brand, Vehicle type, Yes/No and GST. Everything you can pick is listed on the "Lists" sheet (it updates each time you download a template).',
            '3.  Compatibility sheet — one row per vehicle a product fits (pick the vehicle from the dropdown). Skip it for products marked "Fits all vehicles".',
            '4.  Variants and FAQs sheets are optional. Link every row to its product with the Product SKU.',
            '5.  Upload the file. It is checked first (nothing is saved) and you see exactly what will be added, updated or needs fixing. Then click Import — it runs in the background, so you can keep working.',
            '',
            'Good to know',
            '•  SKU is the key: a SKU that already exists updates that product, a new SKU creates a product.',
            '•  Updating: empty cells keep the current value. To erase an optional value, type '.ImportSchema::CLEAR.'.',
            '•  If a product SKU appears on the Compatibility, Variants or FAQs sheet, that product\'s list is replaced by the rows in the file.',
            '•  Rows whose SKU starts with '.ImportSchema::EXAMPLE_PREFIX.' are samples and are always ignored — overwrite or delete them.',
            '•  Up to '.number_format(ImportSchema::MAX_PRODUCT_ROWS).' products per file. Formats: .xlsx (recommended), .xls or .csv (products only).',
            '•  Prices exclude GST. Numbers only — no ₹ sign.',
            '',
            'Columns',
        ];
        $r = 4;
        foreach ($steps as $line) {
            $sheet->setCellValue('A'.$r, $line);
            $sheet->mergeCells('A'.$r.':E'.$r);
            if (in_array($line, ['How it works', 'Good to know', 'Columns'], true)) {
                $sheet->getStyle('A'.$r)->getFont()->setBold(true)->setSize(13)->getColor()->setRGB(self::INK);
            } else {
                $sheet->getStyle('A'.$r)->getAlignment()->setWrapText(true);
            }
            $r++;
        }

        $sheet->fromArray(['Sheet', 'Column', 'Required', 'What to enter', 'Example'], null, 'A'.$r);
        $sheet->getStyle('A'.$r.':E'.$r)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::INK]],
        ]);
        $first = ++$r;
        foreach (ImportSchema::dataSheets() as $name) {
            foreach (ImportSchema::columns($name) as $key => [$header, $required, $help, $example, , $list]) {
                $what = $help ?? '';
                if ($list && ! str_contains($what, 'dropdown')) {
                    $what = trim($what.' Pick from the dropdown.');
                }
                $sheet->setCellValueExplicit('A'.$r, $name, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('B'.$r, $header, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('C'.$r, $required ? 'Yes' : '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('D'.$r, $what, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('E'.$r, $example === null ? '' : (string) $example, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                if ($required) {
                    $sheet->getStyle('B'.$r.':C'.$r)->getFont()->setBold(true)->getColor()->setRGB(self::BRAND);
                }
                if ($r % 2 === 0) {
                    $sheet->getStyle('A'.$r.':E'.$r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::SOFT);
                }
                $r++;
            }
        }
        $sheet->getStyle('A'.$first.':E'.($r - 1))->applyFromArray([
            'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => 'D1D5DB']]],
        ]);
        $sheet->getTabColor()->setRGB('059669');
    }
}
