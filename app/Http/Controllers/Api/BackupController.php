<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Ifsnop\Mysqldump\Mysqldump;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PDO;
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

    /**
     * Same host rules as Laravel MySQL connector; avoids Windows localhost edge cases.
     */
    protected function normalizeMysqlHost(string $host): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $h = strtolower(trim($host));
            if (in_array($h, ['localhost', '::1'], true)) {
                return '127.0.0.1';
            }
        }

        return $host;
    }

    /**
     * PDO DSN aligned with Illuminate\Database\Connectors\MySqlConnector.
     */
    protected function mysqlDsnFromLaravelConfig(array $config): string
    {
        $database = $config['database'] ?? '';

        if (! empty($config['unix_socket'])) {
            return sprintf(
                'mysql:unix_socket=%s;dbname=%s;charset=%s',
                $config['unix_socket'],
                $database,
                $config['charset'] ?? 'utf8mb4'
            );
        }

        $host = $this->normalizeMysqlHost((string) ($config['host'] ?? '127.0.0.1'));
        $port = (string) ($config['port'] ?? '3306');

        return sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $host,
            $port,
            $database,
            $config['charset'] ?? 'utf8mb4'
        );
    }

    /**
     * PDO attributes: Laravel connection options (e.g. SSL to managed DB) + non-persistent for HTTP requests.
     *
     * @return array<int, mixed>
     */
    protected function mysqlPdoSettingsForBackup(array $config): array
    {
        $fromConfig = $config['options'] ?? [];
        if (! is_array($fromConfig)) {
            $fromConfig = [];
        }

        $fromConfig = array_filter(
            $fromConfig,
            fn ($v) => $v !== null && $v !== ''
        );

        return array_merge(
            [PDO::ATTR_PERSISTENT => false],
            $fromConfig
        );
    }

    /**
     * Pure PHP dump (no mysqldump binary). Uses the same PDO connection parameters as the app.
     *
     * @return array{ok: true, sql: string}|array{ok: false, message: string}
     */
    protected function runMysqlDump(): array
    {
        $connectionName = (string) Config::get('database.default');
        $config = Config::get("database.connections.{$connectionName}");
        if (! is_array($config)) {
            return ['ok' => false, 'message' => 'Database configuration is missing.'];
        }

        $driver = $config['driver'] ?? '';

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return [
                'ok' => false,
                'message' => 'SQL backup requires DB_CONNECTION=mysql or mariadb.',
            ];
        }

        $dsn = $this->mysqlDsnFromLaravelConfig($config);
        $user = (string) ($config['username'] ?? '');
        $password = (string) ($config['password'] ?? '');
        $pdoSettings = $this->mysqlPdoSettingsForBackup($config);

        $dumpSettings = [
            'default-character-set' => Mysqldump::UTF8MB4,
            'single-transaction' => true,
            'lock-tables' => false,
            'routines' => true,
            'events' => true,
            'add-drop-table' => true,
            'add-drop-trigger' => true,
            'hex-blob' => true,
        ];

        $tmp = tempnam(sys_get_temp_dir(), 'atinbk_');
        if ($tmp === false) {
            return ['ok' => false, 'message' => 'Could not create a temporary file for the backup.'];
        }

        try {
            $dump = new Mysqldump($dsn, $user, $password, $dumpSettings, $pdoSettings);
            $dump->start($tmp);
        } catch (\Throwable $e) {
            if (is_file($tmp)) {
                @unlink($tmp);
            }

            return [
                'ok' => false,
                'message' => 'Database backup failed: '.$e->getMessage(),
            ];
        }

        $sql = @file_get_contents($tmp);
        if (is_file($tmp)) {
            @unlink($tmp);
        }

        if ($sql === false || $sql === '') {
            return [
                'ok' => false,
                'message' => 'Backup produced no output. Check database credentials and permissions.',
            ];
        }

        return ['ok' => true, 'sql' => $sql];
    }

    public function downloadNow(): StreamedResponse|JsonResponse
    {
        $dump = $this->runMysqlDump();
        if (! $dump['ok']) {
            return response()->json(['message' => $dump['message']], 500);
        }

        $sql = $dump['sql'];
        $filename = 'atin-backup-'.now()->format('Ymd_His').'.sql';
        $path = $this->backupsPath().'/'.$filename;

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
        $path = $this->backupsPath().'/'.$filename;
        $disk = $this->backupsDisk();

        if (! $disk->exists($path)) {
            return response()->json(['message' => 'Backup not found.'], 404);
        }

        return $disk->download($path, $filename, [
            'Content-Type' => 'application/sql',
        ]);
    }
}
