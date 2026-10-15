<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AktionStatus: string implements HasLabel
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Open = 'open';
    case Closed = 'closed';
    case Signing = 'signing';
    case Shipping = 'shipping';
    case Done = 'done';

    public function getLabel(): string
    {
        return __("enums.AktionStatus.{$this->value}");
    }
}
