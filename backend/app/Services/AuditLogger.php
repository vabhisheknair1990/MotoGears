<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    private const HIDDEN = ['password', 'remember_token', 'updated_at', 'created_at', 'search_keywords'];

    public function log(string $action, ?Model $model = null, ?array $old = null, ?array $new = null, ?User $user = null): void
    {
        $request = app()->runningInConsole() ? null : request();
        AuditLog::create([
            'user_id' => $user?->id ?? $request?->user()?->id,
            'action' => $action,
            'auditable_type' => $model?->getMorphClass(),
            'auditable_id' => $model?->getKey(),
            'old_values' => $old ? $this->clean($old) : null,
            'new_values' => $new ? $this->clean($new) : null,
            'ip_address' => $request?->ip(),
            'user_agent' => substr((string) $request?->userAgent(), 0, 255) ?: null,
        ]);
    }

    /** Drop secrets, uploaded files and anything that is not JSON-serialisable. */
    private function clean(array $values): array
    {
        $values = array_diff_key($values, array_flip(self::HIDDEN));

        return array_map(function ($v) {
            if ($v instanceof \UnitEnum) {
                return $v instanceof \BackedEnum ? $v->value : $v->name;
            }
            if ($v instanceof \DateTimeInterface) {
                return $v->format(DATE_ATOM);
            }
            if (is_object($v)) {
                return $v instanceof \Illuminate\Http\UploadedFile ? '[file] '.$v->getClientOriginalName() : '['.class_basename($v).']';
            }

            return is_array($v) ? $this->clean($v) : $v;
        }, array_filter($values, fn ($v) => ! is_resource($v)));
    }

    /** Convenience for Eloquent updates: log only changed attributes. */
    public function changes(string $action, Model $model, ?User $user = null): void
    {
        $changes = $model->getChanges();
        if (! $changes) {
            return;
        }
        $old = array_intersect_key($model->getPrevious(), $changes);
        $this->log($action, $model, $old, $changes, $user);
    }
}
