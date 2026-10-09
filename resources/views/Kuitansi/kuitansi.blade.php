<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kuitansi {{ $booking->code }} - {{ $business['business_name'] ?? $business['name'] ?? '' }}</title>
    <style>
        @page {
            size: A4;
            margin: 0;
        }

        * {
            box-sizing: border-box;
        }

        html, body {
            margin: 0;
            padding: 0;
            width: 100%;
        }

        body, table, th, td, div, p, span, strong, b, i, h1, h2, button {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #3b2a23;
        }

        /* ---------- Toolbar (layar saja) ---------- */
        .toolbar {
            text-align: center;
            padding: 16px;
        }
        .toolbar button {
            border: 1px solid #e8dccb;
            border-radius: 8px;
            background: #fff;
            color: #3b2a23;
            cursor: pointer;
            font-family: Arial, sans-serif;
            font-size: 12px;
            font-weight: bold;
            padding: 9px 16px;
            margin: 0 4px;
        }
        .toolbar button.primary {
            background: #893e1e;
            border-color: #893e1e;
            color: #fff;
        }

        .page {
            padding: 42px 46px;
            position: relative;
            max-width: 794px;
            margin: 0 auto;
        }

        table { border-collapse: collapse; }
        td { vertical-align: top; padding: 0; }

        /* ---------- Watermark ---------- */
        .watermark {
            position: absolute;
            top: 340px;
            left: 140px;
            font-size: 60px;
            font-weight: bold;
            color: rgba(178, 102, 50, 0.035);
            transform: rotate(-24deg);
            z-index: -1;
            letter-spacing: 6px;
        }
        .watermark.unpaid {
            color: rgba(198, 40, 40, 0.035);
        }

        /* ---------- Header ---------- */
        .header-table {
            width: 100%;
            table-layout: fixed;
            border-bottom: 1px solid #e8dccb;
            padding-bottom: 14px;
            margin-top: 6px;
            margin-bottom: 20px;
        }
        .header-table td.brand-col {
            width: 60%;
        }
        .header-table td.title-col {
            width: 40%;
            text-align: right;
        }
        .brand img {
            height: 64px;
            width: auto;
            display: block;
            margin-bottom: 8px;
        }
        .brand-name {
            font-size: 18px;
            font-weight: bold;
            color: #3b2a23;
            margin-bottom: 8px;
        }
        .business-meta {
            font-size: 9px;
            color: #a08d7c;
            line-height: 1.6;
            max-width: 280px;
            margin-bottom: 14px;
        }
        .invoice-title .label {
            font-size: 9px;
            letter-spacing: 0.16em;
            color: #b8a68f;
            font-weight: bold;
            text-transform: uppercase;
        }
        .invoice-title h1 {
            font-size: 18px;
            margin: 2px 0 6px;
            color: #3b2a23;
            letter-spacing: 0.01em;
            font-weight: bold;
        }
        .invoice-code {
            font-size: 10px;
            color: #a08d7c;
            margin-bottom: 8px;
        }
        .status-badge {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 9px;
            font-weight: bold;
            letter-spacing: 0.05em;
        }
        .status-paid { background: #eaf5ec; color: #2e7d32; }
        .status-unpaid { background: #fbeceb; color: #c62828; }
        .status-other { background: #fbf1e4; color: #b26632; }

        /* ---------- Notice ---------- */
        .notice {
            background: #fbeceb;
            border: 1px solid #f2c9c5;
            border-radius: 8px;
            color: #c62828;
            font-size: 10.5px;
            line-height: 1.6;
            padding: 10px 14px;
            margin-bottom: 20px;
        }
        .notice.other {
            background: #fbf1e4;
            border-color: #ecd5b8;
            color: #b26632;
        }

        /* ---------- Sections ---------- */
        .section-title {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #b8a68f;
            margin-bottom: 8px;
        }
        .info-table {
            width: 100%;
            table-layout: fixed;
            margin-bottom: 22px;
        }
        .info-table td.info-col {
            width: 50%;
            padding-right: 20px;
        }
        .box strong {
            font-size: 12.5px;
            font-weight: bold;
            color: #3b2a23;
        }
        .box .line {
            margin-top: 2px;
            color: #8a7767;
            font-size: 11px;
        }
        table.kv {
            margin-top: 6px;
            width: auto;
        }
        table.kv td {
            font-size: 11px;
            color: #8a7767;
            padding: 2px 0;
        }
        table.kv td.kv-label {
            padding-right: 14px;
            white-space: nowrap;
        }
        table.kv td.kv-value {
            color: #3b2a23;
            font-weight: bold;
        }

        /* ---------- Table ---------- */
        table.items {
            width: 100%;
            margin-bottom: 6px;
            border-bottom: 1.5px solid #3b2a23;
        }
        table.items thead th {
            text-align: left;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #b8a68f;
            border-bottom: 1.5px solid #3b2a23;
            padding: 0 8px 8px 0;
        }
        table.items thead th:last-child { text-align: right; padding-right: 0; }
        table.items tbody td {
            padding: 10px 8px 10px 0;
            border-bottom: 1px solid #f0e6d8;
            font-size: 11px;
            vertical-align: top;
        }
        table.items tbody td:last-child { padding-right: 0; }
        table.items tbody tr:last-child td {
            border-bottom: none;
        }
        .text-right { text-align: right; }
        .item-name { font-weight: bold; color: #3b2a23; }
        .item-sub { font-size: 9px; color: #b8a68f; margin-top: 2px; }

        /* ---------- Totals ---------- */
        .totals-wrap {
            width: 100%;
            table-layout: fixed;
            margin-top: 18px;
            margin-bottom: 14px;
        }
        .totals-wrap td.spacer { width: 60%; }
        .totals-wrap td.totals-col { width: 40%; }
        table.totals {
            width: 100%;
        }
        table.totals td {
            padding: 4px 0;
            font-size: 11px;
            color: #8a7767;
        }
        table.totals td.text-right { color: #3b2a23; font-weight: bold; }
        table.totals tr.discount td,
        table.totals tr.discount td.text-right { color: #b26632; }
        table.totals tr.grand-total td {
            border-top: 1.5px solid #3b2a23;
            font-weight: bold;
            font-size: 14px;
            color: #3b2a23;
            padding-top: 9px;
        }
        table.totals tr.after-grand td { padding-top: 8px; }
        table.totals tr.after-grand + tr td { padding-top: 4px; }

        .amount-words {
            background: #faf6f0;
            border-radius: 6px;
            padding: 9px 12px;
            margin-bottom: 20px;
            font-size: 10.5px;
            color: #8a7767;
        }
        .amount-words strong {
            font-weight: bold;
            font-style: italic;
            color: #3b2a23;
            font-size: 10.5px;
        }

        /* ---------- Payment box ---------- */
        .payment-box {
            border-top: 1px solid #e8dccb;
            border-bottom: 1px solid #e8dccb;
            padding: 12px 0;
            margin-bottom: 24px;
        }
        table.payment-kv td {
            font-size: 10.5px;
            color: #8a7767;
            padding: 3px 28px 3px 0;
            white-space: nowrap;
        }
        table.payment-kv td b { color: #3b2a23; font-weight: bold; font-size: 10.5px; }
        .trx-id {
            margin-top: 6px;
            font-size: 9px;
            color: #b8a68f;
            font-family: Arial, sans-serif;
        }

        /* ---------- Closing ---------- */
        .closing {
            margin-top: 8px;
            text-align: center;
        }
        .closing .big {
            font-size: 12.5px;
            font-weight: bold;
            color: #893e1e;
            margin-bottom: 5px;
        }
        .closing .small {
            font-size: 9.5px;
            color: #907e70;
            line-height: 1.6;
            max-width: 380px;
            margin: 0 auto;
        }

        .legal-note {
            margin-top: 18px;
            font-size: 8.5px;
            color: #b5a495;
            text-align: center;
            line-height: 1.6;
        }

        @media (max-width: 600px) {
            .page { padding: 24px 18px; }
            .watermark { left: 30px; font-size: 44px; }
        }

        @media print {
            .toolbar { display: none !important; }
            body { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
            .page { max-width: none; margin: 0; }
            .info-table, .payment-box, .totals-wrap, .amount-words, .notice, .closing { page-break-inside: avoid; break-inside: avoid; }
        }
    </style>
</head>
<body>
@php
    $payload = is_array($booking->payload) ? $booking->payload : [];
    $isPaid = $booking->payment_status === 'paid';
    $isCancelled = $booking->status === 'cancelled';
    $statusLabel = $isPaid ? 'LUNAS' : ($isCancelled ? 'DIBATALKAN' : 'BELUM LUNAS');
    $statusClass = $isPaid ? 'status-paid' : ($isCancelled ? 'status-other' : 'status-unpaid');
    $receiptNumber = 'KW-' . $booking->code;
    $grandTotal = (int) $booking->grand_total;
    $studioName = data_get($payload, 'studio.name', $booking->studio?->name ?? 'Studio');
    $duration = (int) data_get($payload, 'pricing_tier.duration_minutes', $booking->duration_minutes);
    $bundleName = $booking->bundle_name_at_booking ?: data_get($payload, 'bundle.name');
    $isBundle = filled($bundleName);
    $studioPrice = (int) $booking->studio_price_at_booking;
    $addonTotal = (int) $booking->addon_total;
    $subtotal = $studioPrice + $addonTotal;
    $discount = (int) $booking->discount_total;

    $businessName = $business['business_name'] ?? $business['name'] ?? '';

    // Logo -> base64 supaya tampil baik di browser maupun di PDF
    $logoSrc = null;
    if (!empty($business['logo']) && is_string($business['logo']) && @file_exists($business['logo'])) {
        $logoMime = @mime_content_type($business['logo']) ?: 'image/png';
        $logoSrc = 'data:' . $logoMime . ';base64,' . base64_encode(file_get_contents($business['logo']));
    }

    $money = fn (int $amount): string => 'Rp ' . number_format($amount, 0, ',', '.');
    $methodLabels = ['cash' => 'Tunai', 'gateway' => 'Pembayaran online'];
    $paymentMethod = $methodLabels[$booking->payment_method] ?? 'Belum dipilih';
    $start = $booking->start_time;
    $end = $booking->end_time;
    $createdAt = $booking->cretime;
    $formatDate = fn ($date, string $format): string => $date
        ? $date->copy()->locale('id')->translatedFormat($format)
        : '-';
    $nowText = now()->locale('id')->translatedFormat('d F Y, H:i');
    $bookingStatusLabels = [
        'pending' => 'Menunggu konfirmasi',
        'confirmed' => 'Dikonfirmasi',
        'cancelled' => 'Dibatalkan',
        'completed' => 'Selesai',
        'expired' => 'Kedaluwarsa',
    ];
    $bookingStatusLabel = $bookingStatusLabels[$booking->status] ?? ucfirst($booking->status);
    $snapshotAddons = collect(data_get($payload, 'addons', []))
        ->keyBy(fn ($item) => (string) data_get($item, 'addon_id'));
    $spell = function (int $number) use (&$spell): string {
        $units = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];
        if ($number < 12) return $units[$number];
        if ($number < 20) return $spell($number - 10) . ' belas';
        if ($number < 100) return $spell(intdiv($number, 10)) . ' puluh' . ($number % 10 ? ' ' . $spell($number % 10) : '');
        if ($number < 200) return 'seratus' . ($number > 100 ? ' ' . $spell($number - 100) : '');
        if ($number < 1000) return $spell(intdiv($number, 100)) . ' ratus' . ($number % 100 ? ' ' . $spell($number % 100) : '');
        if ($number < 2000) return 'seribu' . ($number > 1000 ? ' ' . $spell($number - 1000) : '');
        if ($number < 1000000) return $spell(intdiv($number, 1000)) . ' ribu' . ($number % 1000 ? ' ' . $spell($number % 1000) : '');
        if ($number < 1000000000) return $spell(intdiv($number, 1000000)) . ' juta' . ($number % 1000000 ? ' ' . $spell($number % 1000000) : '');
        if ($number < 1000000000000) return $spell(intdiv($number, 1000000000)) . ' miliar' . ($number % 1000000000 ? ' ' . $spell($number % 1000000000) : '');
        return $spell(intdiv($number, 1000000000000)) . ' triliun' . ($number % 1000000000000 ? ' ' . $spell($number % 1000000000000) : '');
    };
    $amountInWords = ucfirst($grandTotal === 0 ? 'nol' : $spell($grandTotal)) . ' rupiah';

    // ---- Baris rincian layanan ----
    $rows = [[
        'name' => $studioName,
        'sub' => $isBundle ? 'Bagian dari ' . $bundleName : 'Sesi studio',
        'duration' => $duration,
        'amount' => $studioPrice,
        'type' => 'item',
    ]];

    foreach ($booking->addons as $bookingAddon) {
        $snapshot = $snapshotAddons->get((string) $bookingAddon->addon_id, []);
        $addonName = data_get($snapshot, 'addon_name', $bookingAddon->addon?->name ?? 'Add-on');
        $subs = [];
        if ($bookingAddon->is_bundle_item && $isBundle) {
            $subs[] = 'Bagian dari ' . $bundleName;
        }
        $resourceName = data_get($snapshot, 'resource_name', $bookingAddon->resource?->name);
        if ($resourceName) {
            $subs[] = 'Resource: ' . $resourceName;
        }
        $rows[] = [
            'name' => $addonName,
            'sub' => implode(' · ', $subs),
            'duration' => (int) $bookingAddon->duration_minutes,
            'amount' => (int) $bookingAddon->price_at_booking,
            'type' => 'item',
        ];
    }

    if ($discount > 0) {
        $rows[] = [
            'name' => 'Diskon' . ($isBundle ? ' ' . $bundleName : ''),
            'sub' => '',
            'duration' => 0,
            'amount' => -$discount,
            'type' => 'discount',
        ];
    }
@endphp

<div class="toolbar">
    <button class="primary" type="button" onclick="window.print()">Cetak / Simpan PDF</button>
    <button type="button" onclick="window.close()">Tutup</button>
</div>

<div class="page">

    <div class="watermark {{ $isPaid ? '' : 'unpaid' }}">{{ $statusLabel }}</div>

    <table class="header-table">
        <colgroup>
            <col style="width:60%">
            <col style="width:40%">
        </colgroup>
        <tr>
            <td class="brand-col">
                <div class="brand">
                    @if ($logoSrc)
                        <img src="{{ $logoSrc }}" alt="logo">
                    @else
                        <div class="brand-name">{{ $businessName }}</div>
                    @endif
                    <div class="business-meta">
                        @if (!empty($business['address'])){{ $business['address'] }}<br>@endif
                        @if (!empty($business['phone'])){{ $business['phone'] }}@endif
                        @if (!empty($business['phone']) && !empty($business['email'])) &middot; @endif
                        @if (!empty($business['email'])){{ $business['email'] }}@endif
                    </div>
                </div>
            </td>
            <td class="title-col">
                <div class="invoice-title">
                    <div class="label">Kuitansi</div>
                    <h1>{{ $receiptNumber }}</h1>
                    <div class="invoice-code">
                        Diterbitkan {{ $formatDate($createdAt, 'd F Y') }}
                    </div>
                    <span class="status-badge {{ $statusClass }}">{{ $statusLabel }}</span>
                </div>
            </td>
        </tr>
    </table>

    @if (!$isPaid)
        <div class="notice {{ $isCancelled ? 'other' : '' }}">
            @if ($isCancelled)
                Booking ini telah dibatalkan. Dokumen ini hanya menampilkan riwayat pemesanan dan bukan bukti pembayaran.
            @else
                Pembayaran belum diterima. Kuitansi ini belum berlaku sebagai bukti pembayaran sampai status berubah menjadi lunas.
            @endif
        </div>
    @endif

    <table class="info-table">
        <colgroup>
            <col style="width:50%">
            <col style="width:50%">
        </colgroup>
        <tr>
            <td class="info-col">
                <div class="box">
                    <div class="section-title">Diterima Dari</div>
                    <strong>{{ $booking->customer_name }}</strong>
                    <div class="line">{{ $booking->customer_wa }}</div>
                </div>
            </td>
            <td class="info-col">
                <div class="box">
                    <div class="section-title">Detail Booking</div>
                    <table class="kv">
                        <tr>
                            <td class="kv-label">Kode</td>
                            <td class="kv-value">{{ $booking->code }}</td>
                        </tr>
                        <tr>
                            <td class="kv-label">Studio</td>
                            <td class="kv-value">{{ $studioName }}</td>
                        </tr>
                        <tr>
                            <td class="kv-label">Tanggal</td>
                            <td class="kv-value">{{ $formatDate($start, 'd M Y') }}</td>
                        </tr>
                        <tr>
                            <td class="kv-label">Waktu</td>
                            <td class="kv-value">{{ optional($start)->format('H:i') ?? '--:--' }}–{{ optional($end)->format('H:i') ?? '--:--' }} WIB</td>
                        </tr>
                        <tr>
                            <td class="kv-label">Durasi</td>
                            <td class="kv-value">{{ $duration }} menit</td>
                        </tr>
                        <tr>
                            <td class="kv-label">Dibuat</td>
                            <td class="kv-value">{{ $formatDate($createdAt, 'd M Y, H:i') }}</td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <div class="section-title">Rincian Layanan</div>
    <table class="items">
        <colgroup>
            <col style="width:54%">
            <col style="width:16%">
            <col style="width:30%">
        </colgroup>
        <thead>
            <tr>
                <th>Layanan</th>
                <th>Durasi</th>
                <th class="text-right">Jumlah</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
            <tr>
                <td>
                    <span class="item-name">{{ $row['name'] }}</span>
                    @if (!empty($row['sub']))
                        <div class="item-sub">{{ $row['sub'] }}</div>
                    @endif
                </td>
                <td>{{ $row['duration'] > 0 ? $row['duration'] . ' menit' : '-' }}</td>
                <td class="text-right">
                    @if ($row['amount'] < 0)
                        -{{ $money(abs($row['amount'])) }}
                    @elseif ($row['amount'] > 0)
                        {{ $money($row['amount']) }}
                    @else
                        -
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals-wrap">
        <colgroup>
            <col style="width:60%">
            <col style="width:40%">
        </colgroup>
        <tr>
            <td class="spacer"></td>
            <td class="totals-col">
                <table class="totals">
                    <tr>
                        <td>Subtotal</td>
                        <td class="text-right">{{ $money($subtotal) }}</td>
                    </tr>
                    @if ($discount > 0)
                    <tr class="discount">
                        <td>Diskon{{ $isBundle ? ' bundle' : '' }}</td>
                        <td class="text-right">- {{ $money($discount) }}</td>
                    </tr>
                    @endif
                    <tr class="grand-total">
                        <td>Total booking</td>
                        <td class="text-right">{{ $money($grandTotal) }}</td>
                    </tr>
                    <tr class="after-grand">
                        <td>{{ $isPaid ? 'Sudah dibayar' : 'Belum dibayar' }}</td>
                        <td class="text-right">{{ $money($isPaid ? $grandTotal : 0) }}</td>
                    </tr>
                    @unless ($isPaid)
                    <tr>
                        <td>Sisa pembayaran</td>
                        <td class="text-right">{{ $money($grandTotal) }}</td>
                    </tr>
                    @endunless
                </table>
            </td>
        </tr>
    </table>

    <div class="amount-words">Terbilang: <strong>{{ $amountInWords }}</strong></div>

    <div class="payment-box">
        <div class="section-title">Informasi Pembayaran</div>
        <table class="payment-kv">
            <tr>
                <td>Pembayaran: <b>{{ $statusLabel }}</b></td>
                <td>Booking: <b>{{ $bookingStatusLabel }}</b></td>
                <td>Metode: <b>{{ $paymentMethod }}</b></td>
            </tr>
        </table>
        @if (filled($booking->payment_reference))
            <div class="trx-id">REF: {{ $booking->payment_reference }}</div>
        @endif
    </div>

    <div class="closing">
        <div class="big">Terima kasih atas kepercayaan Anda</div>
        <div class="small">
            @if ($isPaid)
                Simpan kuitansi ini sebagai bukti pembayaran yang sah. Untuk pertanyaan
                seputar booking atau pembayaran, silakan hubungi kami melalui kontak
                yang tertera di atas.
            @elseif ($isCancelled)
                Untuk pertanyaan seputar booking ini, silakan hubungi kami melalui kontak
                yang tertera di atas.
            @else
                Silakan selesaikan pembayaran untuk memperoleh kuitansi lunas. Untuk
                pertanyaan seputar booking atau pembayaran, silakan hubungi kami melalui
                kontak yang tertera di atas.
            @endif
        </div>
    </div>

    <div class="legal-note">
        @if ($isPaid)
            Dokumen ini dihasilkan secara otomatis dan sah tanpa tanda tangan basah maupun cap basah.<br>
        @else
            Dokumen ini dihasilkan secara otomatis dan belum berlaku sebagai bukti pembayaran.<br>
        @endif
        {{ $businessName }} &middot; {{ $receiptNumber }} &middot; Dicetak {{ $nowText }} WIB
    </div>

</div>
</body>
</html>