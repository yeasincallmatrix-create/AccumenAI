<?php

namespace App\Http\Controllers\Training;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Certificate;
use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class TrainingCertificateController extends Controller
{
    public function index(Request $request): View
    {
        $instituteId = (int) $request->user()->institute_id;
        $batches = Batch::where('institute_id', $instituteId)
            ->whereIn('status', ['completed', 'ongoing', 'upcoming'])
            ->orderBy('name')
            ->get(['id', 'name', 'batch_code']);

        $selectedBatchId = (int) ($request->query('batch_id') ?? $batches->first()?->id);
        $certTrainees = collect();
        if ($selectedBatchId) {
            $enrolls = \App\Models\Training\Enrollment::where('batch_id', $selectedBatchId)
                ->where('institute_id', $instituteId)
                ->with('student')
                ->get();

            $batch = Batch::find($selectedBatchId);
            $threshold = $batch?->attendance_threshold ?? 80;
            $certTrainees = $enrolls->map(function ($enr) use ($selectedBatchId, $threshold) {
                $traineeId = $enr->trainee_id ?? $enr->student_id;
                $studentId = $traineeId;

                $presentCount = \App\Models\Attendance::where('batch_id', $selectedBatchId)
                    ->where('student_id', $studentId)
                    ->where('status', 'present')
                    ->count();
                $totalDays = \App\Models\Attendance::where('batch_id', $selectedBatchId)
                    ->where('student_id', $studentId)
                    ->count();
                $attendance = $totalDays > 0 ? (int) round(($presentCount / $totalDays) * 100) : 0;

                $exams = Exam::where('batch_id', $selectedBatchId)->get();
                $allPassed = true;
                if ($exams->isEmpty()) {
                    $allPassed = false;
                } else {
                    foreach ($exams as $exam) {
                        $hasPass = ExamResult::where('exam_id', $exam->id)
                            ->where('student_id', $studentId)
                            ->where('result_status', 'pass')
                            ->exists();
                        if (!$hasPass) { $allPassed = false; break; }
                    }
                }
                $examStatus = $allPassed ? 'pass' : 'fail';

                return (object)[
                    'enrollment' => $enr,
                    'trainee' => $enr->trainee ?? $enr->student,
                    'trainee_id' => $traineeId,
                    'student_id' => $studentId,
                    'attendance' => $attendance,
                    'exam_status' => $examStatus,
                    'eligible' => $attendance >= $threshold && $examStatus === 'pass',
                ];
            });
        }

        $certificates = Certificate::where('institute_id', $instituteId)
            ->with(['student','course','batch'])
            ->latest('id')
            ->paginate(15);

        $selectedBatch = $batches->firstWhere('id', $selectedBatchId) ?? ($selectedBatchId ? Batch::find($selectedBatchId) : null);
        $threshold = $selectedBatch ? $selectedBatch->attendance_threshold : 80;

        return view('training.certificates.index', compact(
            'batches',
            'selectedBatchId',
            'certTrainees',
            'certificates',
            'threshold'
        ));
    }

    public function generate(Request $request): RedirectResponse
    {
        $request->validate([
            'batch_id' => 'required|exists:batches,id',
            'trainee_ids' => 'required|array|min:1',
            'trainee_ids.*' => 'exists:students,id',
            'template_id' => 'nullable|integer|in:1,2,3',
        ]);

        $batchId = (int) $request->batch_id;
        $traineeIds = $request->trainee_ids;
        $instituteId = (int) $request->user()->institute_id;
        $templateId = (int) ($request->input('template_id', 1));
        $templateId = in_array($templateId, [1, 2, 3], true) ? $templateId : 1;

        $batch = Batch::where('institute_id', $instituteId)->findOrFail($batchId);
        $courseId = $batch->course_id;

        $generated = 0;
        foreach ($traineeIds as $traineeId) {
            $studentId = $traineeId;
            // Try to resolve training User -> Student via email fallback
            $trainee = \App\Models\User::find($traineeId);
            if ($trainee && $trainee->email) {
                $studentByEmail = Student::where('email', $trainee->email)->where('institute_id', $instituteId)->first();
                if ($studentByEmail) $studentId = $studentByEmail->id;
            }

            // Ensure student exists for FK
            $student = Student::find($studentId);
            if (!$student) {
                continue;
            }

            // Check eligibility again
            $presentCount = \App\Models\Attendance::where('batch_id', $batchId)->where('student_id', $studentId)->where('status','present')->count();
            $totalDays = \App\Models\Attendance::where('batch_id', $batchId)->where('student_id', $studentId)->count();
            $attendance = $totalDays > 0 ? (int) round(($presentCount/$totalDays)*100) : 0;

            $exams = Exam::where('batch_id', $batchId)->get();
            $allPassed = !$exams->isEmpty();
            foreach ($exams as $exam) {
                if (!ExamResult::where('exam_id', $exam->id)->where('student_id', $studentId)->where('result_status','pass')->exists()) {
                    $allPassed = false; break;
                }
            }
            $threshold = $batch->attendance_threshold ?? 80;
            if (!($attendance >= $threshold && $allPassed)) {
                continue;
            }

            // Avoid duplicate certificate for same student/batch/course
            $exists = Certificate::where('institute_id', $instituteId)->where('student_id', $studentId)->where('batch_id', $batchId)->where('course_id', $courseId)->exists();
            if ($exists) continue;

            // Resolve issued_by safely — nullable FK to institute_users (same pattern as Attendance marked_by)
            $issuedBy = null;
            $user = $request->user();
            if ($user instanceof \App\Models\InstituteUser) {
                $issuedBy = $user->id;
            } elseif ($user) {
                $membership = \Illuminate\Support\Facades\DB::table('institution_user')
                    ->where('user_id', $user->id ?? \Illuminate\Support\Facades\Auth::id())
                    ->where('institution_id', $instituteId)
                    ->where('status', 'active')
                    ->first();
                if ($membership && !empty($membership->legacy_institute_user_id)) {
                    $issuedBy = $membership->legacy_institute_user_id;
                }
            }

            $certificate = Certificate::create([
                'institute_id' => $instituteId,
                'student_id' => $studentId,
                'batch_id' => $batchId,
                'course_id' => $courseId,
                'issue_date' => now()->toDateString(),
                'status' => 'active',
                'issued_by' => $issuedBy,
                'template_id' => $templateId,
            ]);
            // Generate unique number after create (needs id/uuid)
            $certificate->update(['certificate_number' => Certificate::numberFor($certificate)]);
            $generated++;
        }

        return redirect()->back()->with('status', $generated > 0 ? "Generated $generated certificate(s) successfully." : 'No eligible certificates generated (already issued or not eligible).');
    }

    public function show(Request $request, Certificate $certificate): View
    {
        $instituteId = (int) $request->user()->institute_id;
        abort_unless((int) $certificate->institute_id === $instituteId, 403);

        $certificate->load(['student','course.subjects','batch','institute']);

        $templateCount = 3;
        $stored = (int) ($certificate->template_id ?? 1);
        $stored = in_array($stored, [1,2,3], true) ? $stored : 1;
        $requested = $request->query('template');
        if ($requested !== null && $requested !== '') {
            $t = (int) $requested;
            $template = in_array($t, [1,2,3], true) ? $t : $stored;
        } else {
            $template = $stored;
        }

        $verifyUrl = $certificate->certificate_number ? route('verify.certificate', $certificate->certificate_number) : null;
        $qrSvg = $verifyUrl ? qr_svg($verifyUrl, 6) : null;

        $logoDataUri = null;
        $instituteLogoPath = $certificate->institute?->logo_path_resolved ?? $certificate->institute?->logo_path ?? $certificate->institute?->logo ?? null;
        if (!empty($instituteLogoPath)) {
            $path = $instituteLogoPath;
            try {
                if (\Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
                    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                    if (in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'], true)) {
                        $mime = $ext === 'svg' ? 'svg+xml' : $ext;
                        $mime = $mime === 'jpg' ? 'jpeg' : $mime;
                        $logoDataUri = 'data:image/'.$mime.';base64,'.base64_encode((string) \Illuminate\Support\Facades\Storage::disk('public')->get($path));
                    }
                } elseif (is_file($path)) {
                    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                    if (in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'], true)) {
                        $mime = $ext === 'svg' ? 'svg+xml' : $ext;
                        $mime = $mime === 'jpg' ? 'jpeg' : $mime;
                        $logoDataUri = 'data:image/'.$mime.';base64,'.base64_encode((string) file_get_contents($path));
                    }
                } elseif (is_file(public_path($path)) || is_file(public_path('storage/'.$path))) {
                    $real = is_file(public_path($path)) ? public_path($path) : public_path('storage/'.$path);
                    $ext = strtolower(pathinfo($real, PATHINFO_EXTENSION));
                    if (in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'], true)) {
                        $mime = $ext === 'svg' ? 'svg+xml' : $ext;
                        $mime = $mime === 'jpg' ? 'jpeg' : $mime;
                        $logoDataUri = 'data:image/'.$mime.';base64,'.base64_encode((string) file_get_contents($real));
                    }
                }
            } catch (\Throwable $e) {}
        }

        $student = $certificate->student;
        $course = $certificate->course;
        $institute = $certificate->institute;
        $studentName = strtoupper(trim($student?->full_name ?? ''));
        $courseName = strtoupper(trim($course?->name ?? ''));
        $guardian = null;
        if (! empty($student?->father_name)) {
            $guardianLabel = match (strtolower((string) $student->gender)) {
                'male' => 'Son of',
                'female' => 'Daughter of',
                default => 'Child of',
            };
            $guardian = $guardianLabel.' '.trim($student->father_name);
        }
        $instituteName = strtoupper(trim($institute?->name ?? ''));
        $tagline = trim($institute?->short_name ?? '');
        $initials = strtoupper(substr($instituteName, 0, 1) ?: 'A');
        $subjects = $certificate->course?->subjects ?? collect();

        return view('training.certificates.show', [
            'certificate' => $certificate,
            'student' => $student,
            'course' => $course,
            'batch' => $certificate->batch,
            'institute' => $institute,
            'studentName' => $studentName,
            'courseName' => $courseName,
            'guardian' => $guardian,
            'instituteName' => $instituteName,
            'tagline' => $tagline,
            'initials' => $initials,
            'verifyUrl' => $verifyUrl,
            'qrSvg' => $qrSvg,
            'logoDataUri' => $logoDataUri,
            'subjects' => $subjects,
            'template' => $template,
            'templateCount' => $templateCount,
        ]);
    }

    public function download(Request $request, Certificate $certificate)
    {
        $instituteId = (int) $request->user()->institute_id;
        abort_unless((int) $certificate->institute_id === $instituteId, 403);

        $certificate->load(['student','course','batch','type','institute']);

        $template = (int) ($certificate->template_id ?? 1);
        $template = in_array($template, [1, 2, 3], true) ? $template : 1;

        $verifyUrl = $certificate->certificate_number ? route('verify.certificate', $certificate->certificate_number) : null;
        $qrSvg = $verifyUrl ? qr_svg($verifyUrl, 6) : null;

        $logoDataUri = null;
        $instituteLogoPath = $certificate->institute?->logo_path_resolved ?? $certificate->institute?->logo_path ?? $certificate->institute?->logo ?? null;
        if (!empty($instituteLogoPath)) {
            $path = $instituteLogoPath;
            try {
                if (\Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
                    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                    if (in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'], true)) {
                        $mime = $ext === 'svg' ? 'svg+xml' : $ext;
                        $mime = $mime === 'jpg' ? 'jpeg' : $mime;
                        $logoDataUri = 'data:image/'.$mime.';base64,'.base64_encode((string) \Illuminate\Support\Facades\Storage::disk('public')->get($path));
                    }
                } elseif (is_file($path)) {
                    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                    if (in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'], true)) {
                        $mime = $ext === 'svg' ? 'svg+xml' : $ext;
                        $mime = $mime === 'jpg' ? 'jpeg' : $mime;
                        $logoDataUri = 'data:image/'.$mime.';base64,'.base64_encode((string) file_get_contents($path));
                    }
                } elseif (is_file(public_path($path)) || is_file(public_path('storage/'.$path))) {
                    $real = is_file(public_path($path)) ? public_path($path) : public_path('storage/'.$path);
                    $ext = strtolower(pathinfo($real, PATHINFO_EXTENSION));
                    if (in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'], true)) {
                        $mime = $ext === 'svg' ? 'svg+xml' : $ext;
                        $mime = $mime === 'jpg' ? 'jpeg' : $mime;
                        $logoDataUri = 'data:image/'.$mime.';base64,'.base64_encode((string) file_get_contents($real));
                    }
                }
            } catch (\Throwable $e) {}
        }

        // Prepare shared variables expected by admin templates
        $student = $certificate->student;
        $course = $certificate->course;
        $institute = $certificate->institute;
        $studentName = strtoupper(trim($student?->full_name ?? ''));
        $courseName = strtoupper(trim($course?->name ?? ''));
        $guardian = null;
        if (! empty($student?->father_name)) {
            $guardianLabel = match (strtolower((string) $student->gender)) {
                'male' => 'Son of',
                'female' => 'Daughter of',
                default => 'Child of',
            };
            $guardian = $guardianLabel.' '.trim($student->father_name);
        }
        $instituteName = strtoupper(trim($institute?->name ?? ''));
        $tagline = trim($institute?->short_name ?? '');
        $initials = strtoupper(substr($instituteName, 0, 1) ?: 'A');
        $subjects = $certificate->course?->subjects ?? collect();

        $view = 'admin.certificates._template'.$template;
        $html = view($view, ['certificate'=>$certificate,'student'=>$student,'course'=>$course,'batch'=>$certificate->batch,'institute'=>$institute,'studentName'=>$studentName,'courseName'=>$courseName,'guardian'=>$guardian,'instituteName'=>$instituteName,'tagline'=>$tagline,'initials'=>$initials,'verifyUrl'=>$verifyUrl,'qrSvg'=>$qrSvg,'logoDataUri'=>$logoDataUri,'subjects'=>$subjects])->render();

        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
            $pdf->setPaper('a4', 'landscape');
            return $pdf->download('certificate-'.($certificate->certificate_number ?? $certificate->id).'.pdf');
        }

        return response($html)
            ->header('Content-Type', 'text/html')
            ->header('Content-Disposition', 'attachment; filename="certificate-'.($certificate->certificate_number ?? $certificate->id).'.html"');
    }

    public function update(Request $request, Certificate $certificate)
    {
        $instituteId = (int) $request->user()->institute_id;
        abort_unless((int) $certificate->institute_id === $instituteId, 403);

        $data = $request->validate([
            'issue_date' => ['required', 'date'],
            'full_name' => ['nullable', 'string', 'max:120'],
            'father_name' => ['nullable', 'string', 'max:120'],
            'nid_number' => ['nullable', 'string', 'max:30'],
            'passport_number' => ['nullable', 'string', 'max:40'],
            'course_name' => ['nullable', 'string', 'max:150'],
        ]);

        $certificate->update([
            'issue_date' => $data['issue_date'],
        ]);

        $student = $certificate->student;
        if ($student) {
            $studentData = [];
            if (array_key_exists('full_name', $data) && $data['full_name'] !== null && trim($data['full_name']) !== '') {
                $student->full_name = trim($data['full_name']);
            }
            if (array_key_exists('father_name', $data)) $studentData['father_name'] = $data['father_name'];
            if (array_key_exists('nid_number', $data)) $studentData['nid_number'] = $data['nid_number'];
            if (array_key_exists('passport_number', $data)) $studentData['passport_number'] = $data['passport_number'];
            if (!empty($studentData) || $student->isDirty()) {
                if (!empty($studentData)) $student->fill($studentData);
                $student->save();
            }
        }

        if (!empty($data['course_name']) && $certificate->course) {
            $certificate->course->update(['name' => trim($data['course_name'])]);
        }

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Certificate updated.',
                'data' => ['issue_date' => $certificate->issue_date->format('Y-m-d')],
            ]);
        }

        return redirect()
            ->route('training.certificates.index', ['batch_id' => $certificate->batch_id])
            ->with('status', 'Certificate updated — issue date '.$certificate->issue_date->format('d M Y').'.');
    }

    public function downloadQr(Request $request, Certificate $certificate)
    {
        $instituteId = (int) $request->user()->institute_id;
        abort_unless((int) $certificate->institute_id === $instituteId, 403);
        abort_unless((bool) $certificate->certificate_number, 404);

        $verifyUrl = route('verify.certificate', $certificate->certificate_number);
        $svg = qr_svg($verifyUrl, 8);
        $filename = ($certificate->certificate_number ?? 'certificate').'.svg';

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
