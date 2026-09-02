@php $steps = ['Account','Verify Email','Organization','Address','Setup']; @endphp
<div class="d-flex justify-content-center align-items-center gap-1 mb-3 flex-wrap">
    @foreach($steps as $i => $label)
        @php $n = $i+1; $active = ($step ?? 1) == $n; $done = ($step ?? 1) > $n; @endphp
        <div class="d-flex align-items-center gap-1">
            <span class="badge rounded-pill {{ $active ? 'text-bg-primary' : ($done ? 'text-bg-success' : 'text-bg-secondary') }}">{{ $n }}</span>
            <span class="small {{ $active ? 'fw-bold text-primary' : ($done ? 'text-success' : 'text-muted') }}">{{ $label }}</span>
        </div>
        @if(!$loop->last)
            <span class="text-muted mx-1">→</span>
        @endif
    @endforeach
</div>
