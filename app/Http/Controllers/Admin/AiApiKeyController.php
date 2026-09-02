<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiApiKey;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AiApiKeyController extends Controller
{
    public function index(): View
    {
        $keys = AiApiKey::orderBy('provider')->orderBy('name')->get();
        return view('admin.settings._ai_api_keys', compact('keys'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'provider' => 'required|in:openai,anthropic,gemini,groq,custom',
            'capability' => 'required|in:text,image,vision,embeddings,speech,audio,moderation,other',
            'name' => 'nullable|string|max:100',
            'api_key' => 'required|string|min:10',
            'base_url' => 'nullable|url|max:500',
            'model' => 'nullable|string|max:100',
            'is_active' => 'sometimes|boolean',
        ]);

        $data['is_active'] = $request->has('is_active') && $request->boolean('is_active');

        AiApiKey::create($data);

        return redirect()->back()->with('status', 'API key added successfully.');
    }

    public function update(Request $request, AiApiKey $key): RedirectResponse
    {
        $data = $request->validate([
            'capability' => 'required|in:text,image,vision,embeddings,speech,audio,moderation,other',
            'name' => 'nullable|string|max:100',
            'api_key' => 'nullable|string|min:10',
            'base_url' => 'nullable|url|max:500',
            'model' => 'nullable|string|max:100',
            'is_active' => 'sometimes|boolean',
        ]);

        $update = $request->only(['capability', 'name', 'base_url', 'model']);
        // handle is_active checkbox
        $update['is_active'] = $request->has('is_active') ? $request->boolean('is_active') : false;

        if ($request->filled('api_key')) {
            $update['api_key'] = $request->input('api_key');
        }

        $update['capability'] = $data['capability'] ?? $key->capability;
        $update['name'] = $data['name'] ?? $key->name;
        $update['base_url'] = $data['base_url'] ?? $key->base_url;
        $update['model'] = $data['model'] ?? $key->model;

        $key->update($update);

        return redirect()->back()->with('status', 'API key updated successfully.');
    }

    public function destroy(AiApiKey $key): RedirectResponse
    {
        $key->delete();
        return redirect()->back()->with('status', 'API key deleted.');
    }

    public function toggleActive(AiApiKey $key): RedirectResponse
    {
        $key->is_active = !$key->is_active;
        $key->save();
        return redirect()->back()->with('status', 'API key status toggled.');
    }

    public function configure(AiApiKey $key): RedirectResponse
    {
        Setting::set('ai.enabled', '1');
        Setting::set('ai.provider', $key->provider);

        if ($key->model) {
            Setting::set('ai.model', $key->model);
            Setting::set("ai.model_{$key->provider}", $key->model);
        } else {
            Setting::set('ai.model', config("ai.providers.{$key->provider}.model", ''));
            Setting::set("ai.model_{$key->provider}", config("ai.providers.{$key->provider}.model", ''));
        }

        if ($key->base_url) {
            Setting::set('ai.base_url', $key->base_url);
            Setting::set("ai.base_url_{$key->provider}", $key->base_url);
        } else {
            Setting::set('ai.base_url', config("ai.providers.{$key->provider}.base_url", ''));
            Setting::set("ai.base_url_{$key->provider}", config("ai.providers.{$key->provider}.base_url", ''));
        }

        if ($key->api_key) {
            Setting::set('ai.api_key', $key->api_key);
            Setting::set("ai.api_key_{$key->provider}", $key->api_key);
        }

        AiApiKey::where('provider', $key->provider)->update(['is_active' => false]);
        $key->is_active = true;
        $key->save();

        return redirect()->back()->with('status', 'AI configured successfully using key: ' . ($key->name ?? $key->provider));
    }
}
