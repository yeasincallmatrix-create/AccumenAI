<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\Training\TrainingCertificate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class VerifyCertificateController extends Controller
{
    public function index(): View
    {
        return view('verify.check');
    }

    public function check(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'certificate_number' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9\-]+$/'],
        ]);

        return redirect()->route('verify.certificate', ['certificate_number' => strtoupper($data['certificate_number'])]);
    }

    public function show(string $certificateNumber): View|Response
    {
        $number = strtoupper($certificateNumber);
        $relations = ['student', 'course.subjects', 'batch', 'institute', 'type'];

        $certificate = Certificate::query()
            ->with($relations)
            ->where('certificate_number', $number)
            ->first();

        // Training-center certificates live in their own table but are
        // verified through this same public route (see TrainingCertificateController).
        if ($certificate === null) {
            $certificate = TrainingCertificate::query()
                ->with($relations)
                ->where('certificate_number', $number)
                ->first();
        }

        if ($certificate === null) {
            return response()->view('verify.not_found', [], Response::HTTP_NOT_FOUND);
        }

        return view('verify.certificate', ['certificate' => $certificate]);
    }
}
