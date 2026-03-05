<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\File;

Route::get('/', function () {
    return response()->json([
    'message' => 'Locker ERP API',
    'version' => '1.0.0',
    'status' => 'running'
    ]);
});

// Documentación API (OpenAPI) — accesible sin autenticación para el frontend
Route::get('/api-docs', function () {
    $path = base_path('docs/openapi.yaml');
    if (!File::exists($path)) {
        abort(404, 'OpenAPI spec not found');
    }
    $content = File::get($path);
    return response($content, 200, [
        'Content-Type' => 'application/x-yaml',
        'Cache-Control' => 'public, max-age=60',
    ]);
});

Route::get('/api-docs.json', function () {
    $path = base_path('docs/openapi.yaml');
    if (!File::exists($path)) {
        abort(404, 'OpenAPI spec not found');
    }
    $yaml = File::get($path);
    $spec = \Symfony\Component\Yaml\Yaml::parse($yaml);
    return response()->json($spec, 200, [
        'Cache-Control' => 'public, max-age=60',
    ]);
});

Route::get('/docs', function () {
    return response()->view('docs');
});
