@props(['type', 'id', 'label' => 'Importer'])

<form action="{{ route('document-files.store', ['type' => $type, 'id' => $id]) }}" method="POST" enctype="multipart/form-data" class="inline">
    @csrf
    <label class="text-indigo-600 hover:text-indigo-900 cursor-pointer" title="{{ $label }}">
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
        </svg>
        <input type="file" name="document_file" accept=".pdf,.jpg,.jpeg,.png" class="hidden" onchange="this.form.submit()">
    </label>
</form>
