<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One uploaded product spreadsheet and its progress through the two background steps:
 * check (dry run, nothing saved) → import.
 */
class ProductImport extends Model
{
    public const PENDING = 'pending';                 // waiting for the worker to check the file
    public const VALIDATING = 'validating';
    public const VALIDATED = 'validated';             // checked, waiting for the admin to confirm
    public const QUEUED = 'queued';                   // confirmed, waiting for the worker
    public const IMPORTING = 'importing';
    public const COMPLETED = 'completed';
    public const COMPLETED_WITH_ERRORS = 'completed_with_errors';
    public const FAILED = 'failed';
    public const CANCELLED = 'cancelled';

    public const ACTIVE = [self::PENDING, self::VALIDATING, self::QUEUED, self::IMPORTING];
    public const FINISHED = [self::COMPLETED, self::COMPLETED_WITH_ERRORS, self::FAILED, self::CANCELLED];

    public const MODES = ['upsert', 'create', 'update'];

    /** Issues stored per import; the error report and counters still cover all of them. */
    public const MAX_STORED_ERRORS = 2000;

    protected $fillable = [
        'user_id', 'original_name', 'file_path', 'file_size', 'mode', 'auto_start', 'status',
        'total_rows', 'processed_rows', 'created_count', 'updated_count', 'skipped_count', 'failed_count',
        'warning_count', 'error_count', 'summary', 'errors', 'message', 'cancel_requested',
        'validated_at', 'started_at', 'finished_at',
    ];

    protected $attributes = [
        'mode' => 'upsert', 'status' => self::PENDING, 'auto_start' => false, 'cancel_requested' => false,
    ];

    protected $casts = [
        'auto_start' => 'boolean',
        'cancel_requested' => 'boolean',
        'summary' => 'array',
        'errors' => 'array',
        'validated_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE, true);
    }

    public function isFinished(): bool
    {
        return in_array($this->status, self::FINISHED, true);
    }

    public function progressPercent(): int
    {
        if (in_array($this->status, [self::VALIDATED, ...self::FINISHED], true)) {
            return 100;
        }

        return $this->total_rows > 0 ? (int) floor($this->processed_rows * 100 / $this->total_rows) : 0;
    }

    public function modeLabel(): string
    {
        return match ($this->mode) {
            'create' => 'Add new products only',
            'update' => 'Update existing products only',
            default => 'Add new and update existing',
        };
    }
}
