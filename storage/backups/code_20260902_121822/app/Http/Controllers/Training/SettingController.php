<?php

namespace App\Http\Controllers\Training;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class SettingController extends Controller
{
    public function index(Request $request): View
    {
        $institute = $request->user()->institute ?? \App\Models\Institute::find($request->user()->institute_id);
        if (!$institute) {
            abort(404);
        }
        $config = $institute->settings?->training_config ?? [];
        if (!is_array($config)) {
            $config = [];
        }
        // Ensure defaults
        $defaults = [
            'enable_courses' => true,
            'enable_batches' => true,
            'enable_enrollment' => true,
            'enable_attendance' => true,
            'enable_exams' => true,
            'enable_certificates' => true,
        ];
        $config = array_merge($defaults, $config);

        return view('training.settings.index', compact('config', 'institute'));
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'enable_courses' => 'nullable|boolean',
            'enable_batches' => 'nullable|boolean',
            'enable_enrollment' => 'nullable|boolean',
            'enable_attendance' => 'nullable|boolean',
            'enable_exams' => 'nullable|boolean',
            'enable_certificates' => 'nullable|boolean',
        ]);

        // Normalize to bool with defaults
        $data = [
            'enable_courses' => (bool) ($validated['enable_courses'] ?? $request->input('enable_courses', 0)),
            'enable_batches' => (bool) ($validated['enable_batches'] ?? $request->input('enable_batches', 0)),
            'enable_enrollment' => (bool) ($validated['enable_enrollment'] ?? $request->input('enable_enrollment', 0)),
            'enable_attendance' => (bool) ($validated['enable_attendance'] ?? $request->input('enable_attendance', 0)),
            'enable_exams' => (bool) ($validated['enable_exams'] ?? $request->input('enable_exams', 0)),
            'enable_certificates' => (bool) ($validated['enable_certificates'] ?? $request->input('enable_certificates', 0)),
        ];
        // Handle hidden 0 inputs when checkbox unchecked
        foreach (['enable_courses','enable_batches','enable_enrollment','enable_attendance','enable_exams','enable_certificates'] as $key) {
            $data[$key] = $request->has($key) ? (bool) $request->boolean($key) : false;
            // If using hidden 0, boolean will be correct
            if ($request->input($key) === '0' || $request->input($key) === 0) {
                // already handled by boolean, but ensure unchecked is false
            }
        }

        $institute = $request->user()->institute ?? \App\Models\Institute::find($request->user()->institute_id);
        if (!$institute) {
            abort(404);
        }
        $settings = $institute->settings;
        if (!$settings) {
            $settings = \App\Models\InstituteSetting::create([
                'institute_id' => $institute->id,
                'training_config' => $data,
            ]);
        } else {
            $settings->update(['training_config' => $data]);
        }

        return redirect()->route('training.settings.index')->with('status', 'Training settings updated.');
    }
}
