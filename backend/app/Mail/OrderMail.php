<?php

namespace App\Mail;

use App\Models\Order;
use App\Services\Payments\PaymentService;
use App\Services\Pdf\InvoicePdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Base for customer emails about an order: sent from the shop's own sender,
 * in the customer's language, with the shop's name in header and footer.
 */
abstract class OrderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order)
    {
        $this->locale($order->locale === 'en' ? 'en' : 'de');
    }

    /**
     * Translation key below "mail." for this mail, e.g. "confirmation".
     */
    abstract protected function key(): string;

    protected function markdownView(): string
    {
        return 'mail.'.str_replace('_', '-', $this->key());
    }

    public function envelope(): Envelope
    {
        $tenant = $this->order->tenant;
        $from = $tenant->mail_from_address ?: config('mail.from.address');
        $replyTo = $tenant->mail_reply_to ?: $tenant->contact_email;

        return new Envelope(
            from: new Address($from, $tenant->mail_from_name ?: $tenant->name),
            replyTo: $replyTo ? [new Address($replyTo)] : [],
            subject: __("mail.{$this->key()}.subject", ['number' => $this->order->number], $this->locale),
        );
    }

    public function content(): Content
    {
        $this->order->loadMissing('tenant', 'aktion', 'items.book');

        return new Content(markdown: $this->markdownView(), with: [
            'order' => $this->order,
            'tenant' => $this->order->tenant,
            'shopName' => $this->order->tenant->name,
            'shopUrl' => $this->order->tenant->storefrontUrl(),
            'trackingUrl' => $this->order->storefrontUrl(),
            'canPayOnline' => app(PaymentService::class)->canPayOnline($this->order),
            ...$this->data(),
        ]);
    }

    /**
     * Extra view data for the concrete mail.
     */
    protected function data(): array
    {
        return [];
    }

    /**
     * The QR invoice as attachment (rendered when the queued mail is sent).
     */
    protected function invoiceAttachment(): Attachment
    {
        $invoices = app(InvoicePdf::class);

        return Attachment::fromData(fn () => $invoices->render($this->order), $invoices->filename($this->order))
            ->withMime('application/pdf');
    }
}
