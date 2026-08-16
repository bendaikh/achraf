@props(['type', 'id', 'label' => 'Ajouter un document', 'category' => 'primary'])

<x-managed-document-actions :type="$type" :id="$id" :label="$label" :category="$category" />
