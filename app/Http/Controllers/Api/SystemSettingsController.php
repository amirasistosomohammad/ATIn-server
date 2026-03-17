<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class SystemSettingsController extends Controller
{
    protected function getSettings(): SystemSetting
    {
        return SystemSetting::query()->first() ?? SystemSetting::create([
            'app_name' => config('app.name', 'ATIn'),
        ]);
    }

    /**
     * Return absolute URL for a storage path (logo/auth background).
     * Uses APP_URL so production always gets correct full URL regardless of frontend domain.
     */
    protected function storageUrl(?string $path): ?string
    {
        if (! $path || ! trim($path)) {
            return null;
        }
        $path = ltrim(str_replace('\\', '/', $path), '/');
        return URL::asset('storage/'.$path);
    }

    public function showPublic(): JsonResponse
    {
        $settings = $this->getSettings();

        return response()->json([
            'app_name' => $settings->app_name,
            'logo_url' => $this->storageUrl($settings->logo_path),
            'auth_background_url' => $this->storageUrl($settings->auth_background_path),
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
            'logo_url' => $this->storageUrl($settings->logo_path),
            'auth_background_url' => $this->storageUrl($settings->auth_background_path),
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
            'logo_url' => $this->storageUrl($path),
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
            'auth_background_url' => $this->storageUrl($path),
        ]);
    }
}

