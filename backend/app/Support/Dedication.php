<?php

namespace App\Support;

use App\Enums\ModerationStatus;
use App\Models\Tenant;

/**
 * The dedication of one copy as the shop allows it, from the storefront or the admin.
 */
class Dedication
{
    /**
     * Options the shop doesn't offer are dropped (e.g. a draft started before
     * the setting changed).
     *
     * @param  array<string, mixed>  $copy  signature_only, recipient_name, wish
     * @return array{signature_only: bool, recipient_name: ?string, wish: ?string}
     */
    public static function normalize(Tenant $tenant, array $copy): array
    {
        $signatureOnly = (bool) ($copy['signature_only'] ?? false) && $tenant->setting('dedication_signature_only');

        return [
            'signature_only' => $signatureOnly,
            'recipient_name' => $signatureOnly ? null : (trim((string) ($copy['recipient_name'] ?? '')) ?: null),
            'wish' => $signatureOnly || ! $tenant->setting('dedication_wish') ? null : (trim((string) ($copy['wish'] ?? '')) ?: null),
        ];
    }

    /**
     * Anything customers wrote gets reviewed; plain signatures don't need it,
     * names without a wish only if the shop says so.
     *
     * @param  array{recipient_name: ?string, wish: ?string}  $dedication
     */
    public static function moderationStatus(Tenant $tenant, array $dedication): ModerationStatus
    {
        $autoApproved = $dedication['wish'] === null
            && ($dedication['recipient_name'] === null || $tenant->setting('dedication_auto_approve_names'));

        return $autoApproved ? ModerationStatus::Approved : ModerationStatus::Pending;
    }
}
