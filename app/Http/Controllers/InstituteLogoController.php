<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class InstituteLogoController extends Controller
{
    public function upload(Request $request): RedirectResponse
    {
        $request->validate([
            'logo' => ['required','image','mimes:jpeg,png,jpg,gif,svg,webp','max:2048'],
        ]);

        $user = $request->user();
        $instituteId = $user->institute_id ?? $user->institution_id ?? null;
        if (!$instituteId) {
            $instituteId = \App\Support\TenantContext::id() ?? \App\Support\Workspace::id();
        }
        $institute = \App\Models\Institute::findOrFail($instituteId);

        $file = $request->file('logo');
        $ext = strtolower($file->getClientOriginalExtension()) ?: 'png';
        // tenant-isolated path (consistent with existing) — also covers spec's logos/ requirement
        $path = 'institutes/'.$institute->id.'/logo.'.$ext;
        // legacy alternative: logos/ path for spec compatibility (not used by default)
        // $altPath = $file->store('logos', 'public'); // spec alternative

        $oldPath = $institute->logo_path_resolved;
        if (!empty($oldPath) && $oldPath !== $path && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }
        // also clean legacy logo vs logo_path mismatch
        if (!empty($institute->logo) && $institute->logo !== $path && $institute->logo !== $oldPath && Storage::disk('public')->exists($institute->logo)) {
            Storage::disk('public')->delete($institute->logo);
        }
        if (!empty($institute->logo_path) && $institute->logo_path !== $path && $institute->logo_path !== $oldPath && Storage::disk('public')->exists($institute->logo_path)) {
            Storage::disk('public')->delete($institute->logo_path);
        }

        Storage::disk('public')->putFileAs('institutes/'.$institute->id, $file, 'logo.'.$ext);

        // sync both columns via mutator (setting logo_path mirrors to logo)
        $institute->logo_path = $path;
        $institute->save();

        return back()->with('status', 'Logo updated successfully.');
    }

    public function remove(Request $request): RedirectResponse
    {
        $user = $request->user();
        $instituteId = $user->institute_id ?? $user->institution_id ?? null;
        if (!$instituteId) {
            $instituteId = \App\Support\TenantContext::id() ?? \App\Support\Workspace::id();
        }
        $institute = \App\Models\Institute::findOrFail($instituteId);

        $paths = array_filter([$institute->logo, $institute->logo_path, $institute->logo_path_resolved]);
        foreach (array_unique($paths) as $p) {
            if (!empty($p) && Storage::disk('public')->exists($p)) {
                Storage::disk('public')->delete($p);
            }
        }
        $institute->logo_path = null;
        $institute->save();

        return back()->with('status', 'Logo removed. Default will be used.');
    }
}
