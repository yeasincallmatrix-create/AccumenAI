@props(['uid', 'label' => 'UID'])
<div class="d-inline-flex align-items-center gap-1">
    @if(!empty($label) && $label !== 'UID')
        <span class="text-muted small me-1">{{ $label }}:</span>
    @endif
    <span class="badge bg-light text-dark font-monospace border">{{ $uid ?? '—' }}</span>
    @if(!empty($uid))
    <button type="button" class="btn btn-sm btn-outline-secondary p-0 px-1" onclick="copyToClipboard('{{ $uid }}', this)" title="Copy {{ $label }}" aria-label="Copy {{ $label }}">
        <i class="bi bi-clipboard"></i>
    </button>
    @endif
</div>
