<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'document_type_id',
        'document_type_other',
        'control_number',
        'description',
        'status',
        'current_holder_user_id',
        'created_by_user_id',
        'supplier_name',
        'amount',
        'date_prepared',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'date_prepared' => 'date',
        ];
    }

    /**
     * Document type (e.g. Purchase Request, PO).
     */
    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class, 'document_type_id');
    }

    /**
     * User who currently has the document (null when in_transit).
     */
    public function currentHolder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_holder_user_id');
    }

    /**
     * User who created/owns the document (owner).
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Logbook entries (In/Out) for this document, ordered by moved_at ascending (chronological).
     */
    public function logbookEntries(): HasMany
    {
        return $this->hasMany(LogbookEntry::class)->orderBy('moved_at', 'asc');
    }

    /**
     * Label for lists and exports, including free-text when type is "Other".
     */
    public function documentTypeLabel(): string
    {
        $type = $this->documentType;
        $name = $type?->name ?? '';
        if ($type && $type->isOtherChoice()) {
            $spec = trim((string) ($this->document_type_other ?? ''));
            if ($spec !== '') {
                return $name.' ('.$spec.')';
            }
        }

        return $name;
    }
}
