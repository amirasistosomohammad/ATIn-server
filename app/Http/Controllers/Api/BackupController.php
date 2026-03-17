<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    protected function scheduleRow()
    {
        return DB::table('backup_schedules')->first();
    }

    public function showSchedule(): JsonResponse
    {
        $row = $this->scheduleRow();

        return response()->json([
            'frequency' => $row->frequency,
            'run_at_time' => $row->run_at_time,
            'timezone' => $row->timezone,
            'last_run_at' => $row->last_run_at,
            'next_run_at' => $row->next_run_at,
            'has_latest_file' => $this->latestBackupFilename() !== null,
        ]);
    }

    public function updateSchedule(): JsonResponse
    {
        $validated = request()->validate([
            'frequency' => ['required', 'in:off,daily,weekly'],
            'run_at_time' => ['required', 'date_format:H:i'],
        ]);

        $row = $this->scheduleRow();

        DB::table('backup_schedules')->where('id', $row->id)->update([
            'frequency' => $validated['frequency'],
            'run_at_time' => $validated['run_at_time'],
            'updated_at' => now(),
        ]);

        $updated = $this->scheduleRow();

        return response()->json([
            'frequency' => $updated->frequency,
            'run_at_time' => $updated->run_at_time,
            'timezone' => $updated->timezone,
            'last_run_at' => $updated->last_run_at,
            'next_run_at' => $updated->next_run_at,
        ]);
    }

    protected function backupsDisk()
    {
        return Storage::disk('local');
    }

    protected function backupsPath(): string
    {
        return 'backups';
    }

    protected function latestBackupFilename(): ?string
    {
        $files = $this->backupsDisk()->files($this->backupsPath());
        if (empty($files)) {
            return null;
        }
        sort($files);

        return end($files);
    }

    public function listBackups(): JsonResponse
    {
        $disk = $this->backupsDisk();
        $files = $disk->files($this->backupsPath());

        $backups = array_map(function ($path) use ($disk) {
            $timestamp = $disk->lastModified($path);

            return [
                'filename' => basename($path),
                'created_at' => $timestamp ? date('c', $timestamp) : null,
            ];
        }, $files);

        usort($backups, fn ($a, $b) => strcmp($b['filename'], $a['filename']));

        return response()->json([
            'backups' => $backups,
        ]);
    }

    public function downloadNow(): StreamedResponse
    {
        $connection = Config::get('database.default');
        $dbName = Config::get("database.connections.$connection.database");
        $username = Config::get("database.connections.$connection.username");
        $password = Config::get("database.connections.$connection.password");
        $host = Config::get("database.connections.$connection.host", '127.0.0.1');

        $filename = 'atin-backup-' . now()->format('Ymd_His') . '.sql';
        $path = $this->backupsPath() . '/' . $filename;

        $cmd = sprintf(
            'mysqldump -h%s -u%s -p%s %s 2>&1',
            escapeshellarg($host),
            escapeshellarg($username),
            escapeshellarg($password),
            escapeshellarg($dbName)
        );

        $sql = shell_exec($cmd);
        if (! $sql) {
            return response()->json(['message' => 'Backup command failed. Check server configuration.'], 500);
        }

        $this->backupsDisk()->put($path, $sql);

        return response()->streamDownload(function () use ($sql) {
            echo $sql;
        }, $filename, [
            'Content-Type' => 'application/sql',
        ]);
    }

    public function downloadLatest()
    {
        $latest = $this->latestBackupFilename();
        if (! $latest) {
            return response()->json(['message' => 'No backup file available.'], 404);
        }

        return $this->downloadFile(basename($latest));
    }

    public function downloadFile(string $filename)
    {
        $path = $this->backupsPath() . '/' . $filename;
        $disk = $this->backupsDisk();

        if (! $disk->exists($path)) {
            return response()->json(['message' => 'Backup not found.'], 404);
        }

        return $disk->download($path, $filename, [
            'Content-Type' => 'application/sql',
        ]);
    }
}

