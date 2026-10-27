<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 18px 28px; }
        body {
            font-family: 'Helvetica', Arial, sans-serif;
            font-size: 10.5px;
            color: #111;
        }
        table { border-collapse: collapse; width: 100%; }
        .outer {
            border: 1.5px solid #000;
            padding: 10px 14px;
        }
        .header-table td { vertical-align: middle; }
        .logo { height: 46px; }
        .company-name {
            font-size: 15px;
            font-weight: bold;
            text-align: center;
        }
        .company-address {
            font-size: 9.5px;
            text-align: center;
        }
        .divider { border-top: 1.5px solid #000; margin: 6px 0; }
        .title-block { text-align: center; margin: 8px 0 10px; }
        .title-block .title {
            font-size: 12px;
            font-weight: bold;
            text-decoration: underline;
        }
        .title-block .period { font-size: 10.5px; margin-top: 2px; }
        .info-table td { padding: 1px 0; vertical-align: top; }
        .info-table .label { width: 70px; }
        .info-table .sep { width: 12px; }
        .info-table .value { font-weight: bold; }
        .section-head {
            font-weight: bold;
            text-decoration: underline;
            padding-bottom: 4px;
        }
        .amounts-table { margin-top: 10px; table-layout: fixed; }
        .amounts-table > tbody > tr > td { vertical-align: top; padding: 0; }
        .col-left { width: 270px; padding-right: 10px; }
        .col-right { width: 270px; padding-left: 10px; }
        .line-table { table-layout: fixed; }
        .line-table td { padding: 2px 0; }
        .line-table .l-label { width: 68%; }
        .line-table .l-sep { width: 4%; }
        .line-table .l-amount { width: 28%; text-align: right; }
        .subtotal-row td {
            border-top: 1px solid #000;
            font-weight: bold;
            padding-top: 3px;
        }
        .bank-box {
            border: 1px solid #000;
            padding: 8px;
            margin-top: 18px;
        }
        .bank-box .bank-title { font-weight: bold; font-style: italic; }
        .totals-table { margin-top: 14px; table-layout: fixed; }
        .totals-table td { border-top: 1.5px solid #000; padding-top: 4px; font-weight: bold; }
        .totals-table .right { text-align: right; }
        .rounded-row td { padding-top: 2px; font-weight: normal; border-top: none; }
        .net-row {
            margin-top: 6px;
            border-top: 1.5px solid #000;
            border-bottom: 1.5px solid #000;
            padding: 4px 0;
        }
        .net-row table td { font-weight: bold; }
        .terbilang { text-align: center; font-style: italic; margin-top: 4px; }
        .signature-block { margin-top: 22px; width: 260px; float: right; text-align: center; }
        .signature-block .place-date { text-align: left; }
        .signature-space { height: 56px; position: relative; }
        .signature-space img { max-height: 56px; max-width: 220px; }
        .director-name { text-decoration: underline; font-weight: bold; }
        .clearfix::after { content: ''; display: block; clear: both; }
    </style>
</head>
<body>
    <div class="outer">
        <table class="header-table">
            <tr>
                <td style="width: 90px;">
                    @if (is_file($logoPath))
                        <img class="logo" src="{{ $logoPath }}">
                    @endif
                </td>
                <td>
                    <div class="company-name">{{ strtoupper($company->legal_name ?: $company->name) }}</div>
                    @if ($company->address)
                        <div class="company-address">{{ $company->address }}</div>
                    @endif
                </td>
                <td style="width: 90px;"></td>
            </tr>
        </table>
        <div class="divider"></div>

        <div class="title-block">
            <div class="title">SLIP GAJI KARYAWAN</div>
            <div class="period">Periode {{ $periodLabel }}</div>
        </div>

        <table class="info-table">
            <tr>
                <td class="label">NIK</td>
                <td class="sep">:</td>
                <td class="value">{{ $company->code ? $company->code.'-' : '' }}{{ $user->employee_number ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label">Nama</td>
                <td class="sep">:</td>
                <td class="value">{{ $user->name }}</td>
            </tr>
            <tr>
                <td class="label">Jabatan</td>
                <td class="sep">:</td>
                <td class="value">{{ $entry->position_name ?: '-' }}</td>
            </tr>
            <tr>
                <td class="label">Status</td>
                <td class="sep">:</td>
                <td class="value">{{ $statusLabel }}</td>
            </tr>
        </table>

        <table class="amounts-table">
            <tr>
                <td class="col-left">
                    <div class="section-head">PENGHASILAN</div>
                    <table class="line-table">
                        @foreach ($earnings as $line)
                            <tr>
                                <td class="l-label">{{ $line['label'] }}</td>
                                <td class="l-sep">:</td>
                                <td class="l-amount">{{ number_format((float) $line['amount'], 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                        <tr class="subtotal-row">
                            <td class="l-label">Sub Total</td>
                            <td class="l-sep"></td>
                            <td class="l-amount">{{ number_format((float) $grossTotal, 0, ',', '.') }}</td>
                        </tr>
                    </table>

                    <table class="line-table" style="margin-top: 16px;">
                        <tr>
                            <td class="l-label">Bonus Target</td>
                            <td class="l-sep">:</td>
                            <td class="l-amount">-</td>
                        </tr>
                    </table>
                </td>
                <td class="col-right">
                    <div class="section-head">POTONGAN</div>
                    <table class="line-table">
                        <tr>
                            <td class="l-label">PPh 21</td>
                            <td class="l-sep">:</td>
                            <td class="l-amount">-</td>
                        </tr>
                        <tr>
                            <td class="l-label">Asuransi</td>
                            <td class="l-sep">:</td>
                            <td class="l-amount">-</td>
                        </tr>
                        @foreach ($deductions as $line)
                            <tr>
                                <td class="l-label">{{ $line['label'] }}</td>
                                <td class="l-sep">:</td>
                                <td class="l-amount">{{ number_format((float) $line['amount'], 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                        <tr class="subtotal-row">
                            <td class="l-label">Sub Total</td>
                            <td class="l-sep"></td>
                            <td class="l-amount">{{ number_format((float) $deductionTotal, 0, ',', '.') }}</td>
                        </tr>
                    </table>

                    @if ($destination)
                        <div class="bank-box">
                            <div class="bank-title">{{ $destination['bank_name'] ?: '-' }}</div>
                            <div>No. Rek : {{ $destination['account_number'] ?: '-' }}</div>
                            <br>
                            <div class="bank-title">An. {{ strtoupper($destination['account_holder'] ?: $user->name) }}</div>
                        </div>
                    @endif
                </td>
            </tr>
        </table>

        <table class="totals-table">
            <tr>
                <td style="width: 50%;">Total (A)</td>
                <td class="right" style="width: 50%;">Total (B)</td>
            </tr>
            <tr class="rounded-row">
                <td>{{ number_format((float) $grossTotal, 0, ',', '.') }}&nbsp;&nbsp;Dibulatkan</td>
                <td class="right">Rp {{ number_format((float) $deductionTotal, 0, ',', '.') }}</td>
            </tr>
        </table>

        <div class="net-row">
            <table>
                <tr>
                    <td style="width: 55%;">PENERIMAAN BERSIH (A - B)&nbsp;&nbsp;=</td>
                    <td class="right" style="width: 45%;">Rp {{ number_format((float) $netAmount, 0, ',', '.') }}</td>
                </tr>
            </table>
        </div>
        <div class="terbilang">Terbilang: #{{ $terbilang }}#</div>

        <div class="clearfix">
            <div class="signature-block">
                <div class="place-date">{{ $issueCity }}, {{ $issueDateLabel }}</div>
                <div>Direktur</div>
                <div class="signature-space">
                    @if ($signaturePath && is_file($signaturePath))
                        <img src="{{ $signaturePath }}">
                    @endif
                </div>
                @if ($company->director_name)
                    <div class="director-name">{{ $company->director_name }}</div>
                @endif
            </div>
        </div>
    </div>
</body>
</html>
