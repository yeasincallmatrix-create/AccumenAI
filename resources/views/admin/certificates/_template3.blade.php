<style>
    @page { size: A4 landscape; margin: 0; }
    .cert3-sheet {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, .18);
        width: 297mm;
        max-width: 100%;
        aspect-ratio: 297 / 210;
        height: auto;
        margin: 0 auto;
        position: relative;
        overflow: hidden;
        color: #202020;
        font-family: Georgia, "Times New Roman", serif;
        box-sizing: border-box;
    }

    .cert3-border-outer {
        position: absolute;
        inset: 7mm;
        border: 2px solid #176b45;
        pointer-events: none;
    }
    .cert3-border-inner {
        position: absolute;
        inset: 9mm;
        border: 1px solid #c5a45a;
        pointer-events: none;
    }

    .cert3-corner {
        position: absolute;
        width: 22mm;
        height: 22mm;
        border-color: #176b45;
        z-index: 3;
        pointer-events: none;
    }
    .cert3-corner.tl { top: 10mm; left: 10mm; border-top: 4px solid; border-left: 4px solid; }
    .cert3-corner.tr { top: 10mm; right: 10mm; border-top: 4px solid; border-right: 4px solid; }
    .cert3-corner.bl { bottom: 10mm; left: 10mm; border-bottom: 4px solid; border-left: 4px solid; }
    .cert3-corner.br { bottom: 10mm; right: 10mm; border-bottom: 4px solid; border-right: 4px solid; }

    .cert3-content {
        position: relative;
        z-index: 5;
        height: 100%;
        padding: 15mm 16mm 10mm;
        display: flex;
        flex-direction: column;
        box-sizing: border-box;
        overflow: hidden;
        min-width: 0;
        min-height: 0;
    }

    .cert3-qr {
        position: absolute;
        top: 15mm;
        left: 19mm;
        z-index: 6;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 1.5mm;
        text-align: center;
    }
    .cert3-qr svg { width: 21mm; height: 21mm; display: block; }
    .cert3-qr-text { font-family: Arial, sans-serif; font-size: 8px; color: #555; line-height: 1.3; }
    .cert3-qr-text strong { display: block; color: #176b45; font-size: 9px; letter-spacing: .5px; }

    .cert3-corner-info {
        position: absolute;
        top: 12mm;
        right: 16mm;
        z-index: 6;
        text-align: right;
        font-family: Arial, sans-serif;
        max-width: 70mm;
        overflow: visible;
    }
    .cert3-info-item { margin-bottom: 2.5mm; }
    .cert3-info-label {
        display: block;
        font-size: 8px;
        text-transform: uppercase;
        letter-spacing: .8px;
        color: #777;
    }
    .cert3-info-value {
        display: block;
        margin-top: .5mm;
        font-size: 10px;
        font-weight: bold;
        color: #202020;
        white-space: nowrap;
        word-break: keep-all;
        overflow-wrap: normal;
        max-width: 100%;
    }

    .cert3-header { text-align: center; }
    .cert3-logo {
        width: 27.5mm;
        height: 27.5mm;
        margin: 0 auto 3mm;
        border-radius: 50%;
        border: 2px solid #176b45;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #176b45;
        font-size: 15px;
        font-weight: bold;
    }
    .cert3-logo img { width: 100%; height: 100%; object-fit: cover; }
    .cert3-institute-name {
        margin: 0;
        font-family: Arial, sans-serif;
        font-size: 22px;
        font-weight: 800;
        letter-spacing: 1.5px;
        color: #176b45;
        word-break: break-word;
        overflow-wrap: anywhere;
        max-width: 100%;
        line-height: 1.15;
    }
    .cert3-institute-tagline {
        margin-top: 2px;
        font-family: Arial, sans-serif;
        font-size: 10px;
        letter-spacing: 2px;
        color: #555;
    }

    .cert3-title-section { text-align: center; margin-top: 4mm; }
    .cert3-title {
        margin: 0;
        font-family: Georgia, serif;
        font-size: 24px;
        letter-spacing: 2px;
        color: #1b1b1b;
        word-break: break-word;
        overflow-wrap: anywhere;
        max-width: 100%;
    }
    .cert3-title-line {
        width: 80mm;
        height: 2px;
        background: #c5a45a;
        margin: 2.5mm auto;
    }
    .cert3-subtitle { font-size: 13px; font-style: italic; color: #666; }

    .cert3-main-body { margin-top: 2mm; flex: 1; min-width: 0; overflow: hidden; }
    .cert3-main-content { text-align: center; }
    .cert3-certify-text { font-size: 14px; margin-top: 2mm; }
    .cert3-student-name {
        margin: 2mm 0 1mm;
        font-family: Georgia, serif;
        font-size: 23px;
        font-weight: bold;
        letter-spacing: 1px;
        color: #176b45;
        word-break: break-word;
        overflow-wrap: anywhere;
        max-width: 100%;
        line-height: 1.1;
    }
    .cert3-student-info { font-size: 12px; line-height: 1.6; color: #333; }
    .cert3-completion-text { margin-top: 2mm; font-size: 13px; color: #333; }
    .cert3-course-name {
        margin: 2mm auto;
        font-family: Arial, sans-serif;
        font-size: 19px;
        font-weight: 800;
        letter-spacing: 1px;
        color: #176b45;
        word-break: break-word;
        overflow-wrap: anywhere;
        max-width: 100%;
        line-height: 1.15;
    }

    .cert3-subjects {
        margin-top: 8mm;
        text-align: center;
        font-family: Arial, sans-serif;
    }
    .cert3-subjects-title {
        font-size: 9px;
        text-transform: uppercase;
        letter-spacing: 1.2px;
        color: #777;
    }
    .cert3-subjects-list { margin-top: 1mm; font-size: 11px; font-weight: bold; color: #202020; word-break: break-word; overflow-wrap: anywhere; max-width: 100%; }

    .cert3-footer {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        margin-top: 3mm;
        align-items: end;
        gap: 4mm;
        min-width: 0;
    }
    .cert3-signature { text-align: center; font-family: Arial, sans-serif; font-size: 8px; min-width: 0; overflow: hidden; }
    .cert3-signature-line { width: 32mm; max-width: 100%; border-top: 1px solid #333; margin: 0 auto 2mm; }
    .cert3-signature-title { font-weight: bold; color: #202020; }
    .cert3-signature-subtitle { font-size: 7px; color: #777; }

    .cert3-watermark {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%) rotate(-28deg);
        font-size: 96px;
        font-weight: 800;
        letter-spacing: 8px;
        color: rgba(220, 53, 69, .10);
        pointer-events: none;
        white-space: nowrap;
        user-select: none;
        z-index: 8;
    }

    html.monetix-dark .cert3-sheet { background: #1e1f22; }
    html.monetix-dark .cert3-student-info, html.monetix-dark .cert3-completion-text { color: #cfd3d8; }
    html.monetix-dark .cert3-info-value, html.monetix-dark .cert3-signature-title { color: #f8f9fa; }
    html.monetix-dark .cert3-title { color: #f8f9fa; }

    @media print {
        @page { size: A4 landscape; margin:0 !important; }
        html, body { margin:0 !important; padding:0 !important; width:297mm !important; height:210mm !important; overflow:hidden !important; }
        .cert3-sheet {
            box-shadow: none !important;
            border-radius: 0 !important;
            margin: 0 !important;
            width: 297mm !important;
            max-width: none !important;
            height: 210mm !important;
            aspect-ratio: auto !important;
            position: absolute !important;
            top: 0 !important;
            left: 0 !important;
        }
        .cert3-watermark { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>

<div class="cert3-sheet">
    @if ($certificate->status === 'revoked')
        <div class="cert3-watermark">REVOKED</div>
    @endif

    <div class="cert3-border-outer"></div>
    <div class="cert3-border-inner"></div>

    <div class="cert3-corner tl"></div>
    <div class="cert3-corner tr"></div>
    <div class="cert3-corner bl"></div>
    <div class="cert3-corner br"></div>

    <div class="cert3-content">

        <div class="cert3-corner-info">
            @if($student?->student_id)
            <div class="cert3-info-item">
                <span class="cert3-info-label">Student ID</span>
                <span class="cert3-info-value">{{ $student->student_id }}</span>
            </div>
            @endif
            <div class="cert3-info-item">
                <span class="cert3-info-label">Student ID No</span>
                <span class="cert3-info-value">{{ $student->student_id_number ?? '—' }}</span>
            </div>
            <div class="cert3-info-item">
                <span class="cert3-info-label">Reg. no</span>
                <span class="cert3-info-value">{{ $student->reg_no ?? '—' }}</span>
            </div>
            <div class="cert3-info-item">
                <span class="cert3-info-label">Certificate Number</span>
                <span class="cert3-info-value">{{ $certificate->certificate_number ?? '—' }}</span>
            </div>
        </div>

        @if ($qrSvg && $verifyUrl)
            <div class="cert3-qr">
                {!! $qrSvg !!}
                <div class="cert3-qr-text"><strong>Scan to verify</strong></div>
            </div>
        @endif

        <header class="cert3-header">
            @if ($logoDataUri)
                <div class="cert3-logo"><img src="{{ $logoDataUri }}" alt="{{ $instituteName }}"></div>
            @elseif ($instituteName)
                <div class="cert3-logo">{{ $initials }}</div>
            @endif
            <h1 class="cert3-institute-name">{{ $instituteName ?: 'Institute' }}</h1>
            @if ($tagline)
                <div class="cert3-institute-tagline">{{ $tagline }}</div>
            @endif
        </header>

        <section class="cert3-title-section">
            <h2 class="cert3-title">CERTIFICATE OF COMPLETION</h2>
            <div class="cert3-title-line"></div>
        </section>

        <main class="cert3-main-body">
            <section class="cert3-main-content">
                <div class="cert3-certify-text">This is to certify that</div>
                <div class="cert3-student-name">{{ $studentName ?: 'Student' }}</div>

                @if ($guardian)
                    <div class="cert3-student-info">{{ $guardian }}</div>
                @endif

                <div class="cert3-completion-text">has successfully completed the prescribed training course</div>

                <div class="cert3-course-name">{{ $courseName ?: 'Course' }}</div>

                @php $durationText3 = isset($course) && $course && $course->duration_value ? trim(rtrim(rtrim(number_format((float)$course->duration_value, 2), '0'), '.') . ' ' . $course->duration_type) : ''; @endphp
                <div class="cert3-completion-text">and fulfilled the required {{ $durationText3 ? $durationText3 . ' ' : '' }}training and assessment requirements of the institute.</div>

                @if ($subjects->isNotEmpty())
                    @php $orderedSubjects3 = $subjects->sortBy(function($s){ $map=['ARC Welding'=>1,'TIG'=>2,'MIG'=>3]; return $map[$s->name] ?? 99; }); @endphp
                    <div class="cert3-subjects">
                        <div class="cert3-subjects-title">Subjects Completed</div>
                        <div class="cert3-subjects-list">{{ $orderedSubjects3->pluck('name')->implode(' • ') }}</div>
                    </div>
                @endif
            </section>
        </main>

        <footer class="cert3-footer">
            <div class="cert3-signature">
                <div class="cert3-signature-line"></div>
                <div class="cert3-signature-title">Instructor / Trainer</div>
                <div class="cert3-signature-subtitle">Training Department</div>
            </div>
            <div class="cert3-signature">
                <div class="cert3-signature-line"></div>
                <div class="cert3-signature-title">Authorized Signatory</div>
                <div class="cert3-signature-subtitle">{{ $instituteName ?: 'Institute' }}</div>
            </div>
            <div class="cert3-signature">
                <div class="cert3-signature-line"></div>
                <div class="cert3-signature-title">Director</div>
                <div class="cert3-signature-subtitle">{{ $instituteName ?: 'Institute' }}</div>
            </div>
        </footer>

    </div>
</div>