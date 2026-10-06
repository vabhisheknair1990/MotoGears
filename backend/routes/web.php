<?php

use Illuminate\Support\Facades\Route;

// The storefront is the separate Angular app; the backend only serves the API and docs.
Route::get('/', fn () => response()->json([
    'name' => config('app.name').' API',
    'version' => 'v1',
    'base_url' => url('/v1'),
    'docs' => url('/docs'),
    'health' => url('/up'),
]));

Route::get('/docs', fn () => response()->file(public_path('docs/index.html')));
Route::get('/docs/openapi.yaml', fn () => response()->file(public_path('docs/openapi.yaml'), ['Content-Type' => 'application/yaml']));
// Old addresses from before the API moved to its own host root.
Route::redirect('/api/docs', '/docs', 301);
Route::get('/api/v1/{path?}', function (\Illuminate\Http\Request $request, ?string $path = null) {
    $query = $request->getQueryString();

    return redirect('/v1'.($path ? '/'.$path : '').($query ? '?'.$query : ''), 308);
})->where('path', '.*');

// Serves uploaded media when the public/storage symlink is unavailable (e.g. some Windows
// bind mounts). With the symlink in place the web server serves these files directly.
Route::get('/storage/{path}', function (string $path) {
    $disk = \Illuminate\Support\Facades\Storage::disk('public');
    abort_if(str_contains($path, '..') || ! $disk->exists($path), 404);

    return response()->file($disk->path($path), ['Cache-Control' => 'public, max-age=604800']);
})->where('path', '.*');
