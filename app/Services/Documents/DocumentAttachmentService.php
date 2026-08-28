<?php

namespace App\Services\Documents;

use App\Models\ManagedDocument;
use App\Models\ManagedDocumentVersion;
use App\Support\DocumentAttachmentRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentAttachmentService
{
    public function __construct(
        protected DocumentNamingService $naming
    ) {}

    public function store(
        string $sectionKey,
        Model $record,
        UploadedFile $file,
        array $options = []
    ): ManagedDocument {
        $config = DocumentAttachmentRegistry::get($sectionKey);
        $category = $options['category'] ?? 'primary';
        $source = $options['source'] ?? 'upload';
        $userId = $options['user_id'] ?? auth()->id();
        $disk = config('managed_documents.disk', 'local');

        $reference = $this->naming->sanitizeReference(
            $options['reference'] ?? DocumentAttachmentRegistry::referenceFor($sectionKey, $record)
        );
        $documentDate = $options['document_date']
            ?? DocumentAttachmentRegistry::documentDateFor($sectionKey, $record);
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $sequence = $this->naming->nextSequence($sectionKey, $reference);
        $displayName = $this->naming->buildDisplayName($reference, $extension, $sequence);

        return DB::transaction(function () use (
            $sectionKey,
            $record,
            $file,
            $config,
            $category,
            $source,
            $userId,
            $disk,
            $reference,
            $documentDate,
            $sequence,
            $displayName,
            $options
        ) {
            $document = ManagedDocument::create([
                'documentable_type' => $record->getMorphClass(),
                'documentable_id' => $record->getKey(),
                'section_key' => $sectionKey,
                'category' => $category,
                'document_type_label' => DocumentAttachmentRegistry::typeLabelFor($sectionKey, $category),
                'reference' => $reference,
                'document_date' => $documentDate,
                'expires_at' => $options['expires_at'] ?? null,
                'display_name' => $displayName,
                'sequence' => $sequence,
                'source' => $source,
                'uploaded_by' => $userId,
                'imported_at' => now(),
                'is_active' => true,
            ]);

            $version = $this->storeVersion($document, $file, $disk, $config['folder'], $source, $userId, 1);
            $document->update(['current_version_id' => $version->id]);
            $this->syncLegacyField($record, $config['legacy_field'] ?? null, $version);

            return $document->fresh(['currentVersion', 'versions', 'uploader']);
        });
    }

    public function replace(
        ManagedDocument $document,
        UploadedFile $file,
        array $options = []
    ): ManagedDocument {
        $config = DocumentAttachmentRegistry::get($document->section_key);
        $source = $options['source'] ?? 'upload';
        $userId = $options['user_id'] ?? auth()->id();
        $disk = config('managed_documents.disk', 'local');
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');

        return DB::transaction(function () use ($document, $file, $config, $source, $userId, $disk, $extension) {
            $nextVersion = ((int) $document->versions()->max('version_number')) + 1;
            $version = $this->storeVersion($document, $file, $disk, $config['folder'], $source, $userId, $nextVersion);

            $document->update([
                'display_name' => $this->naming->buildDisplayName($document->reference, $extension, $document->sequence),
                'current_version_id' => $version->id,
                'source' => $source,
                'uploaded_by' => $userId,
                'imported_at' => now(),
            ]);

            $record = $document->documentable;
            if ($record instanceof Model) {
                $this->syncLegacyField($record, $config['legacy_field'] ?? null, $version);
            }

            return $document->fresh(['currentVersion', 'versions', 'uploader']);
        });
    }

    /**
     * Retire la pièce jointe sélectionnée sans supprimer l’enregistrement métier.
     * Le fichier et ses versions restent consultables dans l’historique.
     */
    public function deactivate(ManagedDocument $document, array $options = []): ManagedDocument
    {
        if (! $document->is_active) {
            return $document;
        }

        $userId = $options['user_id'] ?? auth()->id();

        return DB::transaction(function () use ($document, $userId) {
            $current = $document->currentVersion;
            $nextVersion = ((int) $document->versions()->max('version_number')) + 1;

            if ($current) {
                ManagedDocumentVersion::create([
                    'managed_document_id' => $document->id,
                    'version_number' => $nextVersion,
                    'disk' => $current->disk,
                    'path' => $current->path,
                    'original_name' => $current->original_name,
                    'mime_type' => $current->mime_type,
                    'size' => $current->size,
                    'checksum' => $current->checksum,
                    'source' => 'deletion',
                    'uploaded_by' => $userId,
                ]);
            }

            $document->update([
                'is_active' => false,
                'deleted_at' => now(),
                'deleted_by' => $userId,
            ]);

            $this->resyncLegacyField($document);

            return $document->fresh(['currentVersion', 'versions', 'uploader', 'deletedBy']);
        });
    }

    public function ingestLegacyIfNeeded(string $sectionKey, Model $record): ?ManagedDocument
    {
        $existing = $this->listFor($sectionKey, $record);
        if ($existing->isNotEmpty()) {
            return $existing->first();
        }

        $config = DocumentAttachmentRegistry::get($sectionKey);
        $legacyField = $config['legacy_field'] ?? null;
        if (! $legacyField) {
            return null;
        }

        $path = $record->getAttribute($legacyField);
        if (! is_string($path) || $path === '' || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $absolute = Storage::disk('public')->path($path);
        $uploaded = new UploadedFile(
            $absolute,
            basename($path),
            mime_content_type($absolute) ?: null,
            null,
            true
        );

        return $this->store($sectionKey, $record, $uploaded, [
            'source' => 'upload',
        ]);
    }

    public function latestFor(string $sectionKey, Model $record, string $category = 'primary'): ?ManagedDocument
    {
        return ManagedDocument::query()
            ->where('section_key', $sectionKey)
            ->where('documentable_type', $record->getMorphClass())
            ->where('documentable_id', $record->getKey())
            ->where('category', $category)
            ->where('is_active', true)
            ->with(['currentVersion', 'uploader'])
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return \Illuminate\Support\Collection<int, ManagedDocument>
     */
    public function listFor(string $sectionKey, Model $record)
    {
        return ManagedDocument::query()
            ->where('section_key', $sectionKey)
            ->where('documentable_type', $record->getMorphClass())
            ->where('documentable_id', $record->getKey())
            ->where('is_active', true)
            ->with(['currentVersion', 'uploader', 'versions'])
            ->orderBy('sequence')
            ->orderBy('id')
            ->get();
    }

    public function hasAttachment(string $sectionKey, Model $record, string $category = 'primary'): bool
    {
        return ManagedDocument::query()
            ->where('section_key', $sectionKey)
            ->where('documentable_type', $record->getMorphClass())
            ->where('documentable_id', $record->getKey())
            ->where('category', $category)
            ->where('is_active', true)
            ->whereNotNull('current_version_id')
            ->exists();
    }

    protected function storeVersion(
        ManagedDocument $document,
        UploadedFile $file,
        string $disk,
        string $folder,
        string $source,
        ?int $userId,
        int $versionNumber
    ): ManagedDocumentVersion {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $storedName = Str::uuid()->toString().'.'.$extension;
        $path = $file->storeAs(
            trim($folder, '/').'/'.$document->id,
            $storedName,
            $disk
        );

        return ManagedDocumentVersion::create([
            'managed_document_id' => $document->id,
            'version_number' => $versionNumber,
            'disk' => $disk,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'checksum' => hash_file('sha256', $file->getRealPath() ?: $file->getPathname()),
            'source' => $source,
            'uploaded_by' => $userId,
        ]);
    }

    protected function syncLegacyField(Model $record, ?string $legacyField, ManagedDocumentVersion $version): void
    {
        if (! $legacyField || ! in_array($legacyField, $record->getFillable(), true)) {
            return;
        }

        // Keep a public mirror for older views that still read *_file_path.
        $binary = Storage::disk($version->disk)->get($version->path);
        $mirrorRelative = 'legacy-mirrors/'.$version->managed_document_id.'/v'.$version->version_number.'-'.basename($version->path);
        Storage::disk('public')->put($mirrorRelative, $binary);

        $old = $record->getAttribute($legacyField);
        if ($old && $old !== $mirrorRelative) {
            Storage::disk('public')->delete($old);
        }

        $record->update([$legacyField => $mirrorRelative]);
    }

    protected function resyncLegacyField(ManagedDocument $document): void
    {
        $config = DocumentAttachmentRegistry::get($document->section_key);
        $legacyField = $config['legacy_field'] ?? null;
        $record = $document->documentable;

        if (! $legacyField || ! $record instanceof Model || ! in_array($legacyField, $record->getFillable(), true)) {
            return;
        }

        $remaining = $this->latestFor($document->section_key, $record, $document->category);
        if ($remaining?->currentVersion) {
            $this->syncLegacyField($record, $legacyField, $remaining->currentVersion);

            return;
        }

        $old = $record->getAttribute($legacyField);
        if ($old) {
            Storage::disk('public')->delete($old);
        }

        $record->update([$legacyField => null]);
    }
}
