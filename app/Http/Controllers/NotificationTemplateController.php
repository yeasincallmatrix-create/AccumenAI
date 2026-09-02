<?php

namespace App\Http\Controllers;

use App\Models\InstituteUser;
use App\Models\NotificationTemplate;
use App\Models\User;
use App\Support\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Notification template management.
 *
 * Global defaults (institute_id = NULL) are read-only; institutes create,
 * edit, toggle and delete their own overrides, which take precedence at send
 * time. Cross-institute records are rejected outright.
 */
class NotificationTemplateController extends Controller
{
    public function index(Request $request): View
    {
        $instituteId = $this->resolveInstituteId($request->user());

        $query = NotificationTemplate::query()
            ->where(fn ($q) => $q->whereNull('institute_id')->orWhere('institute_id', $instituteId));

        if ($event = $request->query('event')) {
            $query->where('event', $event);
        }
        if ($channel = $request->query('channel')) {
            $query->where('channel', $channel);
        }
        if ($language = $request->query('language')) {
            $query->where('language', $language);
        }

        return view('settings.notifications.templates', [
            'templates' => $query->orderBy('event')->orderBy('channel')->orderBy('language')->get(),
            'events' => config('notifications.events', []),
            'instituteId' => $instituteId,
            'filters' => [
                'event' => $request->query('event'),
                'channel' => $request->query('channel'),
                'language' => $request->query('language'),
            ],
        ]);
    }

    public function create(Request $request): View
    {
        return view('settings.notifications.template-form', [
            'template' => new NotificationTemplate(['institute_id' => $this->resolveInstituteId($request->user()), 'is_active' => true]),
            'events' => config('notifications.events', []),
            'channels' => config('notifications.channels', []),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $instituteId = $this->resolveInstituteId($request->user());
        abort_unless($instituteId !== null, 403);

        $data = $this->validated($request);

        $exists = NotificationTemplate::query()
            ->where('institute_id', $instituteId)
            ->where('event', $data['event'])
            ->where('channel', $data['channel'])
            ->where('language', $data['language'])
            ->exists();

        if ($exists) {
            return back()->withErrors(['language' => 'An override already exists for this event, channel and language.']);
        }

        NotificationTemplate::create([...$data, 'institute_id' => $instituteId]);

        return redirect()
            ->route('settings.notifications.templates.index', ['event' => $data['event']])
            ->with('status', 'Notification template created.');
    }

    public function edit(Request $request, NotificationTemplate $notificationTemplate): View
    {
        $this->authorizeOverride($request, $notificationTemplate);

        return view('settings.notifications.template-form', [
            'template' => $notificationTemplate,
            'events' => config('notifications.events', []),
            'channels' => config('notifications.channels', []),
        ]);
    }

    public function update(Request $request, NotificationTemplate $notificationTemplate): RedirectResponse
    {
        $this->authorizeOverride($request, $notificationTemplate);

        $data = $this->validated($request);

        $conflict = NotificationTemplate::query()
            ->where('institute_id', $notificationTemplate->institute_id)
            ->where('event', $data['event'])
            ->where('channel', $data['channel'])
            ->where('language', $data['language'])
            ->where('id', '!=', $notificationTemplate->id)
            ->exists();

        if ($conflict) {
            return back()->withErrors(['language' => 'Another override already uses this event, channel and language.']);
        }

        $notificationTemplate->update($data);

        return redirect()
            ->route('settings.notifications.templates.index', ['event' => $data['event']])
            ->with('status', 'Notification template updated.');
    }

    public function toggle(Request $request, NotificationTemplate $notificationTemplate): RedirectResponse
    {
        $this->authorizeOverride($request, $notificationTemplate);

        $notificationTemplate->update(['is_active' => ! $notificationTemplate->is_active]);

        return back()->with('status', 'Notification template status updated.');
    }

    public function destroy(Request $request, NotificationTemplate $notificationTemplate): RedirectResponse
    {
        $this->authorizeOverride($request, $notificationTemplate);

        $notificationTemplate->delete();

        return back()->with('status', 'Notification template deleted.');
    }

    /**
     * @return array{event: string, channel: string, language: string, name: string, subject: string, body: string, variables: array<int,string>|null, is_active: bool}
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'event' => ['required', 'string', Rule::in(array_keys(config('notifications.events', [])))],
            'channel' => ['required', 'string', Rule::in(['in_app', 'email', 'sms'])],
            'language' => ['required', 'string', Rule::in(['en', 'bn'])],
            'name' => ['required', 'string', 'max:190'],
            'subject' => ['nullable', 'string', 'max:190'],
            'body' => ['required', 'string', 'max:5000'],
            'variables' => ['nullable', 'array'],
            'variables.*' => ['string', 'max:60'],
            'is_active' => ['nullable', 'boolean'],
        ]) + ['is_active' => $request->boolean('is_active')];
    }

    private function authorizeOverride(Request $request, NotificationTemplate $template): void
    {
        $instituteId = $this->resolveInstituteId($request->user());
        abort_unless($instituteId !== null, 403);
        abort_if($template->institute_id !== $instituteId, 403, 'Global templates are read-only.');
    }

    private function resolveInstituteId($user): ?int
    {
        if ($user instanceof InstituteUser) {
            return $user->institute_id;
        }

        if ($user instanceof User) {
            return Workspace::membership()?->institution_id;
        }

        return null;
    }
}
