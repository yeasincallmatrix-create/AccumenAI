<?php

namespace App\Http\Controllers\Dealership\Api;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Models\Dealership\PushNotification;
use App\Models\Dealership\SalesForce;
use App\Services\Dealership\Api\PushDispatchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PushNotificationController extends Controller
{
    use ResolvesInstitute;

    public function __construct(
        private readonly PushDispatchService $dispatch,
    ) {}

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        $rows = PushNotification::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('sr'), fn ($q) => $q->where('recipient_sales_force_id', $request->input('sr')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->input('from')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('dealership.api.push.index', [
            'institute' => $institute,
            'rows' => $rows,
            'filters' => $request->only(['status', 'sr', 'from']),
            'statuses' => ['queued', 'sent', 'failed', 'cancelled'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requireInstitute($request);

        $data = $request->validate([
            'sales_force_id' => ['required', 'integer', 'min:1', 'exists:dealership_sales_force,id'],
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string'],
            'scheduled_at' => ['nullable', 'date'],
        ]);

        $sr = SalesForce::withoutGlobalScope('institute')->findOrFail($data['sales_force_id']);

        $this->dispatch->queue(
            $sr,
            $data['title'],
            $data['body'],
            [],
            isset($data['scheduled_at']) ? \Carbon\Carbon::parse($data['scheduled_at']) : null
        );

        return redirect()->route('dealership.push.index')->with('success', 'Notification queued.');
    }

    public function cancel(Request $request, PushNotification $notification): RedirectResponse
    {
        $this->requireInstitute($request);

        if (! $notification->isQueued()) {
            abort(422, 'Only queued notifications can be cancelled.');
        }

        $notification->update(['status' => 'cancelled']);

        return back()->with('success', 'Notification cancelled.');
    }
}
