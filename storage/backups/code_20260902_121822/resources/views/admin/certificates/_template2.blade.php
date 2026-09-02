<style>
    @page { size: A4 landscape; margin: 0; }
    .cert2-sheet {
        background: #fff;
        border: 1px solid rgba(32, 42, 68, .18);
        border-radius: 18px;
        box-shadow: 0 20px 50px rgba(32, 42, 68, .12);
        width: 297mm;
        max-width: 100%;
        aspect-ratio: 297 / 210;
        height: auto;
        margin: 0 auto;
        padding: 38px 28px 28px;
        position: relative;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        box-sizing: border-box;
    }
    .cert2-frame {
        flex: 1;
        border: 2px solid #202a44;
        outline: 1px solid #c9a227;
        outline-offset: -8px;
        padding: 32px 32px 24px;
        position: relative;
        display: flex;
        flex-direction: column;
        border-radius: 6px;
        box-sizing: border-box;
        max-width: 100%;
        overflow: hidden;
        min-width: 0;
        min-height: 0;
    }
    .cert2-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        border-bottom: 2px solid #202a44;
        padding-bottom: 16px;
        min-width: 0;
        overflow: visible;
    }
    .cert2-header > div:nth-child(2) { flex: 1 1 auto; min-width: 0; overflow: hidden; }
    .cert2-logo {
        width: 86px;
        height: 86px;
        object-fit: contain;
        object-position: center;
        display: block;
        flex-shrink: 0;
        background: #fff;
        border-radius: 6px;
        padding: 4px;
        box-sizing: border-box;
        border: 1px solid rgba(32,42,68,.08);
    }
    .cert2-logo-fallback {
        width: 86px;
        height: 86px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #202a44, #3a4a78);
        color: #c9a227;
        font-size: 34px;
        font-weight: 800;
        flex-shrink: 0;
    }
    .cert2-institute {
        font-size: 22px;
        font-weight: 800;
        color: #202a44;
        letter-spacing: 1px;
        text-align: center;
        line-height: 1.25;
        word-break: break-word;
        overflow-wrap: anywhere;
        max-width: 100%;
    }
    .cert2-tagline {
        font-size: 12px;
        letter-spacing: 3px;
        color: #c9a227;
        text-transform: uppercase;
        text-align: center;
        margin-top: 4px;
    }
    .cert2-badge {
        text-align: right;
        font-size: 10px;
        line-height: 1.5;
        color: #495057;
        white-space: nowrap;
        flex-shrink: 0;
        max-width: 240px;
        word-break: keep-all;
        overflow-wrap: normal;
    }
    .cert2-badge strong { display: block; font-size: 12px; color: #202a44; }

    .cert2-title {
        text-align: center;
        margin: 26px 0 18px;
    }
    .cert2-title h3 {
        display: inline-block;
        font-size: 22px;
        font-weight: 800;
        letter-spacing: 4px;
        color: #202a44;
        margin: 0;
        padding: 0 20px 12px;
        border-bottom: 3px solid #c9a227;
        max-width: 100%;
        word-break: break-word;
        overflow-wrap: anywhere;
    }

    .cert2-body { text-align: center; flex: 1 1 auto; min-width: 0; overflow: hidden; max-width: 100%; }
    .cert2-certify { font-size: 13px; letter-spacing: 2px; color: #6c757d; text-transform: uppercase; }
    .cert2-student {
        font-size: 32px;
        font-weight: 800;
        color: #202a44;
        letter-spacing: 1.5px;
        margin: 12px 0 6px;
        text-transform: uppercase;
        word-break: break-word;
        overflow-wrap: anywhere;
        max-width: 100%;
        line-height: 1.1;
    }
    .cert2-guardian { font-size: 15px; color: #495057; margin-bottom: 16px; }
    .cert2-details {
        display: inline-block;
        border: 1px solid rgba(32, 42, 68, .15);
        border-radius: 8px;
        overflow: hidden;
        margin-bottom: 14px;
        text-align: left;
        font-size: 12px;
        max-width: 100%;
        box-sizing: border-box;
    }
    .cert2-details-row {
        display: flex;
        gap: 16px;
        padding: 6px 14px;
        min-width: 0;
    }
    .cert2-details-row + .cert2-details-row { border-top: 1px dashed rgba(32, 42, 68, .12); }
    .cert2-details-row span { color: #6c757d; min-width: 90px; flex-shrink: 0; }
    .cert2-details-row strong { color: #202a44; font-weight: 600; word-break: break-all; overflow-wrap: anywhere; min-width: 0; }
    .cert2-completed { font-size: 14px; color: #6c757d; }
    .cert2-course { font-size: 24px; font-weight: 800; color: #202a44; letter-spacing: 1px; margin: 8px 0; text-transform: uppercase; word-break: break-word; overflow-wrap: anywhere; max-width: 100%; line-height: 1.15; }
    .cert2-fulfilled { font-size: 13px; color: #6c757d; }

    .cert2-subjects { margin: 36px auto 0; max-width: 100%; box-sizing: border-box; }
    .cert2-subjects-label {
        font-size: 11px;
        letter-spacing: 2px;
        color: #c9a227;
        text-transform: uppercase;
        font-weight: 700;
        margin-bottom: 10px;
    }
    .cert2-subject-chips { display: flex; flex-wrap: wrap; justify-content: center; gap: 6px; max-width: 100%; }
    .cert2-subject-chip {
        background: #f4f6fa;
        border: 1px solid rgba(32, 42, 68, .18);
        color: #202a44;
        border-radius: 4px;
        padding: 5px 12px;
        font-size: 12px;
        font-weight: 600;
        max-width: 100%;
        word-break: break-word;
        overflow-wrap: anywhere;
    }

    .cert2-footer {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        gap: 16px;
        margin-top: 18px;
        padding-top: 14px;
        border-top: 1px solid rgba(32, 42, 68, .15);
        flex-wrap: wrap;
        min-width: 0;
    }
    .cert2-qr {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 6px;
    }
    .cert2-qr svg { width: 76px; height: 76px; display: block; }
    .cert2-qr-text { font-size: 11px; color: #6c757d; line-height: 1.4; text-align: center; }
    .cert2-qr-text strong { display: block; color: #202a44; font-size: 13px; }
    .cert2-sign { text-align: center; font-size: 11px; color: #6c757d; flex: 1 1 0; min-width: 120px; max-width: 100%; }
    .cert2-sign .sign-line {
        width: 100%;
        max-width: 180px;
        border-top: 1px solid #adb5bd;
        margin: 0 auto 6px;
        padding-top: 6px;
        font-weight: 700;
        color: #202a44;
        letter-spacing: 1px;
        word-break: break-word;
        overflow-wrap: anywhere;
    }
    .cert2-meta-line {
        text-align: center;
        margin-top: 6px;
        font-size: 12px;
        color: #6c757d;
        letter-spacing: .5px;
    }
    .cert2-meta-line strong { color: #202a44; font-weight: 600; }

    .cert2-watermark {
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

    html.monetix-dark .cert2-sheet { background: #1e1f22; border-color: rgba(255,255,255,.12); }
    html.monetix-dark .cert2-frame { border-color: #c9a227; }
    html.monetix-dark .cert2-institute, html.monetix-dark .cert2-student,
    html.monetix-dark .cert2-qr-text strong, html.monetix-dark .cert2-sign .sign-line,
    html.monetix-dark .cert2-details-row strong, html.monetix-dark .cert2-meta-line strong,
    html.monetix-dark .cert2-badge strong { color: #f8f9fa; }
    html.monetix-dark .cert2-subject-chip { background: rgba(255,255,255,.05); color: #f8f9fa; border-color: rgba(255,255,255,.2); }

    @media print {
        @page { size: A4 landscape; margin:0 !important; }
        html, body { margin:0 !important; padding:0 !important; width:297mm !important; height:210mm !important; overflow:hidden !important; }
        .cert2-sheet {
            box-shadow: none !important;
            border: none !important;
            margin: 0 !important;
            width: 297mm !important;
            max-width: none !important;
            height: 210mm !important;
            aspect-ratio: auto !important;
            padding: 16mm !important;
            border-radius: 0 !important;
            position: absolute !important;
            top: 0 !important;
            left: 0 !important;
        }
        .cert2-watermark { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>

<div class="cert2-sheet">
    @if ($certificate->status === 'revoked')
        <div class="cert2-watermark">REVOKED</div>
    @endif

    <div class="cert2-frame">
        <div class="cert2-header">
            @if ($logoDataUri)
                <img class="cert2-logo" src="{{ $logoDataUri }}" alt="{{ $instituteName }}">
            @elseif ($instituteName)
                <div class="cert2-logo-fallback">{{ $initials }}</div>
            @else
                <div class="cert2-logo"></div>
            @endif

            <div>
                <div class="cert2-institute">{{ $instituteName ?: 'Institute' }}</div>
                @if ($tagline)
                    <div class="cert2-tagline">{{ $tagline }}</div>
                @endif
            </div>

            <div class="cert2-badge">
                @if ($certificate->certificate_number)
                    <strong>Certificate No</strong>{{ $certificate->certificate_number }}
                @endif
                @if ($certificate->issue_date)
                    <strong>Issue Date</strong>{{ $certificate->issue_date->format('d F Y') }}
                @endif
            </div>
        </div>

        <div class="cert2-title">
            <h3>CERTIFICATE OF COMPLETION</h3>
        </div>

        <div class="cert2-body">
            <div class="cert2-certify">This is to certify that</div>
            <h2 class="cert2-student">{{ $studentName ?: 'Student' }}</h2>
            @if ($guardian)
                <div class="cert2-guardian">{{ $guardian }}</div>
            @endif



            <div class="cert2-completed">has successfully completed the prescribed training course</div>
            <div class="cert2-course">{{ $courseName ?: 'Course' }}</div>
            @php $durationText2 = isset($course) && $course && $course->duration_value ? trim(rtrim(rtrim(number_format((float)$course->duration_value, 2), '0'), '.') . ' ' . $course->duration_type) : ''; @endphp
            <div class="cert2-fulfilled">and fulfilled the required {{ $durationText2 ? $durationText2 . ' ' : '' }}training and assessment requirements of the institute.</div>

            @if ($subjects->isNotEmpty())
                @php $orderedSubjects2 = $subjects->sortBy(function($s){ $map=['ARC Welding'=>1,'TIG'=>2,'MIG'=>3]; return $map[$s->name] ?? 99; }); @endphp
                <div class="cert2-subjects">
                    <div class="cert2-subjects-label">Completed Subjects</div>
                    <div class="cert2-subject-chips">
                        @foreach ($orderedSubjects2 as $subject)
                            <span class="cert2-subject-chip">{{ $subject->name }}</span>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <div class="cert2-footer">
            @if ($qrSvg && $verifyUrl)
                <div class="cert2-qr">
                    {!! $qrSvg !!}
                    <div class="cert2-qr-text"><strong>Scan to verify</strong></div>
                </div>
            @else
                <div class="cert2-qr-text">
                    <strong>Not yet issued</strong>
                    This certificate has no verification code yet.
                </div>
            @endif
            <div class="cert2-sign">
                <div class="sign-line">Instructor / Trainer</div>
                Training Department
            </div>
            <div class="cert2-sign">
                <div class="sign-line">{{ $instituteName ?: 'Authorized Issuer' }}</div>
                Authorized Issuer
            </div>
        </div>

        <div class="cert2-meta-line">
            @if ($certificate->issue_date)
                Issue Date: <strong>{{ $certificate->issue_date->format('d F Y') }}</strong>
            @endif
        </div>
    </div>
</div>