<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * State of a single payment attempt (an order can have several, e.g. an
 * invoice that was later paid online).
 */
enum PaymentState: string implements HasLabel
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return __("enums.PaymentState.{$this->value}");
    }
}
