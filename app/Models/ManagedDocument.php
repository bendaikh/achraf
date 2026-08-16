<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class ManagedDocument extends Model
{
    protected $fillable = [
        'documentable_type',
        'documentable_id',
        'section_key',
        'category',
        'document_type_label',
        'reference',
        'document_date',
        'display_name',
        'sequence',
        'current_version_id',
        'source',
        'uploaded_by',
        'imported_at',
        'is_active',
    ];

    protected $casts = [
        'document_date' => 'date',
        'imported_at' => 'datetime',
        'is_active' => 'boolean',
        'sequence' => 'integer',
    ];

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ManagedDocumentVersion::class)->orderByDesc('version_number');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(ManagedDocumentVersion::class, 'current_version_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function absolutePath(): ?string
    {
        $version = $this->currentVersion;

        if (! $version) {
            return null;
        }

        return Storage::disk($version->disk)->path($version->path);
    }

    public function downloadName(): string
    {
        return $this->display_name;
    }
}
