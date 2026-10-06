<?php

namespace App\Services;

use App\Support\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Stores validated uploads on the media disk and returns the relative path saved in MySQL.
 * Files are renamed to random names; extensions are derived from the detected MIME type, not
 * the client-supplied filename.
 */
class ImageUploadService
{
    public const MIMES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];

    public function store(UploadedFile $file, string $folder): string
    {
        $mime = $file->getMimeType();
        $ext = self::MIMES[$mime] ?? throw new \App\Exceptions\BusinessException('Unsupported image type.', 422, ['image' => ['Only JPG, PNG, WEBP or GIF images are allowed.']]);
        $name = now()->format('Y/m').'/'.Str::uuid().'.'.$ext;

        return Media::disk()->putFileAs($folder, $file, $name) ?: throw new \RuntimeException('Could not store upload');
    }

    /** Stores raw image bytes (e.g. downloaded during an import) whose MIME type was already verified. */
    public function storeContents(string $contents, string $mime, string $folder): string
    {
        $ext = self::MIMES[$mime] ?? throw new \InvalidArgumentException('Unsupported image type.');
        $path = trim($folder, '/').'/'.now()->format('Y/m').'/'.Str::uuid().'.'.$ext;

        return Media::disk()->put($path, $contents) ? $path : throw new \RuntimeException('Could not store image');
    }

    public function delete(?string $path): void
    {
        if ($path && ! Str::startsWith($path, ['http://', 'https://'])) {
            Media::disk()->delete($path);
        }
    }

    /** Standard validation rules for image uploads. */
    public static function rules(bool $required = false, int $maxKb = 5120): array
    {
        return [$required ? 'required' : 'nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:'.$maxKb, 'dimensions:max_width=5000,max_height=5000'];
    }
}
