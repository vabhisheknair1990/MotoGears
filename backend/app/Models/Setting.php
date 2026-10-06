<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'type', 'group', 'is_public'];

    protected $casts = ['is_public' => 'boolean'];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('settings.all'));
        static::deleted(fn () => Cache::forget('settings.all'));
    }

    public function typedValue(): mixed
    {
        return match ($this->type) {
            'number' => is_numeric($this->value) ? $this->value + 0 : 0,
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode((string) $this->value, true),
            default => $this->value,
        };
    }

    /** All settings as key => typed value (cached). */
    public static function allValues(): array
    {
        return Cache::rememberForever('settings.all', fn () => static::all()->mapWithKeys(fn (Setting $s) => [$s->key => $s->typedValue()])->all());
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return static::allValues()[$key] ?? $default;
    }
}
