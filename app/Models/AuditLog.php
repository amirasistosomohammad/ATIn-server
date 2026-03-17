<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Request;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'action',
        'user_id',
        'user_email',
        'document_id',
        'control_number',
        'meta',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public static function log(string $action, ?int $userId = null, ?string $userEmail = null, ?int $documentId = null, ?string $controlNumber = null, ?array $meta = null): void
    {
        self::create([
            'action' => $action,
            'user_id' => $userId,
            'user_email' => $userEmail,
            'document_id' => $documentId,
            'control_number' => $controlNumber,
            'meta' => $meta,
            'ip_address' => Request::ip(),
        ]);
    }
}
