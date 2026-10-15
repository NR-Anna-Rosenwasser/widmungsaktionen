<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TenantRole: string implements HasLabel
{
    case Admin = 'admin';
    case Fulfillment = 'fulfillment';
    case Widmen = 'widmen';

    public function getLabel(): string
    {
        return __("enums.TenantRole.{$this->value}");
    }
}
