<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessProductImport;
use App\Models\Product;
use App\Models\ProductImport;
use App\Services\ProductImport\ImportSchema;
use App\Services\ProductImport\TemplateBuilder;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Bulk product import from Excel. Uploading stores the file and queues a check (dry run);
 * the admin reviews the result and starts the real import, which also runs in the queue.
 */
class ProductImportController extends Controller
{
    private const STATUS_LABELS = [
        ProductImport::PENDING => 'Waiting to be checked',
        ProductImport::VALIDATING => 'Checking file',
        ProductImport::VALIDATED => 'Ready to import',
        ProductImport::QUEUED => 'Waiting to import',
        ProductImport::IMPORTING => 'Importing',
        ProductImport::COMPLETED => 'Completed',
        ProductImport::COMPLETED_WITH_ERRORS => 'Completed with errors',
        ProductImport::FAILED => 'Failed',
        ProductImport::CANCELLED => 'Cancelled',
    ];

    public function index(): JsonResponse
    {
        $page = ProductImport::with('user:id,name')->latest('id')->paginate($this->perPage(10, 50));

        return response()->json([
            'success' => true,
            'message' => 'Imports retrieved',
            'data' => $page->getCollection()->map(fn (ProductImport $i) => $this->payload($i))->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
        ]);
    }

