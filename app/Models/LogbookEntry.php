<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogbookEntry extends Model
{
    use HasFactory;

    public const ACTION_IN = 'in';
    public const ACTION_OUT = 'out';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'document_id',
        'action',
        'user_id',
        'remarks',
        'registration_details',
        'moved_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'moved_at' => 'datetime',
            'registration_details' => 'array',
        ];
    }

    /**
     * Document (control number) this entry belongs to.
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * User who performed the In or Out action.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
