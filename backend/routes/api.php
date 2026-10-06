<?php

use Illuminate\Support\Facades\Route;

/*
| Versioned REST API. Each version lives in its own file so /api/v2 can be added
| alongside v1 without touching existing clients.
*/

Route::prefix('v1')->name('v1.')->middleware('throttle:api')->group(base_path('routes/api/v1.php'));

// Route::prefix('v2')->name('v2.')->middleware('throttle:api')->group(base_path('routes/api/v2.php'));
