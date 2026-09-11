<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Token #{{ $appointment->serial_number }} — {{ $hospitalName }}</title>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
        font-family: Arial, Helvetica, sans-serif;
        color: #000;
        background: #fff;
        display: flex;
        justify-content: center;
    }
    .token {
        width: 80mm;
        max-width: 100%;
        padding: 6mm 4mm;
        text-align: center;
    }
    .hospital-name {
        font-size: 15pt;
        font-weight: bold;
        line-height: 1.25;
    }
    .hospital-address {
        font-size: 9pt;
        margin-top: 1mm;
        line-height: 1.3;
    }
    .divider {
        border-top: 1px dashed #000;
        margin: 3mm 0;
    }
    .serial-label {
        font-size: 11pt;
        letter-spacing: 2px;
    }
    .serial-no {
        font-size: 64pt;
        font-weight: bold;
        line-height: 1.1;
    }
    .room-no {
        font-size: 16pt;
        font-weight: bold;
        margin-top: 1mm;
    }
    .meta {
        font-size: 9pt;
        margin-top: 2mm;
        line-height: 1.5;
    }
    .actions { margin-top: 4mm; }
    .actions button {
        font-size: 12pt;
        padding: 2mm 8mm;
        cursor: pointer;
    }
    @media print {
        .actions { display: none !important; }
        @page { size: auto; margin: 4mm; }
    }
</style>
</head>
<body onload="window.print()">
<div class="token">
    <div class="hospital-name">{{ $hospitalName }}</div>
    @if($hospitalAddress !== '')
        <div class="hospital-address">{{ $hospitalAddress }}</div>
    @endif
    @if($hospitalPhone !== '')
        <div class="hospital-address">Phone: {{ $hospitalPhone }}</div>
    @endif

    <div class="divider"></div>

    <div class="serial-label">SERIAL NO</div>
    <div class="serial-no">#{{ $appointment->serial_number }}</div>

    <div class="divider"></div>

    <div class="room-no">Room No: {{ $roomNo ?? '—' }}</div>

    <div class="divider"></div>

    <div class="meta">
        <div>Patient: {{ $appointment->patient->full_name ?? 'N/A' }}</div>
        <div>Doctor: {{ $appointment->doctor->name ?? 'N/A' }}</div>
        <div>Date: {{ $appointment->appointment_date?->format('d M Y') ?? 'N/A' }}</div>
    </div>

    <div class="actions">
        <button type="button" onclick="window.print()">Print</button>
    </div>
</div>
<script>
// Auto-close this tab once the print dialog is done (printed or cancelled).
window.onafterprint = function () {
    setTimeout(function () { window.close(); }, 300);
};
</script>
</body>
</html>
