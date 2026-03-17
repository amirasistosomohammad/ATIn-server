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
     * Build storage URL for a path. Same pattern as TheMidTaskApp: use api/storage/{path}
     * so the file is served by Laravel—works in production without storage:link.
     * Base URL is taken from the current request so it works behind proxies.
     */
    protected function storageUrl(Request $request, ?string $path): ?string
    {
        if (! $path || $path === '') {
            return null;
        }
        $path = ltrim(str_replace(['../', '..\\'], '', $path), '/');
        if ($path === '' || str_contains($path, '..')) {
            return null;
        }
        $base = rtrim($request->getSchemeAndHttpHost().$request->getBasePath(), '/');
        return $base.'/api/storage/'.$path;
    }

    public function showPublic(Request $request): JsonResponse
    {
        $settings = $this->getSettings();
        $logoUrl = $this->storageUrl($request, $settings->logo_path);
        $authBgUrl = $this->storageUrl($request, $settings->auth_background_path);

        return response()->json([
            'app_name' => $settings->app_name,
            'logo_url' => $logoUrl,
            'auth_background_url' => $authBgUrl,
            'logo_path' => $settings->logo_path,
            'auth_background_path' => $settings->auth_background_path,
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
            'logo_url' => $this->storageUrl($request, $settings->logo_path),
            'auth_background_url' => $this->storageUrl($request, $settings->auth_background_path),
            'logo_path' => $settings->logo_path,
            'auth_background_path' => $settings->auth_background_path,
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
            'logo_url' => $this->storageUrl($request, $path),
            'logo_path' => $path,
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
            'auth_background_url' => $this->storageUrl($request, $path),
            'auth_background_path' => $path,
        ]);
    }
}

