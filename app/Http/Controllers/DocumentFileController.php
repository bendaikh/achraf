<?php

namespace App\Http\Controllers;

use App\Services\Documents\DocumentAttachmentService;
use App\Support\DocumentAttachmentRegistry;
use Illuminate\Http\Request;

class DocumentFileController extends Controller
{
    public function __construct(
        protected DocumentAttachmentService $attachments
    ) {}

    public function store(Request $request, string $type, int $id)
    {
        if (! DocumentAttachmentRegistry::exists($type)) {
            abort(404);
        }

        $config = DocumentAttachmentRegistry::get($type);
        if (($config['allow_attach'] ?? false) !== true) {
            abort(403);
        }

        $request->validate([
            'document_file' => 'required|file|mimes:'.implode(',', config('managed_documents.allowed_mimes')).'|max:'.config('managed_documents.max_kilobytes'),
            'category' => 'nullable|string|max:64',
            'source' => 'nullable|in:upload,scan',
        ]);

        $record = DocumentAttachmentRegistry::resolveRecord($type, $id);
        $category = $request->input('category', 'primary');

        $this->attachments->store($type, $record, $request->file('document_file'), [
            'category' => $category,
            'source' => $request->input('source', 'upload'),
            'user_id' => $request->user()?->id,
        ]);

        return redirect()
            ->to(url()->previous())
            ->with('success', 'Document enregistré avec succès.');
    }
}
