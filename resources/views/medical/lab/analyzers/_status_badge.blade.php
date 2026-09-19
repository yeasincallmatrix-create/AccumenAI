@props(['status'])
<span class="badge bg-{{ match($status) {
    'active' => 'success',
    'inactive' => 'secondary',
    'maintenance' => 'warning',
    'error' => 'danger',
    default => 'secondary',
} }}">{{ ucfirst($status) }}</span>
