<div class="d-flex gap-3">
    <div class="flex-shrink-0 text-center" style="width: 48px;">
        <span class="badge rounded-circle bg-{{ $event->severityColor() }} p-2" title="{{ $event->eventTypeLabel() }}">
            <i class="bi {{ $event->icon() }}"></i>
        </span>
        <div class="vr h-100 mx-auto" style="width: 2px; background: #dee2e6;"></div>
    </div>
    <div class="card shadow-sm flex-grow-1 mb-3">
        <div class="card-body py-2">
            <div class="d-flex justify-content-between align-items-start gap-2">
                <div>
                    <span class="badge bg-light text-dark border">{{ $event->eventTypeLabel() }}</span>
                    @if($event->severity && $event->severity !== 'info')
                        <span class="badge bg-{{ $event->severityColor() }}">{{ ucfirst($event->severity) }}</span>
                    @endif
                    <h6 class="mb-0 mt-1">{{ $event->title }}</h6>
                    @if($event->description)
                        <p class="mb-1 small text-muted">{{ \Illuminate\Support\Str::limit($event->description, 300) }}</p>
                    @endif
                    <small class="text-muted">
                        {{ $event->event_at->format('d M Y H:i') }}
                        @if($event->doctor) | Dr. {{ $event->doctor->name }} @endif
                        @if($event->location) | {{ $event->location }} @endif
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>
