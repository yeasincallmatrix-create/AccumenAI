<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Marksheet</title>
    <style>
        @page {
            size: A4 {{ (isset($orientation) && $orientation === 'landscape') ? 'landscape' : 'portrait' }};
            margin: 0;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            margin: 15mm;
            font-size: 12px;
            color: #333;
        }
        @media print {
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .no-print { display: none !important; }
            @page { margin: 0; }
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            font-size: 22px;
        }
        .header p {
            margin: 2px 0;
            font-size: 14px;
        }
        .details {
            margin-bottom: 20px;
        }
        .details table {
            width: 100%;
            font-size: 12px;
        }
        .details td {
            padding: 4px 8px;
        }
        .details .label {
            font-weight: bold;
            width: 30%;
        }
        table.marks {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        table.marks th, table.marks td {
            border: 1px solid #000;
            padding: 6px 8px;
            text-align: center;
        }
        table.marks th {
            background-color: #f0f0f0;
            font-weight: bold;
        }
        .pass {
            color: green;
            font-weight: bold;
        }
        .fail {
            color: red;
            font-weight: bold;
        }
        .footer {
            margin-top: 30px;
            text-align: right;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #ccc;
            padding-top: 10px;
        }
        .signature {
            margin-top: 40px;
            display: flex;
            justify-content: space-between;
        }
        .signature div {
            text-align: center;
        }
        .signature .line {
            border-top: 1px solid #000;
            width: 200px;
            margin-top: 30px;
            padding-top: 5px;
        }
        /* Preserve legacy styling for fallback variables */
        .info table { width: 100%; margin-bottom: 15px; }
        .info td { padding: 4px 8px; }
        .marks-legacy table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .marks-legacy th, .marks-legacy td { border: 1px solid #999; padding: 6px 8px; text-align: center; }
        .marks-legacy th { background: #f0f0f0; }
        /* For landscape, table may need to be wider */
        @media print and (orientation: landscape) {
            .marks td, .marks th {
                padding: 4px 8px;
                font-size: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="no-print" style="text-align:right; margin-bottom:10px;">
        <button onclick="window.print()" style="padding:6px 14px; font-size:13px; cursor:pointer;">Print</button>
    </div>
    @php
        // Resolve institute / batch / student for both new spec and legacy controller variables
        $instituteName = $institute->name ?? $batch->institute->name ?? $batch->institute_name ?? 'Institute';
        $studentName = $student->full_name ?? $student->name ?? trim(($student->first_name ?? '').' '.($student->last_name ?? '')) ?: ($displayName ?? 'Trainee');
        $studentCode = $student->student_id ?? $student->reg_no ?? $student->id ?? ($traineeId ?? 'N/A');
        $batchCourseName = $batch->course->name ?? $course->name ?? 'N/A';
        // Support both examResults (spec) and examDetails (legacy)
        $hasSpecResults = isset($examResults) && $examResults instanceof \Illuminate\Support\Collection && $examResults->isNotEmpty();
    @endphp

    @php $headerInstitute = $institute ?? ($batch->institute ?? null); @endphp
    <div class="header">
        @if($headerInstitute && $headerInstitute->logo_url)
            <img src="{{ $headerInstitute->logo_url }}" alt="{{ $instituteName }}" style="max-height:60px; margin-bottom:10px; object-fit:contain;">
        @endif
        <h1>{{ $instituteName }}</h1>
        <p><strong>Marksheet</strong></p>
        <p>Batch: {{ $batch->name }} ({{ $batchCourseName }})</p>
        <p>Student: {{ $studentName }} (ID: {{ $studentCode }})</p>
    </div>

    <div class="details">
        <table>
            <tr><td class="label">Batch Code:</td><td>{{ $batch->batch_code ?? 'N/A' }}</td></tr>
            <tr><td class="label">Course:</td><td>{{ $batchCourseName }}</td></tr>
            <tr><td class="label">Start Date:</td><td>{{ isset($batch->start_date) ? \Carbon\Carbon::parse($batch->start_date)->format('d M Y') : 'N/A' }}</td></tr>
            <tr><td class="label">End Date:</td><td>{{ isset($batch->end_date) ? \Carbon\Carbon::parse($batch->end_date)->format('d M Y') : 'N/A' }}</td></tr>
            <tr><td class="label">Total Marks:</td><td>{{ $result->total_marks ?? 0 }}</td></tr>
            <tr><td class="label">Obtained Marks:</td><td>{{ $result->obtained_marks ?? 0 }}</td></tr>
            <tr><td class="label">Percentage:</td><td>{{ $result->percentage ?? 0 }}%</td></tr>
            <tr><td class="label">Status:</td>
                <td class="{{ ($result->status ?? '') === 'pass' ? 'pass' : 'fail' }}">
                    {{ ucfirst($result->status ?? 'Pending') }}
                </td>
            </tr>
            @if(isset($result->published_at) && $result->published_at)
            <tr><td class="label">Published:</td><td>{{ \Carbon\Carbon::parse($result->published_at)->format('d M Y') }}</td></tr>
            @endif
            @if(isset($displayName) && $displayName !== $studentName)
            <tr><td class="label">Trainee:</td><td>{{ $displayName }} (ID: {{ $traineeId ?? $studentCode }})</td></tr>
            @endif
        </table>
    </div>

    <h4>Exam-wise Marks</h4>
    @if($hasSpecResults)
    <table class="marks">
        <thead>
            <tr>
                <th>#</th>
                <th>Exam Name</th>
                <th>Date</th>
                <th>Total Marks</th>
                <th>Obtained</th>
                <th>Pass/Fail</th>
            </tr>
        </thead>
        <tbody>
            @forelse($examResults as $index => $examResult)
            @php
                $exam = $examResult->exam ?? null;
                $examName = $exam->title ?? $exam->name ?? 'N/A';
                $examDate = $exam && $exam->exam_date ? \Carbon\Carbon::parse($exam->exam_date)->format('d M Y') : 'N/A';
                $totalMarks = $exam->full_marks ?? 0;
                $obtained = $examResult->marks_obtained ?? $examResult->obtained_marks ?? 0;
                $status = $examResult->result_status ?? ($obtained >= ($totalMarks*0.4) ? 'pass' : 'fail');
            @endphp
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $examName }}</td>
                <td>{{ $examDate }}</td>
                <td>{{ $totalMarks }}</td>
                <td>{{ $obtained }}</td>
                <td class="{{ $status === 'pass' ? 'pass' : 'fail' }}">
                    {{ ucfirst($status) }}
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" style="text-align:center;">No exam records found.</td>
            </tr>
            @endforelse
            @if(isset($result))
            <tr style="font-weight:bold; background:#f9f9f9;">
                <td colspan="3">Total</td>
                <td>{{ $result->total_marks }}</td>
                <td>{{ $result->obtained_marks }}</td>
                <td>{{ $result->percentage }}% ({{ ucfirst($result->status) }})</td>
            </tr>
            @endif
        </tbody>
    </table>
    @else
    {{-- Legacy fallback using examDetails --}}
    <table class="marks">
        <thead>
            <tr><th>#</th><th>Exam / Subject</th><th>Full Marks</th><th>Obtained</th><th>Result</th></tr>
        </thead>
        <tbody>
        @forelse($examDetails ?? [] as $idx => $ex)
            <tr>
                <td>{{ $idx+1 }}</td>
                <td>{{ $ex['title'] }} @if($ex['subjects']) <small>({{ $ex['subjects'] }})</small> @endif</td>
                <td>{{ $ex['full_marks'] }}</td>
                <td>{{ $ex['obtained'] }}</td>
                <td>{{ $ex['obtained'] >= ($ex['full_marks']*0.4) ? 'Pass' : 'Fail' }}</td>
            </tr>
        @empty
            <tr><td colspan="5">No exam details</td></tr>
        @endforelse
        @if(isset($result))
            <tr style="font-weight:bold; background:#f9f9f9;">
                <td colspan="2">Total</td>
                <td>{{ $result->total_marks }}</td>
                <td>{{ $result->obtained_marks }}</td>
                <td>{{ $result->percentage }}% ({{ ucfirst($result->status) }})</td>
            </tr>
        @endif
        </tbody>
    </table>
    @endif

    <div class="signature">
        <div>
            <div class="line">Authorized Signature</div>
        </div>
        <div>
            <div class="line">Date</div>
        </div>
    </div>

    <div class="footer">
        Generated by accumenAI Software Solution ltd.
    </div>
</body>
</html>
