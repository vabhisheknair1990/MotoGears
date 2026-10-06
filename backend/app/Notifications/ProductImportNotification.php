<?php

namespace App\Notifications;

use App\Models\ProductImport;
use Illuminate\Notifications\Notification;

/** Tells the admin who uploaded a file that its check or import has finished. */
class ProductImportNotification extends Notification
{
    public function __construct(public ProductImport $import) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $i = $this->import;
        $s = $i->summary ?? [];
        [$title, $message] = match ($i->status) {
            ProductImport::VALIDATED => ['Import file checked', ($s['invalid'] ?? 0) > 0
                ? "{$i->original_name}: ".($s['will_create'] ?? 0).' new, '.($s['will_update'] ?? 0).' to update, '.$s['invalid'].' with errors. Review and start the import.'
                : "{$i->original_name} is ready: ".($s['will_create'] ?? 0).' new and '.($s['will_update'] ?? 0).' existing products. Start the import when you are ready.'],
            ProductImport::COMPLETED => ['Product import finished', "{$i->original_name}: {$i->created_count} added, {$i->updated_count} updated."],
            ProductImport::COMPLETED_WITH_ERRORS => ['Product import finished with errors', "{$i->original_name}: {$i->created_count} added, {$i->updated_count} updated, {$i->failed_count} not imported — download the error report."],
            ProductImport::CANCELLED => ['Product import cancelled', $i->message ?: "{$i->original_name} was cancelled."],
            default => ['Product import failed', $i->message ?: "{$i->original_name} could not be imported."],
        };

        return ['title' => $title, 'message' => $message, 'product_import_id' => $i->id, 'url' => '/admin/products/import?id='.$i->id];
    }
}
