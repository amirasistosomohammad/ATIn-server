<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

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

    /**
     * Parse php.ini size values (e.g. "8M", "512K") to bytes.
     */
    protected static function iniSizeToBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }
        $last = strtolower($value[strlen($value) - 1]);
        $num = (int) $value;

        return match ($last) {
            'g' => $num * 1024 * 1024 * 1024,
            'm' => $num * 1024 * 1024,
            'k' => $num * 1024,
            default => (int) $value,
        };
    }

    /**
     * Fail fast with a clear message when the request body is larger than PHP post_max_size
     * (PHP drops the whole body; otherwise Laravel only reports "required" on the file field).
     */
    protected function assertRequestWithinPostMax(Request $request, string $field): void
    {
        $length = (int) $request->header('Content-Length', 0);
        if ($length <= 0) {
            return;
        }
        $postMax = self::iniSizeToBytes((string) ini_get('post_max_size'));
        if ($postMax > 0 && $length > $postMax) {
            throw ValidationException::withMessages([
                $field => ['This upload is larger than the server allows (PHP post_max_size). Increase upload_max_filesize and post_max_size on the server (see public/.user.ini), or use a smaller image.'],
            ]);
        }
    }

    protected function phpUploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE => 'This file exceeds the server upload_max_filesize limit. Use a smaller image or raise PHP limits (public/.user.ini).',
            UPLOAD_ERR_FORM_SIZE => 'The file exceeds the maximum size allowed by the form.',
            UPLOAD_ERR_PARTIAL => 'The file was only partially uploaded. Please try again.',
            UPLOAD_ERR_NO_FILE => 'No file was received. Check your connection and try again.',
            UPLOAD_ERR_NO_TMP_DIR => 'The server is missing a temporary folder for uploads.',
            UPLOAD_ERR_CANT_WRITE => 'The server could not write the uploaded file to disk.',
            UPLOAD_ERR_EXTENSION => 'A PHP extension blocked this upload.',
            default => 'Upload failed.',
        };
    }

    /**
     * Surface PHP-level upload errors before Laravel's "image" / "mimes" rules (422 with clearer text).
     */
    protected function assertUploadedFileOk(Request $request, string $field): void
    {
        if (! $request->hasFile($field)) {
            return;
        }
        $file = $request->file($field);
        if (! $file->isValid()) {
            throw ValidationException::withMessages([
                $field => [$this->phpUploadErrorMessage($file->getError())],
            ]);
        }
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

        $this->assertRequestWithinPostMax($request, 'logo');
        $this->assertUploadedFileOk($request, 'logo');

        // Use explicit mimes (not only "image"): stricter "image" rule often 422s valid JPEG/WebP on some hosts.
        $validated = $request->validate([
            'logo' => ['required', 'file', 'max:2048', 'mimes:jpeg,jpg,png,gif,webp,svg'],
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

        $this->assertRequestWithinPostMax($request, 'background');
        $this->assertUploadedFileOk($request, 'background');

        $validated = $request->validate([
            'background' => ['required', 'file', 'max:5120', 'mimes:jpeg,jpg,png,gif,webp,avif'],
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
