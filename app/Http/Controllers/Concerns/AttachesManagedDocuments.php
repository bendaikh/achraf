<?php

namespace App\Http\Controllers\Concerns;

use App\Services\Documents\DocumentAttachmentService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;

trait AttachesManagedDocuments
{
    protected function attachManagedDocument(
        string $sectionKey,
        Model $record,
        ?UploadedFile $file,
        array $options = []
    ): void {
        if (! $file) {
            return;
        }

        app(DocumentAttachmentService::class)->store($sectionKey, $record, $file, array_merge([
            'user_id' => auth()->id(),
        ], $options));
    }
}
