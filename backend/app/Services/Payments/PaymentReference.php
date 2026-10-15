<?php

namespace App\Services\Payments;

use App\Models\Order;
use Sprain\SwissQrBill\DataGroup\Element\CreditorInformation;
use Sprain\SwissQrBill\DataGroup\Element\PaymentReference as QrPaymentReference;
use Sprain\SwissQrBill\Reference\QrPaymentReferenceGenerator;
use Sprain\SwissQrBill\Reference\RfCreditorReferenceGenerator;

/**
 * Structured payment references for QR bills, so bank payments can be
 * matched to orders: QRR with a QR-IBAN, SCOR (RF…) with a normal IBAN.
 */
class PaymentReference
{
    public static function usesQrIban(?string $iban): bool
    {
        return $iban !== null && CreditorInformation::create($iban)->containsQrIban();
    }

    public static function typeFor(Order $order): string
    {
        return self::usesQrIban($order->tenant->iban) ? QrPaymentReference::TYPE_QR : QrPaymentReference::TYPE_SCOR;
    }

    public static function for(Order $order): string
    {
        if (self::typeFor($order) === QrPaymentReference::TYPE_QR) {
            // Tenant id + order id, numeric only.
            return QrPaymentReferenceGenerator::generate(null, sprintf('%04d%010d', $order->tenant_id, $order->id));
        }

        // e.g. AR-2026-0042 → RF…AR20260042
        return RfCreditorReferenceGenerator::generate(preg_replace('/[^A-Z0-9]/', '', strtoupper($order->number)));
    }

    /**
     * Human-friendly formatting in blocks of four / five (as printed on the bill).
     */
    public static function format(string $reference): string
    {
        return str_starts_with($reference, 'RF')
            ? trim(chunk_split($reference, 4, ' '))
            : trim(substr($reference, 0, 2).' '.chunk_split(substr($reference, 2), 5, ' '));
    }
}
