<?php

namespace App\Services\Pdf;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Aktion;
use App\Models\Order;
use App\Services\ShippingService;
use App\Services\SigningService;
use App\Support\Texts\Texts;
use Endroid\QrCode\Builder\Builder as QrBuilder;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Print documents for an Aktion, always in stack (post-it) order.
 */
class PrintService
{
    public function __construct(
        protected PdfRenderer $renderer,
        protected InvoicePdf $invoices,
        protected ShippingService $shipping,
    ) {}

    /**
     * Orders on the stack (not cancelled), in post-it order.
     *
     * @return Collection<int, Order>
     */
    public function orders(Aktion $aktion): Collection
    {
        return $this->stacked($aktion->orders())->orderBy('stack_number')->get();
    }

    public function needsInvoicePage(Order $order): bool
    {
        return $order->payment_method === PaymentMethod::InvoiceInParcel
            && $order->payment_status === PaymentStatus::Unpaid
            && ! $order->is_gift;
    }

    /**
     * Which orders get an address label, depending on the envelope mode:
     * parcels always; letters only with plain envelopes.
     *
     * @return Collection<int, Order>
     */
    public function ordersNeedingLabels(Aktion $aktion): Collection
    {
        return $this->orders($aktion)
            ->filter(fn (Order $order) => $this->needsLabel($order))
            ->values();
    }

    /**
     * @return Collection<int, Order>
     */
    public function ordersForEnvelopes(Aktion $aktion): Collection
    {
        return $this->orders($aktion)
            ->filter(fn (Order $order) => $this->isLetter($order))
            ->values();
    }

    /**
     * Whether the order goes as a letter (fits a C5 envelope), not as a parcel.
     */
    public function isLetter(Order $order): bool
    {
        return $this->shipping->methodFor($order) === 'letter';
    }

    /**
     * A single order on the stack (e.g. a late order), with what the
     * documents need, or null if it isn't on the stack (anymore).
     */
    public function stackedOrder(Order $order): ?Order
    {
        return $this->stacked(Order::query()->whereKey($order->id))->first();
    }

    /**
     * Orders on the stack (not cancelled), with what the documents need.
     *
     * @param  Builder<Order>|HasMany<Order, Aktion>  $query
     * @return Builder<Order>|HasMany<Order, Aktion>
     */
    protected function stacked(Builder|HasMany $query): Builder|HasMany
    {
        return $query
            ->whereNotNull('stack_number')
            ->whereNotIn('status', [OrderStatus::Cancelled, OrderStatus::Draft])
            ->with('tenant', 'aktion', 'items.book', 'shipments');
    }

    /**
     * Whether the order gets an address label in the current envelope mode.
     */
    public function needsLabel(Order $order): bool
    {
        return $order->tenant->setting('print.envelope_mode') === 'labels'
            || $this->shipping->methodFor($order) === 'parcel';
    }

    /**
     * Labels per sheet in the configured layout.
     */
    public function labelsPerSheet(Aktion $aktion): int
    {
        $sheet = $this->labelSheet($aktion);

        return max(1, (int) ($sheet['cols'] * $sheet['rows']));
    }

    /**
     * @param  Collection<int, Order>|null  $orders  Only these (default: the whole stack)
     */
    public function lettersHtml(Aktion $aktion, ?Collection $orders = null): string
    {
        $tenant = $aktion->tenant;
        $pages = ($orders ?? $this->orders($aktion))->map(fn (Order $order) => [
            'order' => $order,
            'qr' => $tenant->setting('print.letter_qr') && ! $order->is_gift ? $this->qr($order->storefrontUrl()) : null,
            'invoice' => $this->needsInvoicePage($order) ? $this->invoices->pageData($order) : null,
            'text' => app(Texts::class)->for($tenant, $aktion, $order->locale),
        ]);

        return view('pdf.letters', [
            'tenant' => $tenant,
            'pages' => $pages,
            'envelope' => $tenant->setting('print.envelope_mode'),
        ])->render();
    }

    /**
     * @param  Collection<int, Order>|null  $orders  Only these (default: all orders needing a label)
     */
    public function labelsHtml(Aktion $aktion, int $skip = 0, ?Collection $orders = null): string
    {
        return view('pdf.labels', [
            'orders' => $orders ?? $this->ordersNeedingLabels($aktion),
            'sheet' => $this->labelSheet($aktion),
            'skip' => max(0, $skip),
            'sender' => $this->senderLine($aktion),
        ])->render();
    }

    /**
     * @param  Collection<int, Order>|null  $orders  Only these (default: all letters)
     */
    public function envelopesHtml(Aktion $aktion, ?Collection $orders = null): string
    {
        return view('pdf.envelopes', [
            'orders' => $orders ?? $this->ordersForEnvelopes($aktion),
            'tenant' => $aktion->tenant,
        ])->render();
    }

    public function stackListHtml(Aktion $aktion): string
    {
        $orders = $this->orders($aktion);

        return view('pdf.stack-list', [
            'aktion' => $aktion,
            'orders' => $orders,
            'books' => app(SigningService::class)->bookCounts($orders),
            'shipping' => $this->shipping,
            'needsInvoice' => fn (Order $order) => $this->needsInvoicePage($order),
        ])->render();
    }

    public function render(string $html): string
    {
        return $this->renderer->render($html);
    }

    /**
     * @return array{cols: int, rows: int, width: float, height: float, top: float, left: float, gap_x: float, gap_y: float}
     */
    public function labelSheet(Aktion $aktion): array
    {
        $preset = $aktion->tenant->setting('print.label_preset');
        $sheet = $preset === 'custom'
            ? $aktion->tenant->setting('print.label_custom')
            : (config("widmungen.label_presets.$preset") ?? config('widmungen.label_presets.avery_3475'));

        return collect($sheet)->only(['cols', 'rows', 'width', 'height', 'top', 'left', 'gap_x', 'gap_y'])
            ->map(fn ($value) => (float) $value)
            ->all();
    }

    protected function senderLine(Aktion $aktion): string
    {
        $tenant = $aktion->tenant;

        return collect([$tenant->creditor_name, trim($tenant->creditor_street.' '.$tenant->creditor_building_number), trim($tenant->creditor_postal_code.' '.$tenant->creditor_town)])
            ->filter()
            ->implode(', ');
    }

    protected function qr(string $url): string
    {
        return (new QrBuilder(
            writer: new SvgWriter,
            data: $url,
            size: 240,
            margin: 0,
            foregroundColor: new Color(43, 60, 105),
        ))->build()->getDataUri();
    }
}
