<?php

namespace App\Services\Documents;

use App\Models\ManagedDocument;
use Illuminate\Support\Str;

class DocumentNamingService
{
    public function sanitizeReference(string $reference): string
    {
        $reference = trim($reference);
        $reference = preg_replace('/\s+/', '-', $reference) ?? $reference;
        $reference = preg_replace('/[^A-Za-z0-9._\-]+/', '-', $reference) ?? $reference;
        $reference = trim($reference, '.-_');

        return $reference !== '' ? Str::upper($reference) : 'DOC';
    }

    public function nextSequence(string $sectionKey, string $reference, ?int $excludeDocumentId = null): int
    {
        $safeReference = $this->sanitizeReference($reference);

        $query = ManagedDocument::query()
            ->where('section_key', $sectionKey)
            ->where('reference', $safeReference)
            ->where('is_active', true);

        if ($excludeDocumentId) {
            $query->where('id', '!=', $excludeDocumentId);
        }

        $count = $query->count();

        return $count + 1;
    }

    public function buildDisplayName(string $reference, string $extension, int $sequence = 1): string
    {
        $safeReference = $this->sanitizeReference($reference);
        $extension = ltrim(strtolower($extension), '.');

        if ($extension === 'jpeg') {
            $extension = 'jpg';
        }

        if ($sequence <= 1) {
            return "{$safeReference}.{$extension}";
        }

        $suffix = str_pad((string) $sequence, 2, '0', STR_PAD_LEFT);

        return "{$safeReference}_{$suffix}.{$extension}";
    }

    public function exportPrefix(int $index, string $documentDate, string $reference, string $extension): string
    {
        $safeReference = $this->sanitizeReference($reference);
        $extension = ltrim(strtolower($extension), '.');
        $prefix = str_pad((string) $index, 3, '0', STR_PAD_LEFT);

        return "{$prefix}_{$documentDate}_{$safeReference}.{$extension}";
    }
}
