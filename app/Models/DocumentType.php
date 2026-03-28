<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentType extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'code',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Whether this type is the generic "Other" option that requires a free-text specification on documents.
     */
    public function isOtherChoice(): bool
    {
        return strcasecmp(trim($this->name), 'Other') === 0;
    }

    /**
     * Documents registered with this type.
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }
}
