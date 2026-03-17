<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class AuditLog
{
    /**
     * Log an audit event. Uses audit_logs table: action, user_id, user_email, document_id, control_number, meta, ip_address.
     */
    public static function log(int $userId, string $action, ?int $documentId = null, ?string $controlNumber = null, ?array $meta = null, ?string $userEmail = null, ?Request $request = null): void
    {
        try {
            DB::table('audit_logs')->insert([
                'action' => $action,
                'user_id' => $userId,
                'user_email' => $userEmail,
                'document_id' => $documentId,
                'control_number' => $controlNumber,
                'meta' => $meta ? json_encode($meta) : null,
                'ip_address' => $request?->ip(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
