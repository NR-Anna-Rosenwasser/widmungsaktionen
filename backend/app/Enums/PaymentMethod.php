<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PaymentMethod: string implements HasLabel
{
    case Online = 'online';
    case InvoiceAdvance = 'invoice_advance';
    case InvoiceInParcel = 'invoice_in_parcel';

    public function getLabel(): string
    {
        return __("enums.PaymentMethod.{$this->value}");
    }
}
