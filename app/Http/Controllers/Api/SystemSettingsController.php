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

    public function showPublic(): JsonResponse
    {
        $settings = $this->getSettings();

        return response()->json([
            'app_name' => $settings->app_name,
            'logo_url' => $settings->logo_path ? Storage::url($settings->logo_path) : null,
            'auth_background_url' => $settings->auth_background_path ? Storage::url($settings->auth_background_path) : null,
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
            'logo_url' => $settings->logo_path ? Storage::url($settings->logo_path) : null,
            'auth_background_url' => $settings->auth_background_path ? Storage::url($settings->auth_background_path) : null,
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
            'logo_url' => Storage::url($path),
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
            'auth_background_url' => Storage::url($path),
        ]);
    }
}

