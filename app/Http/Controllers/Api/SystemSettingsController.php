<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SystemSettingsController extends Controller
{
    protected function getSettings(): SystemSetting
    {
        return SystemSetting::query()->first() ?? SystemSetting::create([
            'app_name' => config('app.name', 'ATIn'),
        ]);
    }

    /**
     * Build absolute URL to the API endpoint that serves the logo (same host as current request).
     * Works behind any proxy; no dependency on APP_URL or storage link.
     */
    protected function logoEndpointUrl(Request $request): ?string
    {
        $base = rtrim($request->getSchemeAndHttpHost().$request->getBasePath(), '/');
        return $base.'/api/settings/logo';
    }

    /**
     * Build absolute URL to the API endpoint that serves the auth background.
     */
    protected function authBackgroundEndpointUrl(Request $request): ?string
    {
        $base = rtrim($request->getSchemeAndHttpHost().$request->getBasePath(), '/');
        return $base.'/api/settings/auth-background';
    }

    /**
     * Serve logo image from storage. Public, no auth. Works in production without storage:link.
     */
    public function serveLogo(Request $request)
    {
        $settings = $this->getSettings();
        if (! $settings->logo_path) {
            return response()->json(['message' => 'No logo set.'], 404);
        }
        $path = $settings->logo_path;
        if (! Storage::disk('public')->exists($path)) {
            return response()->json(['message' => 'Logo file not found.'], 404);
        }
        $fullPath = Storage::disk('public')->path($path);
        $mime = mime_content_type($fullPath) ?: 'image/png';

        return response()->file($fullPath, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * Serve auth background image from storage. Public, no auth.
     */
    public function serveAuthBackground(Request $request)
    {
        $settings = $this->getSettings();
        if (! $settings->auth_background_path) {
            return response()->json(['message' => 'No auth background set.'], 404);
        }
        $path = $settings->auth_background_path;
        if (! Storage::disk('public')->exists($path)) {
            return response()->json(['message' => 'Auth background file not found.'], 404);
        }
        $fullPath = Storage::disk('public')->path($path);
        $mime = mime_content_type($fullPath) ?: 'image/png';

        return response()->file($fullPath, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    public function showPublic(Request $request): JsonResponse
    {
        $settings = $this->getSettings();
        $logoUrl = $settings->logo_path ? $this->logoEndpointUrl($request) : null;
        $authBgUrl = $settings->auth_background_path ? $this->authBackgroundEndpointUrl($request) : null;

        return response()->json([
            'app_name' => $settings->app_name,
            'logo_url' => $logoUrl,
            'auth_background_url' => $authBgUrl,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $settings = $this->getSettings();

        $validated = $request->validate([
            'app_name' => ['required', 'string', 'max:255'],
        ]);

        $settings->update([
            'app_name' => $validated['app_name'],
        ]);

        return response()->json([
            'app_name' => $settings->app_name,
            'logo_url' => $settings->logo_path ? $this->logoEndpointUrl($request) : null,
            'auth_background_url' => $settings->auth_background_path ? $this->authBackgroundEndpointUrl($request) : null,
        ]);
    }

    public function updateLogo(Request $request): JsonResponse
    {
        $settings = $this->getSettings();

        $validated = $request->validate([
            'logo' => ['required', 'image', 'max:2048'],
        ]);

        if ($settings->logo_path) {
            Storage::disk('public')->delete($settings->logo_path);
        }

        $path = $validated['logo']->store('logos', 'public');

        $settings->update([
            'logo_path' => $path,
        ]);

        return response()->json([
            'logo_url' => $this->logoEndpointUrl($request),
        ]);
    }

    public function updateAuthBackground(Request $request): JsonResponse
    {
        $settings = $this->getSettings();

        $validated = $request->validate([
            'background' => ['required', 'image', 'max:5120'],
        ]);

        if ($settings->auth_background_path) {
            Storage::disk('public')->delete($settings->auth_background_path);
        }

        $path = $validated['background']->store('auth-backgrounds', 'public');

        $settings->update([
            'auth_background_path' => $path,
        ]);

        return response()->json([
            'auth_background_url' => $this->authBackgroundEndpointUrl($request),
        ]);
    }
}

