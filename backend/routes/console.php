<?php

use App\Services\OrderService;
use App\Services\ProductSearchIndexer;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('orders:cancel-unpaid', function (OrderService $orders) {
    $n = $orders->cancelStaleUnpaid();
    $this->info("Cancelled {$n} unpaid order(s).");
})->purpose('Cancel online-payment orders that were never paid and release their stock');

Artisan::command('products:reindex', function (ProductSearchIndexer $indexer) {
    $this->info('Indexed '.$indexer->reindexAll().' products.');
})->purpose('Rebuild the product search keyword index');

Artisan::command('product-imports:prune', function () {
    $days = (int) config('imports.keep_files_days', 30);
    $n = 0;
    \App\Models\ProductImport::where('created_at', '<', now()->subDays($days))->where('file_path', '!=', '')
        ->whereNotIn('status', \App\Models\ProductImport::ACTIVE)
        ->each(function ($import) use (&$n) {
            \Illuminate\Support\Facades\Storage::disk('local')->delete($import->file_path);
            $import->forceFill(['file_path' => ''])->save();
            $n++;
        });
    $this->info("Removed {$n} old import file(s).");
})->purpose('Delete uploaded product import files older than imports.keep_files_days (history is kept)');

Schedule::command('orders:cancel-unpaid')->everyTenMinutes()->withoutOverlapping();
Schedule::command('sanctum:prune-expired --hours=24')->daily();
Schedule::command('product-imports:prune')->daily();
