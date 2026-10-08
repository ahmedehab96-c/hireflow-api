<?php

use Illuminate\Support\Facades\Route;

// This app is a JSON API; the web root only identifies the service.
Route::get('/', fn () => response()->json([
    'name' => config('app.name').' API',
    'status' => 'ok',
    'api' => url('/api'),
    'health' => url('/up'),
]));
