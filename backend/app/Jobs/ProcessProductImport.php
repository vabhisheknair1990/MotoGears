<?php

namespace App\Jobs;

use App\Models\ProductImport;
use App\Notifications\ProductImportNotification;
use App\Services\AuditLogger;
use App\Services\ProductImport\ImportFileException;
use App\Services\ProductImport\ImportProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Runs an import step in the queue worker so large files never block a web request.
 * phase "check": validates everything without saving (and continues straight into the import
 * when the admin chose "import automatically" and nothing failed); phase "import": saves.
 */
class ProcessProductImport implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 3600;

    public function __construct(public int $importId, public string $phase = 'check') {}

    public function handle(ImportProcessor $processor, AuditLogger $audit): void
    {
        $import = ProductImport::find($this->importId);
        if (! $import || $import->cancel_requested) {
            $import?->forceFill(['status' => ProductImport::CANCELLED, 'finished_at' => now(), 'message' => 'Cancelled before it started.'])->save();

            return;
        }
        @set_time_limit(0);

        try {
            if ($this->phase === 'check') {
                $processor->run($import, true);
                $import->refresh();
                $s = $import->summary ?? [];
                $ready = ($s['will_create'] ?? 0) + ($s['will_update'] ?? 0) > 0;
                if ($import->status === ProductImport::VALIDATED && $import->auto_start && ($s['invalid'] ?? 0) === 0 && $ready) {
                    $import->forceFill(['status' => ProductImport::QUEUED])->save();
                    $this->phase = 'import';
                } else {
                    $import->user?->notify(new ProductImportNotification($import));

                    return;
                }
            }

            $processor->run($import, false);
            $import->refresh();
            $audit->log('products.imported', $import, null, [
                'file' => $import->original_name, 'created' => $import->created_count, 'updated' => $import->updated_count,
                'failed' => $import->failed_count, 'skipped' => $import->skipped_count,
            ], $import->user);
            $import->user?->notify(new ProductImportNotification($import));
        } catch (ImportFileException $e) {
            $this->fail($import, $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('Product import crashed', ['import' => $this->importId, 'error' => $e->getMessage(), 'at' => $e->getFile().':'.$e->getLine()]);
            $this->fail($import, 'The import stopped unexpectedly: '.\Illuminate\Support\Str::limit($e->getMessage(), 200));
        }
    }

    /** Called by Laravel when the worker itself gives up (timeout, crash). */
    public function failed(?\Throwable $e): void
    {
        $import = ProductImport::find($this->importId);
        if ($import && ! $import->isFinished()) {
            $this->fail($import, 'The background worker stopped before the import finished'.($e ? ': '.\Illuminate\Support\Str::limit($e->getMessage(), 150) : '.'));
        }
    }

    private function fail(ProductImport $import, string $message): void
    {
        $import->refresh();
        $import->forceFill(['status' => ProductImport::FAILED, 'message' => $message, 'finished_at' => now()])->save();
        $import->user?->notify(new ProductImportNotification($import));
    }
}
