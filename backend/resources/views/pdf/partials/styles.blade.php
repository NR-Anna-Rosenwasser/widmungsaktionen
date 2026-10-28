    @font-face { font-family: 'Barlow'; font-weight: 600; src: url('barlow-latin-600-normal.woff2'); }
    @font-face { font-family: 'Barlow'; font-weight: 700; src: url('barlow-latin-700-normal.woff2'); }
    @font-face { font-family: 'Hanken Grotesk'; font-weight: 400; src: url('hanken-grotesk-latin-400-normal.woff2'); }
    @font-face { font-family: 'Hanken Grotesk'; font-weight: 600; src: url('hanken-grotesk-latin-600-normal.woff2'); }

    @page { size: A4; margin: 0; }
    * { box-sizing: border-box; }
    html, body { margin: 0; padding: 0; }
    body { font-family: 'Hanken Grotesk', Arial, sans-serif; font-size: 10pt; line-height: 1.45; color: #2B3C69; }
    /* Slightly below 297mm: an exact A4 height plus a forced break makes Chromium clip the bottom (payment part). */
    .page { position: relative; width: 210mm; height: 296.5mm; overflow: hidden; break-after: page; }
    .page:last-child { break-after: auto; }
    .sender { position: absolute; top: 15mm; left: 22mm; width: 85mm; font-size: 8.5pt; white-space: pre-line; color: #4a5a85; }
    .address { position: absolute; top: 45mm; width: 80mm; font-size: 10.5pt; }
    .address.window-left { left: 22mm; }
    .address.window-right { left: 118mm; }
    .meta { position: absolute; top: 78mm; left: 22mm; right: 20mm; }
    p { margin: 0 0 2.5mm; }
    h1 { font-family: 'Barlow', Arial, sans-serif; font-weight: 700; font-size: 18pt; margin: 0 0 3mm; letter-spacing: -0.01em; }
    .date { text-align: right; margin-bottom: 4mm; }
    /* Scoped: the QR payment part brings its own table markup. */
    table.items { width: 100%; border-collapse: collapse; margin-top: 4mm; }
    .items th { text-align: left; font-family: 'Barlow', Arial, sans-serif; font-weight: 600; border-bottom: 1.5pt solid #2B3C69; padding: 1.5mm 0; }
    .items td { padding: 1.3mm 0; border-bottom: 0.5pt solid #EEC6D8; vertical-align: top; }
    .items td.amount, .items th.amount { text-align: right; white-space: nowrap; }
    .items tr.total td { font-family: 'Barlow', Arial, sans-serif; font-weight: 700; font-size: 12pt; border-bottom: none; padding-top: 3mm; }
    .muted { color: #4a5a85; font-size: 9pt; }
    .extra { margin-top: 4mm; }
    .facts { margin-top: 4mm; font-size: 9pt; color: #4a5a85; }
    .paid { margin-top: 8mm; font-family: 'Barlow', Arial, sans-serif; font-weight: 700; font-size: 14pt; color: #b8668f; }
    .cancelled { margin-top: 8mm; padding: 4mm 5mm; border: 1.5pt solid #a8324a; border-radius: 1mm; font-family: 'Barlow', Arial, sans-serif; font-weight: 700; font-size: 13pt; color: #a8324a; }
    .cancelled-stamp { position: absolute; bottom: 40mm; left: 0; width: 210mm; text-align: center; font-family: 'Barlow', Arial, sans-serif; font-weight: 700; font-size: 44pt; letter-spacing: 0.08em; color: rgba(168, 50, 74, 0.18); transform: rotate(-8deg); }
    .payment-part { position: absolute; bottom: 0; left: 0; width: 210mm; height: 105mm; }
    .accent { position: absolute; top: 0; left: 0; width: 210mm; height: 5mm; background: #D989B2; }