    public function show(Request $request, ProductImport $productImport): JsonResponse
    {
        $request->validate(['level' => ['nullable', Rule::in(['error', 'warning'])]]);
        $issues = collect($productImport->errors ?? []);
        if ($request->level) {
            $issues = $issues->where('level', $request->level)->values();
        }

        return $this->ok($this->payload($productImport->load('user:id,name')) + [
            'issues' => $issues->take(500)->values(),
            'issues_total' => $issues->count(),
            'issues_truncated' => $productImport->error_count + $productImport->warning_count > count($productImport->errors ?? []),
        ], 'Import retrieved');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:'.config('imports.max_file_kb', 10240), 'extensions:xlsx,xls,csv'],
            'mode' => ['nullable', Rule::in(ProductImport::MODES)],
            'auto_start' => ['sometimes', 'boolean'],
        ], [
            'file.extensions' => 'Please upload an Excel file (.xlsx or .xls) or a .csv file.',
            'file.max' => 'The file is too large (max '.round(config('imports.max_file_kb', 10240) / 1024).' MB). Split it into smaller files.',
        ]);

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        $path = $file->storeAs('imports', now()->format('Y/m').'/'.Str::uuid().'.'.$ext, 'local');

        $import = ProductImport::create([
            'user_id' => $request->user()->id,
            'original_name' => Str::limit(basename($file->getClientOriginalName()), 180, ''),
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'mode' => $data['mode'] ?? 'upsert',
            'auto_start' => (bool) ($data['auto_start'] ?? false),
            'status' => ProductImport::PENDING,
        ]);
        ProcessProductImport::dispatch($import->id, 'check');

        return response()->json([
            'success' => true,
            'message' => 'File uploaded — it is being checked in the background.',
            'data' => $this->payload($import->fresh()->load('user:id,name')),
        ], 202);
    }

    public function start(ProductImport $productImport): JsonResponse
    {
        if ($productImport->status !== ProductImport::VALIDATED) {
            return $this->fail('This import cannot be started — it is '.strtolower(self::STATUS_LABELS[$productImport->status] ?? $productImport->status).'.', 409);
        }
        $s = $productImport->summary ?? [];
        if (($s['will_create'] ?? 0) + ($s['will_update'] ?? 0) === 0) {
            return $this->fail('There is nothing to import — fix the errors in your file and upload it again.', 422);
        }
        $running = ProductImport::whereIn('status', [ProductImport::QUEUED, ProductImport::IMPORTING])->whereKeyNot($productImport->id)->exists();
        if ($running) {
            return $this->fail('Another import is running. Please wait for it to finish, then start this one.', 409);
        }
        if (! $productImport->file_path || ! Storage::disk('local')->exists($productImport->file_path)) {
            return $this->fail('The uploaded file is no longer available. Please upload it again.', 410);
        }

        $productImport->forceFill(['status' => ProductImport::QUEUED, 'cancel_requested' => false])->save();
        ProcessProductImport::dispatch($productImport->id, 'import');

        return $this->ok($this->payload($productImport->fresh()->load('user:id,name')), 'Import started — it runs in the background, you can keep working.');
    }

    public function cancel(ProductImport $productImport): JsonResponse
    {
        if ($productImport->status === ProductImport::VALIDATED) {
            $productImport->forceFill(['status' => ProductImport::CANCELLED, 'finished_at' => now(), 'message' => 'Discarded without importing.'])->save();
        } elseif ($productImport->isActive()) {
            $productImport->forceFill(['cancel_requested' => true])->save();
            if (in_array($productImport->status, [ProductImport::PENDING, ProductImport::QUEUED], true)) {
                $productImport->forceFill(['status' => ProductImport::CANCELLED, 'finished_at' => now(), 'message' => 'Cancelled before it started.'])->save();
            }
        } else {
            return $this->fail('This import has already finished.', 409);
        }

        return $this->ok($this->payload($productImport->fresh()->load('user:id,name')), $productImport->status === ProductImport::CANCELLED ? 'Import cancelled' : 'Stopping after the current product…');
    }

    public function destroy(ProductImport $productImport): JsonResponse
    {
        if ($productImport->isActive()) {
            return $this->fail('Cancel the import before deleting it.', 409);
        }
        if ($productImport->file_path) {
            Storage::disk('local')->delete($productImport->file_path);
        }
        $productImport->delete();

        return $this->deleted('Import removed from the history');
    }

    /** The original uploaded file. */
    public function file(ProductImport $productImport): BinaryFileResponse|JsonResponse
    {
        if (! $productImport->file_path || ! Storage::disk('local')->exists($productImport->file_path)) {
            return $this->fail('The uploaded file is no longer available (files are kept for '.config('imports.keep_files_days', 30).' days).', 404);
        }

        return response()->download(Storage::disk('local')->path($productImport->file_path), $productImport->original_name);
    }

    /** All problems found, as an Excel sheet the admin can filter and work through. */
    public function report(ProductImport $productImport): BinaryFileResponse
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('Problems');
        $sheet->fromArray(['Type', 'Sheet', 'Row', 'SKU', 'Column', 'Problem'], null, 'A1');
        $sheet->getStyle('A1:F1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F2937']],
        ]);
        $r = 2;
        foreach ($productImport->errors ?? [] as $e) {
            $sheet->setCellValueExplicit('A'.$r, $e['level'] === 'error' ? 'Error' : 'Warning', 's');
            $sheet->setCellValueExplicit('B'.$r, (string) $e['sheet'], 's');
            if ($e['row']) {
                $sheet->setCellValue('C'.$r, $e['row']);
            }
            $sheet->setCellValueExplicit('D'.$r, (string) ($e['sku'] ?? ''), 's');
            $sheet->setCellValueExplicit('E'.$r, (string) ($e['column'] ?? ''), 's');
            $sheet->setCellValueExplicit('F'.$r, (string) $e['message'], 's');
            $sheet->getStyle('A'.$r)->getFont()->getColor()->setRGB($e['level'] === 'error' ? 'B91C1C' : 'B45309');
            $r++;
        }
        if ($r === 2) {
            $sheet->setCellValue('A2', 'No problems found.');
        }
        $hidden = $productImport->error_count + $productImport->warning_count - count($productImport->errors ?? []);
        if ($hidden > 0) {
            $sheet->setCellValue('A'.($r + 1), "…and {$hidden} more. Fix the problems above and upload the file again to see the rest.");
        }
        foreach (['A' => 10, 'B' => 15, 'C' => 7, 'D' => 22, 'E' => 20, 'F' => 100] as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }
        $sheet->getStyle('F:F')->getAlignment()->setWrapText(true);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:F'.max(1, $r - 1));

        $path = tempnam(sys_get_temp_dir(), 'rep').'.xlsx';
        (new Xlsx($book))->save($path);
        $name = pathinfo($productImport->original_name, PATHINFO_FILENAME).' - problems.xlsx';

        return response()->download($path, $name)->deleteFileAfterSend();
    }

    /** Blank template, or every (filtered) product pre-filled for bulk editing. */
    public function template(Request $request, SettingsService $settings): BinaryFileResponse
    {
        $request->validate([
            'with_products' => ['sometimes', 'boolean'],
            'category' => ['nullable', 'integer'],
            'brand' => ['nullable', 'integer'],
        ]);
        $store = (string) ($settings->get('store_name') ?: 'MotoGears');
        $query = null;
        if ($request->boolean('with_products')) {
            $query = Product::query()
                ->when($request->category, fn ($q, $id) => $q->whereIn('category_id', \App\Models\Category::find($id)?->descendantIds() ?? [$id]))
                ->when($request->brand, fn ($q, $id) => $q->where('brand_id', $id));
        }
        $path = (new TemplateBuilder)->build($query, $store);
        $name = Str::slug($store).($query ? '-products-'.now()->format('Y-m-d') : '-product-import-template').'.xlsx';

        return response()->download($path, $name, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend();
    }

    /** Column guide for the import page (same data as the Instructions sheet). */
    public function columns(): JsonResponse
    {
        $out = [];
        foreach (ImportSchema::dataSheets() as $sheet) {
            foreach (ImportSchema::columns($sheet) as $key => $c) {
                $out[] = ['sheet' => $sheet, 'key' => $key, 'header' => $c[0], 'required' => $c[1], 'help' => $c[2], 'example' => $c[3] === null ? null : (string) $c[3], 'dropdown' => (bool) $c[5]];
            }
        }

        return $this->ok($out, 'Import columns');
    }

    private function payload(ProductImport $i): array
    {
        $s = $i->summary ?? [];
        $waiting = in_array($i->status, [ProductImport::PENDING, ProductImport::QUEUED], true) && $i->updated_at?->lt(now()->subSeconds(30));

        return [
            'id' => $i->id,
            'original_name' => $i->original_name,
            'file_size' => $i->file_size,
            'mode' => $i->mode,
            'mode_label' => $i->modeLabel(),
            'auto_start' => $i->auto_start,
            'status' => $i->status,
            'status_label' => self::STATUS_LABELS[$i->status] ?? $i->status,
            'phase' => in_array($i->status, [ProductImport::PENDING, ProductImport::VALIDATING, ProductImport::VALIDATED], true) || ($i->status === ProductImport::CANCELLED && ! $i->started_at) ? 'check' : 'import',
            'progress' => $i->progressPercent(),
            'total_rows' => $i->total_rows,
            'processed_rows' => $i->processed_rows,
            'created_count' => $i->created_count,
            'updated_count' => $i->updated_count,
            'skipped_count' => $i->skipped_count,
            'failed_count' => $i->failed_count,
            'warning_count' => $i->warning_count,
            'error_count' => $i->error_count,
            'summary' => $s ?: null,
            'message' => $i->message,
            'is_active' => $i->isActive(),
            'can_start' => $i->status === ProductImport::VALIDATED && (($s['will_create'] ?? 0) + ($s['will_update'] ?? 0)) > 0,
            'can_cancel' => $i->isActive() || $i->status === ProductImport::VALIDATED,
            'cancel_requested' => $i->cancel_requested,
            'waiting_for_worker' => $waiting,
            'has_file' => (bool) $i->file_path,
            'user' => $i->user ? ['id' => $i->user->id, 'name' => $i->user->name] : null,
            'created_at' => $i->created_at?->toIso8601String(),
            'validated_at' => $i->validated_at?->toIso8601String(),
            'started_at' => $i->started_at?->toIso8601String(),
            'finished_at' => $i->finished_at?->toIso8601String(),
        ];
    }

    private function fail(string $message, int $status): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }
}
