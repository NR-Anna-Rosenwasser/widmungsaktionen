<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ModerationStatus: string implements HasLabel
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Flagged = 'flagged';

    public function getLabel(): string
    {
        return __("enums.ModerationStatus.{$this->value}");
    }
}
