<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Serve files from the public storage disk via GET /api/storage/{path}.
 * Same pattern as TheMidTaskApp: Laravel serves the file so it works in production
 * without storage:link or APP_URL (e.g. DigitalOcean App Platform).
 */
class StorageController extends Controller
{
    public function serve(Request $request, string $path)
    {
        $path = ltrim(str_replace(['../', '..\\'], '', $path), '/');
        if ($path === '' || str_contains($path, '..')) {
            return response()->json(['message' => 'Invalid path.'], 400);
        }

        if (! Storage::disk('public')->exists($path)) {
            return response()->json(['message' => 'File not found.'], 404);
        }

        $fullPath = Storage::disk('public')->path($path);
        $mime = @mime_content_type($fullPath) ?: 'application/octet-stream';

        return response()->file($fullPath, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
