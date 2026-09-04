<?php

namespace App\Services\Access;

use App\Models\Collaborator;
use App\Models\CommercialReassignment;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CommercialAttributionService
{
    /**
     * Resolve commercial collaborator for a new document.
     * Prefer explicit form value; else connected user's collaborator if commercial.
     */
    public function resolveForCreate(?int $collaboratorId = null, ?User $actor = null): ?int
    {
        if ($collaboratorId) {
            $exists = Collaborator::query()
                ->where('id', $collaboratorId)
                ->where('status', Collaborator::STATUS_ACTIF)
                ->exists();

            return $exists ? $collaboratorId : null;
        }

        $actor = $actor ?? Auth::user();
        if (! $actor?->collaborator_id) {
            return null;
        }

        $collaborator = Collaborator::query()->find($actor->collaborator_id);
        if ($collaborator && $collaborator->status === Collaborator::STATUS_ACTIF) {
            return $collaborator->id;
        }

        return null;
    }

    public function actorUserId(?User $actor = null): ?int
    {
        return ($actor ?? Auth::user())?->id;
    }

    /**
     * Attributes to merge into create payloads.
     *
     * @return array{collaborator_id: ?int, created_by_user_id: ?int}
     */
    public function createAttributes(?int $collaboratorId = null, ?User $actor = null): array
    {
        return [
            'collaborator_id' => $this->resolveForCreate($collaboratorId, $actor),
            'created_by_user_id' => $this->actorUserId($actor),
        ];
    }

    /**
     * Reassign commercial without erasing history.
     */
    public function reassign(
        Model $document,
        int $toCollaboratorId,
        ?string $reason = null,
        ?User $actor = null,
    ): CommercialReassignment {
        $actor = $actor ?? Auth::user();
        $fromId = $document->collaborator_id ? (int) $document->collaborator_id : null;

        if ($fromId === $toCollaboratorId) {
            throw new \RuntimeException('Le commercial sélectionné est déjà attribué.');
        }

        $to = Collaborator::query()->findOrFail($toCollaboratorId);

        $ref = $this->documentRef($document);

        $log = CommercialReassignment::query()->create([
            'document_type' => $document::class,
            'document_id' => $document->getKey(),
            'document_ref' => $ref,
            'from_collaborator_id' => $fromId,
            'to_collaborator_id' => $to->id,
            'changed_by' => $actor?->id,
            'reason' => $reason,
        ]);

        $document->update(['collaborator_id' => $to->id]);

        ActivityLogger::log(
            'changement_commercial',
            "Réattribution commercial {$ref} → {$to->fullName()}",
            $document,
            ['collaborator_id' => $fromId],
            ['collaborator_id' => $to->id, 'reason' => $reason],
            $ref,
            $actor,
        );

        return $log;
    }

    /**
     * Propagate commercial from source documents (first non-null wins).
     *
     * @param  iterable<Model>  $sources
     */
    public function collaboratorIdFromSources(iterable $sources): ?int
    {
        foreach ($sources as $source) {
            if (! empty($source->collaborator_id)) {
                return (int) $source->collaborator_id;
            }
        }

        return null;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Collaborator>
     */
    public function commercialOptions()
    {
        return Collaborator::query()
            ->where('status', Collaborator::STATUS_ACTIF)
            ->where(function ($q) {
                $q->where('is_commercial', true)
                    ->orWhere('type', Collaborator::TYPE_FREELANCE);
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    public function documentRef(Model $document): string
    {
        foreach (['quote_number', 'reference', 'delivery_number', 'invoice_number', 'credit_note_number', 'number'] as $field) {
            if (! empty($document->{$field})) {
                return (string) $document->{$field};
            }
        }

        return class_basename($document).'#'.$document->getKey();
    }
}
