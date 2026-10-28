<?php

namespace App\Services;

use App\Enums\ModerationStatus;
use App\Models\OrderItem;
use App\Models\User;

/**
 * Checking what customers asked to have written: approve, adjust or flag
 * with a query. Used by the table and the one-by-one review.
 */
class ModerationService
{
    public function approve(OrderItem $item, ?User $by, ?string $note = null): void
    {
        $item->moderation_status = ModerationStatus::Approved;
        $item->moderation_note = $note;
        $item->save();
        $item->order->log('moderation_approved', array_filter(['item' => $item->id, 'note' => $note]), user: $by);
    }

    /**
     * Save a corrected dedication; it counts as approved.
     *
     * @param  array{recipient_name?: ?string, wish?: ?string}  $data
     */
    public function adjust(OrderItem $item, array $data, ?User $by): void
    {
        $item->fill([
            'recipient_name' => trim((string) ($data['recipient_name'] ?? '')) ?: null,
            'wish' => trim((string) ($data['wish'] ?? '')) ?: null,
        ]);
        $this->approve($item, $by, 'Angepasst');
    }

    /**
     * Mark for a query. The order still gets its post-it number; the copy is
     * put aside until the query is resolved.
     */
    public function flag(OrderItem $item, ?User $by, ?string $note): void
    {
        $item->update(['moderation_status' => ModerationStatus::Flagged, 'moderation_note' => $note ?: null, 'flagged_at' => now()]);
        $item->order->log('moderation_flagged', ['item' => $item->id, 'note' => $note ?: null], user: $by);
    }
}
