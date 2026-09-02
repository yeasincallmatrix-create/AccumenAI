<style>
    @page { size: A4 landscape; margin: 0; }
    .certificate-sheet {
        background: #fff;
        border: 1px solid rgba(13, 110, 253, .18);
        border-radius: 18px;
        box-shadow: 0 20px 50px rgba(13, 110, 253, .10);
        width: 297mm;
        max-width: 100%;
        aspect-ratio: 297 / 210;
        height: auto;
        margin: 0 auto;
        padding: 42px 36px 24px;
        position: relative;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        box-sizing: border-box;
        min-width: 0;
        min-height: 0;
    }
    .cert-corner {
        position: absolute;
        top: 24px;
        background: rgba(13, 110, 253, .06);
        border: 1px solid rgba(13, 110, 253, .18);
        border-radius: 10px;
        padding: 6px 14px;
        font-size: 11px;
        letter-spacing: .5px;
        color: #495057;
    }
    .cert-corner strong { display: block; font-size: 12px; color: #212529; }
    .cert-corner-tl { left: 24px; }
    .cert-corner-qr {
        background: #fff;
        border-color: rgba(13, 110, 253, .22);
        padding: 6px;
        top: 24px;
    }
    .cert-corner-qr .cert-qr-box {
        background: #fff;
        border: none;
        border-radius: 8px;
        padding: 6px;
        line-height: 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 6px;
    }
    .cert-corner-qr .cert-qr-box svg { width: 84px; height: 84px; display: block; }
    .cert-corner-qr .cert-qr-text {
        font-size: 10px;
        color: #6c757d;
        line-height: 1.3;
        text-align: center;
        letter-spacing: .5px;
    }
    .cert-corner-qr .cert-qr-text strong { display: block; color: #212529; font-size: 11px; }
    .cert-corner-tr {
        right: 24px;
        text-align: left;
        display: flex;
        flex-direction: column;
        gap: 3px;
        min-width: 180px;
        max-width: 240px;
        overflow: hidden;
    }
    .cert-corner-tr > div {
        display: flex;
        justify-content: space-between;
        gap: 16px;
        align-items: baseline;
    }
    .cert-corner-tr > div strong { white-space: nowrap; word-break: keep-all; overflow-wrap: normal; max-width: 150px; text-align: right; }

    .cert-head {
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        margin-bottom: 2px;
    }
    .cert-logo {
        width: 130px;
        height: 130px;
        object-fit: contain;
        margin-bottom: 14px;
    }
    .cert-logo-fallback {
        width: 130px;
        height: 130px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, var(--bs-primary), #5a9bff);
        color: #fff;
        font-size: 44px;
        font-weight: 800;
        margin-bottom: 14px;
    }
    .cert-institute { font-size: 20px; font-weight: 800; color: #212529; letter-spacing: 1px; word-break: break-word; overflow-wrap: anywhere; max-width: 100%; line-height: 1.2; }
    .cert-tagline { font-size: 12px; letter-spacing: 2px; color: #6c757d; margin-top: 2px; }

    .cert-title {
        text-align: center;
        margin: 12px 0 22px;
        position: relative;
    }
    .cert-title h3 {
        display: inline-block;
        font-size: 20px;
        font-weight: 800;
        letter-spacing: 3px;
        color: var(--bs-primary);
        margin: 0;
        padding: 0 16px 10px;
        border-bottom: 3px solid var(--bs-primary);
        max-width: 100%;
        word-break: break-word;
        overflow-wrap: anywhere;
    }

    .cert-body { text-align: center; flex: 1 1 auto; min-width: 0; overflow: hidden; max-width: 100%; }
    .cert-certify-present { font-size: 13px; letter-spacing: 1.5px; color: #6c757d; text-transform: uppercase; }
    .cert-certify { font-size: 13px; letter-spacing: 1.5px; color: #6c757d; text-transform: uppercase; margin-top: 4px; }
    .cert-student {
        font-size: 30px;
        font-weight: 800;
        color: #212529;
        letter-spacing: 1.2px;
        margin: 10px 0 4px;
        text-transform: uppercase;
        word-break: break-word;
        overflow-wrap: anywhere;
        max-width: 100%;
        line-height: 1.1;
    }
    .cert-guardian { font-size: 15px; color: #495057; margin-bottom: 10px; }
    .cert-meta {
        display: inline-flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 6px 16px;
        margin-bottom: 12px;
        font-size: 12px;
        color: #6c757d;
        background: rgba(13, 110, 253, .05);
        border-radius: 12px;
        padding: 8px 16px;
        max-width: 100%;
        box-sizing: border-box;
    }
    .cert-meta span { word-break: break-all; overflow-wrap: anywhere; }
    .cert-meta strong { color: #212529; font-weight: 600; }
    .cert-completed { font-size: 14px; color: #6c757d; }
    .cert-course { font-size: 22px; font-weight: 800; color: var(--bs-primary); letter-spacing: 1px; margin: 6px 0 8px; text-transform: uppercase; word-break: break-word; overflow-wrap: anywhere; max-width: 100%; line-height: 1.15; }
    .cert-fulfilled { font-size: 13px; color: #6c757d; max-width: 600px; margin: 0 auto; }

    .cert-subjects { margin: 36px auto 0; max-width: 100%; box-sizing: border-box; }
    .cert-subjects-label {
        font-size: 11px;
        letter-spacing: 2px;
        color: #6c757d;
        text-transform: uppercase;
        margin-bottom: 10px;
    }
    .cert-subject-chips { display: flex; flex-wrap: wrap; justify-content: center; gap: 6px; max-width: 100%; }
    .cert-subject-chip {
        background: rgba(13, 110, 253, .07);
        border: 1px solid rgba(13, 110, 253, .22);
        color: #1b4fd8;
        border-radius: 999px;
        padding: 5px 12px;
        font-size: 12px;
        font-weight: 600;
        max-width: 100%;
        word-break: break-word;
        overflow-wrap: anywhere;
    }

    .cert-footer {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 16px;
        margin-top: auto;
        padding: 18px 11% 0;
        position: relative;
        flex-wrap: wrap;
        min-width: 0;
    }
    .cert-footer::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        border-top: 1px solid rgba(13, 110, 253, .15);
        transform: translateY(-22px);
    }
    .cert-qr-box {
        background: #fff;
        border: 1px solid rgba(13, 110, 253, .25);
        border-radius: 10px;
        padding: 8px;
        line-height: 0;
    }
    .cert-qr-text { font-size: 11px; color: #6c757d; }
    .cert-qr-text strong { display: block; color: #212529; font-size: 13px; }
    .cert-sign { text-align: center; font-size: 11px; color: #6c757d; flex: 0 0 180px; min-width: 120px; max-width: 180px; }
    .cert-sign .sign-line { width: 100%; max-width: 180px; border-top: 1px solid #adb5bd; margin: 0 auto 6px; padding-top: 6px; font-weight: 700; color: #212529; letter-spacing: 1px; word-break: break-word; overflow-wrap: anywhere; }
    .cert-sign-left { text-align: center; align-items: center; }
    .cert-sign-left .sign-line { margin-left: auto; margin-right: auto; }
    .cert-sign-right { text-align: center; align-items: center; }
    .cert-sign-right .sign-line { margin-left: auto; margin-right: auto; }
    .cert-meta-line {
        text-align: center;
        margin-top: -6px;
        font-size: 12px;
        color: #6c757d;
        letter-spacing: .5px;
    }
    .cert-meta-line strong { color: #212529; font-weight: 600; }

    .cert-watermark {
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
    }

    html.monetix-dark .certificate-sheet { background: #1e1f22; border-color: rgba(255,255,255,.12); }
    html.monetix-dark .cert-institute, html.monetix-dark .cert-student,
    html.monetix-dark .cert-qr-text strong, html.monetix-dark .cert-meta strong,
    html.monetix-dark .cert-meta-line strong, html.monetix-dark .cert-corner strong,
    html.monetix-dark .cert-sign .sign-line { color: #f8f9fa; }
    html.monetix-dark .cert-corner { background: rgba(255,255,255,.04); border-color: rgba(255,255,255,.15); }

    @media print {
        @page { size: A4 landscape; margin:0 !important; }
        html, body { margin:0 !important; padding:0 !important; width:297mm !important; height:210mm !important; overflow:hidden !important; }
        .certificate-sheet {
            box-shadow: none !important;
            border: none !important;
            margin: 0 !important;
            width: 297mm !important;
            max-width: none !important;
            height: 210mm !important;
            aspect-ratio: auto !important;
            border-radius: 0 !important;
            position: absolute !important;
            top: 0 !important;
            left: 0 !important;
        }
        .cert-watermark { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>

<div class="certificate-sheet">
    @if ($certificate->status === 'revoked')
        <div class="cert-watermark">REVOKED</div>
    @endif

    @if ($student?->student_id || $student?->reg_no || $certificate->certificate_number)
        <div class="cert-corner cert-corner-tr">
            <div><span>Student ID.</span><strong>{{ $student->student_id ?? '—' }}</strong></div>
            <div><span>Reg. No.</span><strong>{{ $student->reg_no ?? '—' }}</strong></div>
            <div><span>Certificate No.</span><strong>{{ $certificate->certificate_number ?? '—' }}</strong></div>
        </div>
    @endif

    @if ($qrSvg && $verifyUrl)
        <div class="cert-corner cert-corner-tl cert-corner-qr">
            <div class="cert-qr-box">
                {!! $qrSvg !!}
                <div class="cert-qr-text"><strong>Scan to verify</strong></div>
            </div>
        </div>
    @endif

    <div class="cert-head">
        @if ($logoDataUri)
            <img class="cert-logo" src="{{ $logoDataUri }}" alt="{{ $instituteName }}">
        @elseif ($instituteName)
            <div class="cert-logo-fallback">{{ $initials }}</div>
        @endif
        <div class="cert-institute">{{ $instituteName ?: 'Institute' }}</div>
        @if ($tagline)
            <div class="cert-tagline">{{ $tagline }}</div>
        @endif
    </div>

    <div class="cert-title">
        <h3>CERTIFICATE OF COMPLETION</h3>
    </div>

    <div class="cert-body">
        <div class="cert-certify">This is to certify that</div>
        <h2 class="cert-student">{{ $studentName ?: 'Student' }}</h2>
        @if ($guardian)
            <div class="cert-guardian">{{ $guardian }}</div>
        @endif



        <div class="cert-completed">has successfully completed the prescribed training course</div>
        <div class="cert-course">{{ $courseName ?: 'Course' }}</div>
        @php $durationText1 = isset($course) && $course && $course->duration_value ? trim(rtrim(rtrim(number_format((float)$course->duration_value, 2), '0'), '.') . ' ' . $course->duration_type) : ''; @endphp
        <div class="cert-fulfilled">and fulfilled the required {{ $durationText1 ? $durationText1 . ' ' : '' }}training and assessment requirements of the institute.</div>

        @if ($subjects->isNotEmpty())
            @php $orderedSubjects1 = $subjects->sortBy(function($s){ $map=['ARC Welding'=>1,'TIG'=>2,'MIG'=>3]; return $map[$s->name] ?? 99; }); @endphp
            <div class="cert-subjects">
                <div class="cert-subjects-label">Completed Subjects</div>
                <div class="cert-subject-chips">
                    @foreach ($orderedSubjects1 as $subject)
                        <span class="cert-subject-chip">{{ $subject->name }}</span>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    <div class="cert-footer">
        @if ($qrSvg && $verifyUrl)
            <div class="cert-sign cert-sign-left">
                <div class="sign-line">Instructor / Trainer</div>
                Training Department
            </div>
        @else
            <div class="cert-qr-text cert-sign-left" style="text-align:left; flex:1 1 0;">
                <strong>Not yet issued</strong>
                This certificate has no verification code yet.
            </div>
        @endif
        <div class="cert-sign cert-sign-right">
            <div class="sign-line">{{ $instituteName ?: 'MAWA ACADEMY' }}</div>
            Authorized Issuer
        </div>
    </div>

    <div class="cert-meta-line">
        @if ($certificate->issue_date)
            Issue Date: <strong>{{ $certificate->issue_date->format('d F Y') }}</strong>
        @endif
    </div>
</div>