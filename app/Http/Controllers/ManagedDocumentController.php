<?php

namespace App\Http\Controllers;

use App\Models\ManagedDocument;
use App\Services\Documents\DocumentAttachmentService;
use App\Support\DocumentAttachmentRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ManagedDocumentController extends Controller
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
            abort(403, 'Ajout de document non autorisé pour cette section.');
        }

        $validated = $request->validate([
            'document_file' => 'required|file|mimes:'.implode(',', config('managed_documents.allowed_mimes')).'|max:'.config('managed_documents.max_kilobytes'),
            'category' => 'nullable|string|max:64',
            'source' => 'nullable|in:upload,scan',
            'redirect_to' => 'nullable|string',
        ]);

        $record = DocumentAttachmentRegistry::resolveRecord($type, $id);
        $category = $validated['category'] ?? 'primary';

        if (! array_key_exists($category, $config['categories'] ?? ['primary' => 'Document'])) {
            abort(422, 'Catégorie de document invalide.');
        }

        $this->attachments->store($type, $record, $request->file('document_file'), [
            'category' => $category,
            'source' => $validated['source'] ?? 'upload',
            'user_id' => $request->user()?->id,
        ]);

        return redirect()
            ->to($validated['redirect_to'] ?? url()->previous())
            ->with('success', 'Document enregistré avec succès.');
    }

    public function replace(Request $request, ManagedDocument $managedDocument)
    {
        abort_unless($managedDocument->is_active, 404);

        $validated = $request->validate([
            'document_file' => 'required|file|mimes:'.implode(',', config('managed_documents.allowed_mimes')).'|max:'.config('managed_documents.max_kilobytes'),
            'source' => 'nullable|in:upload,scan',
            'redirect_to' => 'nullable|string',
        ]);

        $this->attachments->replace($managedDocument, $request->file('document_file'), [
            'source' => $validated['source'] ?? 'upload',
            'user_id' => $request->user()?->id,
        ]);

        return redirect()
            ->to($validated['redirect_to'] ?? url()->previous())
            ->with('success', 'Document remplacé. L’ancienne version a été conservée dans l’historique.');
    }

    public function destroy(Request $request, ManagedDocument $managedDocument)
    {
        $validated = $request->validate([
            'redirect_to' => 'nullable|string',
        ]);

        abort_unless($managedDocument->is_active, 404);

        $this->attachments->deactivate($managedDocument, [
            'user_id' => $request->user()?->id,
        ]);

        return redirect()
            ->to($validated['redirect_to'] ?? url()->previous())
            ->with('success', 'Pièce jointe supprimée. L’enregistrement n’a pas été modifié.');
    }

    public function show(ManagedDocument $managedDocument): StreamedResponse
    {
        return $this->stream($managedDocument, false);
    }

    public function download(ManagedDocument $managedDocument): StreamedResponse
    {
        return $this->stream($managedDocument, true);
    }

    public function history(ManagedDocument $managedDocument)
    {
        $managedDocument->load(['versions.uploader', 'uploader', 'deletedBy', 'documentable']);

        return view('documents.managed.history', [
            'document' => $managedDocument,
        ]);
    }

    protected function stream(ManagedDocument $managedDocument, bool $forceDownload): StreamedResponse
    {
        $version = $managedDocument->currentVersion;
        abort_unless($version && $version->existsOnDisk(), 404);

        $disposition = $forceDownload ? 'attachment' : 'inline';

        return Storage::disk($version->disk)->response(
            $version->path,
            $managedDocument->downloadName(),
            [
                'Content-Type' => $version->mime_type ?: 'application/octet-stream',
            ],
            $disposition
        );
    }
}
