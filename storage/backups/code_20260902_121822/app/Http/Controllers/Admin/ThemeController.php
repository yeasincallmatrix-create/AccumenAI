<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Theme;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ThemeController extends Controller
{
    public function index(): View
    {
        return view('admin.themes.index', [
            'themes' => Theme::query()->orderByDesc('is_default')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Theme $theme): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:100', Rule::unique('themes', 'slug')->ignore($theme->id)],
            'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_dark' => ['sometimes', 'boolean'],
            'status' => ['required', 'in:active,inactive'],
            'is_default' => ['sometimes', 'boolean'],
        ]);

        $theme->name = $data['name'];
        $theme->slug = ($data['slug'] ?? null) ?: Str::slug($data['name']);
        $theme->primary_color = $data['primary_color'];
        $theme->secondary_color = $data['secondary_color'];
        $theme->is_dark = ! empty($data['is_dark']) ? 1 : 0;
        $theme->status = $data['status'];
        $theme->is_default = 0;

        if (! empty($data['is_default'])) {
            Theme::query()->whereKeyNot($theme->id)->update(['is_default' => 0]);
            $theme->is_default = 1;
        }

        $theme->save();

        return redirect(route('admin.industry-settings'))->with('status', "Theme \"{$theme->name}\" updated.");
    }
}
