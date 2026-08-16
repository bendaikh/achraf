<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ManagedDocumentVersion extends Model
{
    protected $fillable = [
        'managed_document_id',
        'version_number',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
        'checksum',
        'source',
        'uploaded_by',
    ];

    protected $casts = [
        'version_number' => 'integer',
        'size' => 'integer',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(ManagedDocument::class, 'managed_document_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function absolutePath(): string
    {
        return Storage::disk($this->disk)->path($this->path);
    }

    public function existsOnDisk(): bool
    {
        return Storage::disk($this->disk)->exists($this->path);
    }
}
