<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum OrderStatus: string implements HasLabel
{
    case Draft = 'draft';
    case PendingPayment = 'pending_payment';
    case Confirmed = 'confirmed';
    case Signed = 'signed';
    case Shipped = 'shipped';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return __("enums.OrderStatus.{$this->value}");
    }
}
