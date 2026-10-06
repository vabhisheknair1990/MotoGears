<?php

use Illuminate\Support\Facades\Route;

// The storefront is the separate Angular app; the backend only serves the API and docs.
Route::get('/', fn () => response()->json([
    'name' => config('app.name').' API',
    'version' => 'v1',
    'base_url' => url('/api/v1'),
    'docs' => url('/api/docs'),
    'health' => url('/up'),
]));

Route::get('/api/docs', fn () => response()->file(public_path('docs/index.html')));
Route::get('/api/docs/openapi.yaml', fn () => response()->file(public_path('docs/openapi.yaml'), ['Content-Type' => 'application/yaml']));

// Serves uploaded media when the public/storage symlink is unavailable (e.g. some Windows
// bind mounts). With the symlink in place the web server serves these files directly.
Route::get('/storage/{path}', function (string $path) {
    $disk = \Illuminate\Support\Facades\Storage::disk('public');
    abort_if(str_contains($path, '..') || ! $disk->exists($path), 404);

    return response()->file($disk->path($path), ['Cache-Control' => 'public, max-age=604800']);
})->where('path', '.*');
