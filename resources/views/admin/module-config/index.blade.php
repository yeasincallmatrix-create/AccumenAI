@extends('layouts.admin')

@section('title', 'Universal Module Configuration — AccumenAI')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item active">Universal Module Configuration</li>
    </ol>
</nav>

<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">Universal Module Configuration</h4>
        <p class="page-header-desc">
            One matrix for every Industry × Sub-Industry. Each module is parked in exactly one bucket:
            <span class="badge bg-danger-subtle text-danger">mandatory</span>
            <span class="badge bg-success-subtle text-success">default</span>
            <span class="badge bg-secondary">optional</span>
            <span class="badge bg-dark">hidden</span>
        </p>
    </div>
    <div class="page-header-actions">
        @if ($subcategory)
            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#copyModal">
                <i class="bi bi-clipboard-check"></i> Copy From…
            </button>
        @endif
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if ($errors->any())
    <div class="alert alert-danger py-2">
        @foreach ($errors->all() as $error)
            <div class="small">{{ $error }}</div>
        @endforeach
    </div>
@endif

<div class="admin-card mb-4">
    <div class="table-toolbar">
        <div class="toolbar-info">
            <i class="bi bi-diagram-3"></i> Scope
            <span class="text-muted ms-2">Pick an industry, then a sub-industry, to load its matrix.</span>
        </div>
    </div>
    <div class="p-3">
        <form method="GET" action="{{ route('admin.module-config.index') }}" class="d-flex align-items-end gap-3 flex-wrap">
            <div>
                <label class="form-label small text-muted mb-1" for="industrySelect">Industry</label>
                <select name="industry" id="industrySelect" class="form-select form-select-sm" style="min-width:200px" onchange="this.form.submit()">
                    @foreach ($industries as $key)
                        <option value="{{ $key }}" {{ $key === $industry ? 'selected' : '' }}>
                            {{ config('industry-modules.' . $key . '.name', $key) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label small text-muted mb-1" for="subIndustrySelect">Sub-Industry</label>
                <select name="subcategory" id="subIndustrySelect" class="form-select form-select-sm" style="min-width:230px" onchange="this.form.submit()">
                    <option value="">— select a sub-industry —</option>
                    @foreach ($subcategories as $row)
                        <option value="{{ $row->subcategory_key }}" {{ $subcategory && $subcategory->subcategory_key === $row->subcategory_key ? 'selected' : '' }}>
                            {{ $row->name }} ({{ $row->subcategory_key }})
                        </option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-repeat"></i> Load</button>
        </form>
    </div>
</div>

@if ($subcategory)
    <form method="POST" action="{{ route('admin.module-config.update') }}">
        @csrf
        @method('PUT')
        <input type="hidden" name="industry" value="{{ $industry }}">
        <input type="hidden" name="subcategory" value="{{ $subcategory->subcategory_key }}">

        <div class="admin-card mb-3">
            <div class="table-toolbar">
                <div class="toolbar-info">
                    <i class="bi bi-collection-fill"></i> {{ $subcategory->name }}
                    <code class="ms-1">{{ $industry }} / {{ $subcategory->subcategory_key }}</code>
                    <span class="text-muted ms-2">Layer 3 source for tenants assigned to this sub-industry.</span>
                </div>
                <div class="toolbar-actions">
                    <span class="badge bg-light text-dark border">saved keys: {{ count($matrix) }}</span>
                </div>
            </div>
        </div>

        @foreach ($groups as $groupKey => $group)
            @include('admin.module-config._group', ['groupKey' => $groupKey, 'group' => $group])
        @endforeach

        <div class="admin-card p-3 d-flex align-items-center justify-content-between gap-3">
            <div class="text-muted small">
                <i class="bi bi-info-circle text-primary"></i>
                Saving is idempotent — re-submitting updates categories in place and flushes the module cache for every
                matching tenant.
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check2-circle"></i> Save Configuration
            </button>
        </div>
    </form>
@else
    <div class="admin-card p-4 text-center text-muted">
        <i class="bi bi-diagram-3 fs-3"></i>
        <p class="mb-0 mt-2">Select a sub-industry above to load its module matrix.</p>
    </div>
@endif

@if ($subcategory)
    <div class="modal fade" id="copyModal" tabindex="-1" aria-labelledby="copyModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form class="modal-content" method="POST" action="{{ route('admin.module-config.copy') }}">
                @csrf
                <input type="hidden" name="target_industry" value="{{ $industry }}">
                <input type="hidden" name="target_subcategory" value="{{ $subcategory->subcategory_key }}">

                <div class="modal-header">
                    <h5 class="modal-title" id="copyModalLabel">Copy Module Configuration</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">
                        Overwrites <strong>{{ $subcategory->name }}</strong>
                        (<code>{{ $industry }} / {{ $subcategory->subcategory_key }}</code>) with the configuration
                        of the sub-industry chosen below.
                    </p>

                    <div class="mb-3">
                        <label class="form-label" for="copySourceIndustry">From — Industry</label>
                        <select name="source_industry" id="copySourceIndustry" class="form-select" required>
                            @foreach ($industries as $key)
                                <option value="{{ $key }}" {{ $key === $industry ? 'selected' : '' }}>
                                    {{ config('industry-modules.' . $key . '.name', $key) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-2">
                        <label class="form-label" for="copySourceSub">From — Sub-Industry</label>
                        <select name="source_subcategory" id="copySourceSub" class="form-select" required></select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-clipboard-check"></i> Copy Configuration
                    </button>
                </div>
            </form>
        </div>
    </div>
@endif
@endsection

@section('scripts')
<script>
(function () {
    document.querySelectorAll('[data-module-toggle]').forEach(function (link) {
        link.addEventListener('click', function (event) {
            event.preventDefault();

            var key = link.getAttribute('data-module-toggle');
            var collapse = !link.classList.contains('is-collapsed');

            document.querySelectorAll('[data-child-of="' + key + '"]').forEach(function (row) {
                row.classList.toggle('d-none', collapse);
            });

            link.classList.toggle('is-collapsed', collapse);
            link.setAttribute('aria-expanded', String(!collapse));

            var caret = link.querySelector('[data-caret]');
            if (caret) {
                caret.classList.toggle('bi-caret-down-fill', !collapse);
                caret.classList.toggle('bi-caret-right-fill', collapse);
            }
        });
    });
})();
</script>
<script>
(function () {
    const SUB_MAP = @json($subMap);
    const sourceIndustry = document.getElementById('copySourceIndustry');
    const sourceSub = document.getElementById('copySourceSub');

    if (!sourceIndustry || !sourceSub) {
        return;
    }

    function fillSubs(industry, selected) {
        const list = SUB_MAP[industry] || [];
        sourceSub.innerHTML = '';

        if (!list.length) {
            sourceSub.innerHTML = '<option value="">No sub-industries registered</option>';
            return;
        }

        list.forEach(function (item) {
            const option = document.createElement('option');
            option.value = item.key;
            option.textContent = item.name;
            option.selected = item.key === selected;
            sourceSub.appendChild(option);
        });
    }

    sourceIndustry.addEventListener('change', function () {
        fillSubs(this.value, '');
    });

    fillSubs(sourceIndustry.value, @json($subcategory?->subcategory_key));
})();
</script>
<script>
(function () {
    // ─────────────────────────────────────────────────────────────
    // Parent → children radio cascade
    //   Rule 1: parent change cascades its value to every child
    //   Rule 2: a child may still be set individually afterwards
    //   Rule 3: changing the parent wipes those child overrides
    // ─────────────────────────────────────────────────────────────
    function cascadeToast(message, type) {
        var toast = document.createElement('div');
        toast.className = 'position-fixed bottom-0 end-0 m-3 alert alert-' + (type || 'info') + ' shadow';
        toast.setAttribute('role', 'status');
        toast.style.zIndex = '9999';
        toast.style.maxWidth = '400px';
        toast.textContent = message;
        document.body.appendChild(toast);

        window.setTimeout(function () {
            toast.style.transition = 'opacity .3s';
            toast.style.opacity = '0';
            window.setTimeout(function () { toast.remove(); }, 300);
        }, 2500);
    }

    function childRow(radio) {
        return radio.closest('tr');
    }

    function setOverrideMarker(row, isOverride) {
        if (!row) {
            return;
        }

        row.classList.toggle('child-override', isOverride);

        var label = row.querySelector('.child-override-label');
        if (isOverride && !label) {
            label = document.createElement('span');
            label.className = 'small text-primary child-override-label';
            label.textContent = 'override';
            var code = row.querySelector('td:first-child code');
            if (code && code.parentNode) {
                code.parentNode.appendChild(label);
            } else {
                row.querySelector('td:first-child div')?.appendChild(label);
            }
        } else if (!isOverride && label) {
            label.remove();
        }
    }

    function parentValueOf(parentKey) {
        var checked = document.querySelector('.parent-radio[data-parent-key="' + parentKey + '"]:checked');
        return checked ? checked.value : null;
    }

    function cascadeToChildren(parentKey, newCategory) {
        var children = document.querySelectorAll('.child-radio[data-parent-key="' + parentKey + '"]');
        var childKeys = {};

        children.forEach(function (childRadio) {
            childKeys[childRadio.dataset.childKey] = true;

            if (childRadio.value === newCategory) {
                childRadio.checked = true;
            }
            // Rule 3 — every child override is reset by a parent change.
            setOverrideMarker(childRow(childRadio), false);
        });

        return Object.keys(childKeys).length;
    }

    document.querySelectorAll('.parent-radio').forEach(function (radio) {
        radio.addEventListener('change', function () {
            var parentKey = this.dataset.parentKey;
            var newCategory = this.value;
            var count = cascadeToChildren(parentKey, newCategory);

            cascadeToast('Parent changed to "' + newCategory + '" — ' + count + ' children updated', 'info');
        });
    });

    document.querySelectorAll('.child-radio').forEach(function (radio) {
        radio.addEventListener('change', function () {
            var parentKey = this.dataset.parentKey;
            var parentValue = parentValueOf(parentKey);
            var row = childRow(this);

            if (parentValue && this.value !== parentValue) {
                setOverrideMarker(row, true);
                cascadeToast('Child overridden (parent: ' + parentValue + ', child: ' + this.value + ')', 'warning');
            } else {
                setOverrideMarker(row, false);
            }
        });
    });

    // Re-sync override markers against the rendered state (saved matrix).
    document.querySelectorAll('.child-radio:checked').forEach(function (radio) {
        var parentValue = parentValueOf(radio.dataset.parentKey);
        if (parentValue) {
            setOverrideMarker(childRow(radio), radio.value !== parentValue);
        }
    });
})();
</script>
<style>
/* Override indicator for a child whose category differs from its parent */
.child-row.child-override td:first-child::before {
    content: '\25CF  ';
    color: #0d6efd;
    font-weight: bold;
}
.child-row.child-override {
    background-color: #f0f7ff !important;
}
.child-override-label {
    text-transform: uppercase;
    letter-spacing: .04em;
    font-size: 10px;
}
html.monetix-dark .child-row.child-override {
    background-color: rgba(13, 110, 253, .12) !important;
}
</style>
@endsection
