<?php

namespace App\Services\Pdf;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentState;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\PaymentReference;
use App\Support\Texts\Texts;
use Illuminate\Support\Carbon;
use RuntimeException;
use Sprain\SwissQrBill\DataGroup\Element\AdditionalInformation;
use Sprain\SwissQrBill\DataGroup\Element\CreditorInformation;
use Sprain\SwissQrBill\DataGroup\Element\PaymentAmountInformation;
use Sprain\SwissQrBill\DataGroup\Element\PaymentReference as QrPaymentReference;
use Sprain\SwissQrBill\DataGroup\Element\StructuredAddress;
use Sprain\SwissQrBill\PaymentPart\Output\DisplayOptions;
use Sprain\SwissQrBill\PaymentPart\Output\HtmlOutput\HtmlOutput;
use Sprain\SwissQrBill\QrBill;

/**
 * Invoice letter with Swiss QR payment part.
 */
class InvoicePdf
{
    public function __construct(protected PdfRenderer $renderer) {}

    /**
     * View data for one invoice page (also used inside the cover letters PDF).
     */
    public function pageData(Order $order): array
    {
        $order->loadMissing('tenant', 'aktion', 'items.book');
        $payment = $this->invoicePayment($order);
        $locale = $order->language();

        return [
            'order' => $order,
            'tenant' => $order->tenant,
            'payment' => $payment,
            'locale' => $locale,
            'text' => app(Texts::class)->for($order->tenant, $order->aktion, $locale),
            'dueDate' => $this->dueDate($order, $payment),
            'reference' => PaymentReference::format($payment->reference),
            'paymentPart' => $this->paymentPart($order, $payment, $locale),
            'envelope' => $order->tenant->setting('print.envelope_mode'),
            'cancelled' => $order->status === OrderStatus::Cancelled,
        ];
    }

    public function html(Order $order): string
    {
        return view('pdf.invoice', $this->pageData($order))->render();
    }

    public function render(Order $order): string
    {
        return $this->renderer->render($this->html($order));
    }

    public function filename(Order $order): string
    {
        return __('invoice.filename', [], $order->language()).'-'.$order->number.'.pdf';
    }

    /**
     * The open (or settled) invoice payment of the order.
     */
    public function invoicePayment(Order $order): Payment
    {
        $payment = $order->payments()
            ->whereIn('method', [PaymentMethod::InvoiceAdvance, PaymentMethod::InvoiceInParcel])
            ->whereNotNull('reference')
            ->orderByRaw('case when status = ? then 0 else 1 end', [PaymentState::Pending->value])
            ->latest()
            ->first();

        if ($payment) {
            return $payment;
        }

        if (! in_array($order->payment_method, [PaymentMethod::InvoiceAdvance, PaymentMethod::InvoiceInParcel], true)) {
            throw new RuntimeException("Order {$order->id} is not paid by invoice.");
        }

        return $order->payments()->create([
            'method' => $order->payment_method,
            'amount' => $order->total,
            'status' => PaymentState::Pending,
            'reference' => PaymentReference::for($order),
        ]);
    }

    protected function dueDate(Order $order, Payment $payment): Carbon
    {
        // Pay-in-advance invoices are due before the order deadline.
        if ($payment->method === PaymentMethod::InvoiceAdvance) {
            return $order->aktion->order_deadline->copy();
        }

        // Invoices in the parcel are due some days after the book has shipped.
        $shipped = $order->shipments()->latest('shipped_at')->value('shipped_at');
        $from = $shipped ? Carbon::parse($shipped) : $order->aktion->estimated_shipping_date->copy();

        return $from->addDays((int) $order->tenant->setting('invoice_payment_days'));
    }

    protected function paymentPart(Order $order, Payment $payment, string $locale): string
    {
        $tenant = $order->tenant;

        $bill = QrBill::create();
        $bill->setCreditor(StructuredAddress::createWithStreet(
            $tenant->creditor_name,
            $tenant->creditor_street,
            $tenant->creditor_building_number,
            $tenant->creditor_postal_code,
            $tenant->creditor_town,
            $tenant->creditor_country,
        ));
        $bill->setCreditorInformation(CreditorInformation::create($tenant->iban));
        $bill->setPaymentAmountInformation(PaymentAmountInformation::create('CHF', $payment->amount / 100));
        $bill->setPaymentReference(QrPaymentReference::create(PaymentReference::typeFor($order), $payment->reference));
        $bill->setAdditionalInformation(AdditionalInformation::create(
            __('invoice.order', [], $locale).' '.$order->number,
        ));
        $bill->setUltimateDebtor(StructuredAddress::createWithStreet(
            mb_substr($order->billingName(), 0, 70),
            mb_substr((string) $order->billing_street, 0, 70),
            null,
            (string) $order->billing_postal_code,
            mb_substr((string) $order->billing_town, 0, 35),
            $order->billing_country ?: 'CH',
        ));

        if (! $bill->isValid()) {
            $errors = collect($bill->getViolations())->map(fn ($v) => $v->getPropertyPath().': '.$v->getMessage())->implode('; ');
            throw new RuntimeException("Invalid QR bill for order {$order->id}: $errors");
        }

        $output = new HtmlOutput($bill, $locale);
        $output->setDisplayOptions((new DisplayOptions)->setPrintable(false)->setDisplayScissors(true));

        return $output->getPaymentPart();
    }
}
