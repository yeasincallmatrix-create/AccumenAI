<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Institute;
use App\Models\Notification;
use App\Services\Notification\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Support\PasswordHash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CertificateAdminController extends Controller
{
    public const CERTIFICATES_COLUMNS = [
        'serial', 'certificate_no', 'student', 'course', 'batch', 'institute', 'issue_date', 'status', 'design', 'remarks', 'qr', 'action',
    ];

    public const CERTIFICATE_REQUESTS_COLUMNS = [
        'serial', 'institute', 'student', 'course', 'batch', 'requested_at', 'status', 'remarks', 'action',
    ];

    public function index(Request $request): View
    {
        $institutes = Institute::query()
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name']);

        $query = Certificate::query()
            ->with(['student', 'course', 'batch', 'institute'])
            ->whereIn('status', ['active', 'revoked'])
            ->when($request->query('q'), function ($query, string $q) {
                $query->where(function ($query) use ($q) {
                    $query->whereHas('student', function ($query) use ($q) {
                        $query->where('first_name', 'like', "%{$q}%")
                            ->orWhere('last_name', 'like', "%{$q}%");
                    })
                        ->orWhere('certificate_number', 'like', "%{$q}%")
                        ->orWhereHas('course', fn ($query) => $query->where('name', 'like', "%{$q}%"));
                });
            })
            ->when($request->query('institute_id'), fn ($query, $id) => $query->where('institute_id', (int) $id))
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->latest('id');

        $items = (clone $query)->paginate(20)->withQueryString();

        $allItems = (clone $query)->get();

        $visibleColumns = $request->user()->preference('certificates_columns', self::CERTIFICATES_COLUMNS);
        $visibleColumns = array_values(array_intersect(self::CERTIFICATES_COLUMNS, (array) $visibleColumns));

        return view('admin.certificates.index', [
            'items' => $items,
            'allItems' => $allItems,
            'institutes' => $institutes,
            'selectedInstituteId' => (int) $request->query('institute_id'),
            'visibleColumns' => $visibleColumns,
            'requestsCount' => Certificate::query()->whereIn('status', ['pending', 'rejected'])->count(),
            'certificatesCount' => Certificate::query()->whereIn('status', ['active', 'revoked'])->count(),
            'filters' => [
                'q' => $request->query('q'),
                'institute_id' => $request->query('institute_id'),
                'status' => $request->query('status'),
            ],
        ]);
    }

    public function requests(Request $request): View
    {
        $institutes = Institute::query()
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name']);

        $query = Certificate::query()
            ->with(['student', 'course', 'batch', 'institute'])
            ->whereIn('status', ['pending', 'rejected'])
            ->when($request->query('q'), function ($query, string $q) {
                $query->where(function ($query) use ($q) {
                    $query->whereHas('institute', fn ($query) => $query->where('name', 'like', "%{$q}%"))
                        ->orWhereHas('student', function ($query) use ($q) {
                            $query->where('first_name', 'like', "%{$q}%")
                                ->orWhere('last_name', 'like', "%{$q}%");
                        })
                        ->orWhereHas('course', fn ($query) => $query->where('name', 'like', "%{$q}%"));
                });
            })
            ->when($request->query('institute_id'), fn ($query, $id) => $query->where('institute_id', (int) $id))
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->latest('id');

        $items = (clone $query)->paginate(20)->withQueryString();

        $allItems = (clone $query)->get();

        $visibleColumns = $request->user()->preference('certificate_requests_columns', self::CERTIFICATE_REQUESTS_COLUMNS);
        $visibleColumns = array_values(array_intersect(self::CERTIFICATE_REQUESTS_COLUMNS, (array) $visibleColumns));

        return view('admin.certificates.requests', [
            'items' => $items,
            'allItems' => $allItems,
            'institutes' => $institutes,
            'selectedInstituteId' => (int) $request->query('institute_id'),
            'visibleColumns' => $visibleColumns,
            'requestsCount' => Certificate::query()->whereIn('status', ['pending', 'rejected'])->count(),
            'certificatesCount' => Certificate::query()->whereIn('status', ['active', 'revoked'])->count(),
            'filters' => [
                'q' => $request->query('q'),
                'institute_id' => $request->query('institute_id'),
                'status' => $request->query('status'),
            ],
        ]);
    }

    public function saveColumns(Request $request): JsonResponse
    {
        $data = $request->validate([
            'columns' => ['nullable', 'array'],
            'columns.*' => ['string'],
        ]);

        $columns = array_values(array_intersect(self::CERTIFICATES_COLUMNS, $data['columns'] ?? []));
        $request->user()->setPreference('certificates_columns', $columns);

        return response()->json(['ok' => true, 'columns' => $columns]);
    }

    public function saveRequestsColumns(Request $request): JsonResponse
    {
        $data = $request->validate([
            'columns' => ['nullable', 'array'],
            'columns.*' => ['string'],
        ]);

        $columns = array_values(array_intersect(self::CERTIFICATE_REQUESTS_COLUMNS, $data['columns'] ?? []));
        $request->user()->setPreference('certificate_requests_columns', $columns);

        return response()->json(['ok' => true, 'columns' => $columns]);
    }

    public function updateTemplate(Request $request, Certificate $certificate): JsonResponse
    {
        $request->validate(['template_id' => 'required|integer|in:1,2,3']);
        $certificate->template_id = (int) $request->input('template_id');
        $certificate->save();

        return response()->json(['success' => true, 'template_id' => $certificate->template_id]);
    }

    public function show(Request $request, Certificate $certificate): View
    {
        $certificate->load(['student', 'course.subjects', 'batch', 'institute']);

        // Prefer stored template_id; query param allows preview override
        $stored = (int) ($certificate->template_id ?? 1);
        $stored = in_array($stored, [1, 2, 3], true) ? $stored : 1;
        $requested = $request->query('template');
        if ($requested !== null) {
            $template = (int) $requested;
            $template = in_array($template, [1, 2, 3], true) ? $template : $stored;
        } else {
            $template = $stored;
        }

        $verifyUrl = $certificate->certificate_number
            ? route('verify.certificate', $certificate->certificate_number)
            : null;

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

        return view('admin.certificates.show', [
            'certificate' => $certificate,
            'template' => $template,
            'templateCount' => 3,
            'verifyUrl' => $verifyUrl,
            'qrSvg' => $verifyUrl ? qr_svg($verifyUrl, 6) : null,
            'logoDataUri' => $logoDataUri,
            'subjects' => $certificate->course?->subjects ?? collect(),
        ]);
    }

    public function downloadQr(Certificate $certificate): Response
    {
        abort_unless((bool) $certificate->certificate_number, 404);

        $verifyUrl = route('verify.certificate', $certificate->certificate_number);
        $svg = qr_svg($verifyUrl, 8);
        $filename = ($certificate->certificate_number ?? 'certificate').'.svg';

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function destroy(Request $request, Certificate $certificate): RedirectResponse|JsonResponse
    {
        $certificate->delete();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Certificate moved to the recycle bin.',
            ]);
        }

        return redirect()->back()->with('status', 'Certificate moved to the recycle bin.');
    }

    public function restore(Request $request, Certificate $certificate): RedirectResponse|JsonResponse
    {
        if (! $certificate->trashed()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Certificate is not in the recycle bin.',
                ], 422);
            }

            return redirect()->route('admin.institutes.bin')->with('status', 'Certificate is not in the recycle bin.');
        }

        $certificate->restore();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Certificate restored.',
            ]);
        }

        return redirect()->route('admin.institutes.bin')->with('status', 'Certificate restored.');
    }

    public function forceDelete(Request $request, Certificate $certificate): RedirectResponse|JsonResponse
    {
        $request->validate(['password' => ['required', 'string']]);

        $admin = $request->user();
        if (! PasswordHash::safeCheck((string) $request->input('password'), (string) $admin->getAuthPassword())) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your password is incorrect.',
                    'errors' => ['password' => ['Your password is incorrect.']],
                ], 422);
            }

            return back()->withErrors(['password' => 'Your password is incorrect.']);
        }

        $certificate->forceDelete();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Certificate permanently deleted.',
            ]);
        }

        return redirect()->route('admin.institutes.bin')->with('status', 'Certificate permanently deleted.');
    }

    public function action(Request $request, Certificate $certificate): RedirectResponse|JsonResponse
    {
        $request->validate([
            'action' => ['required', Rule::in(['approve', 'reject', 'revoke', 'revoke-cancel'])],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $action = $request->input('action');
        $admin = $request->user();
        $reason = $request->input('reason');

        if ($action === 'approve') {
            $certificate->update([
                'status' => 'active',
                'certificate_number' => $certificate->certificate_number ?? $this->certificateNumber($certificate),
                'issue_date' => $certificate->issue_date ?? now()->toDateString(),
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'review_note' => $reason,
            ]);

            $this->notifyInstitute(
                $certificate->institute_id,
                'certificate',
                'Certificate approved',
                'Certificate for '.($certificate->student->full_name ?? 'student').' has been approved.'
            );

            app(NotificationService::class)->send('education.certificate_approved', $certificate->student, [
                'student_name' => $certificate->student->full_name ?: $certificate->student->first_name,
                'reg_no' => $certificate->student->reg_no,
                'course_name' => $certificate->course?->name,
                'certificate_number' => $certificate->certificate_number,
            ], [
                'actor_type' => 'platform_admin',
                'actor_id' => $admin->id,
                'link' => route('students.show', $certificate->student_id),
            ]);

            return $this->respond($request, 'Certificate approved and issued.', route('admin.certificates.requests'));
        }

        if ($action === 'reject') {
            $certificate->update([
                'status' => 'rejected',
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'review_note' => $reason,
            ]);

            $this->notifyInstitute(
                $certificate->institute_id,
                'certificate',
                'Certificate rejected',
                'Certificate for '.($certificate->student->full_name ?? 'student').' was rejected.'
            );

            return $this->respond($request, 'Certificate rejected.', route('admin.certificates.requests'));
        }

        if ($action === 'revoke-cancel') {
            $certificate->update([
                'status' => 'active',
                'revoked_reason' => null,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'review_note' => $reason,
            ]);

            $this->notifyInstitute(
                $certificate->institute_id,
                'certificate',
                'Certificate revocation cancelled',
                'Certificate for '.($certificate->student->full_name ?? 'student').' has been restored.'
            );

            return $this->respond($request, 'Certificate revocation cancelled.', route('admin.certificates.index'));
        }

        $certificate->update([
            'status' => 'revoked',
            'revoked_reason' => $reason,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);

        $this->notifyInstitute(
            $certificate->institute_id,
            'certificate',
            'Certificate revoked',
            'Certificate for '.($certificate->student->full_name ?? 'student').' has been revoked.'
        );

        return $this->respond($request, 'Certificate revoked.', route('admin.certificates.index'));
    }

    protected function respond(Request $request, string $message, string $redirectTo): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
            ]);
        }

        return redirect($redirectTo)->with('status', $message);
    }

    protected function certificateNumber(Certificate $certificate): string
    {
        return Certificate::numberFor($certificate);
    }

    protected function notifyInstitute(int $instituteId, string $category, string $title, string $message): void
    {
        Notification::create([
            'scope' => 'institute',
            'institute_id' => $instituteId,
            'category' => $category,
            'title' => $title,
            'message' => $message,
            'created_by_type' => 'platform_admin',
            'created_by_id' => auth('platform_admin')->id(),
            'created_at' => now(),
        ]);
    }
}
