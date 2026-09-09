@extends('layouts.admin')

@section('title', 'Geography Duplicates — AccumenAI')

@section('content')
<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">Geography Duplicates</h4>
        <p class="page-header-desc">Same name + parent, or same code with different names. Same names under different parents (e.g. Kaliganj in four districts) are legitimate and never listed.</p>
    </div>
    <div class="page-header-actions d-flex gap-2">
        <a class="btn btn-outline-secondary" href="{{ route('admin.geo.imports') }}">
            <i class="bi bi-upload"></i> Imports
        </a>
        <a class="btn btn-outline-secondary" href="{{ route('admin.geo.index') }}">
            <i class="bi bi-arrow-left"></i> Locations
        </a>
    </div>
</div>

<div class="admin-card" style="max-width:760px;">
    <form method="GET" action="{{ route('admin.geo.duplicates') }}" class="row g-3 align-items-end">
        <div class="col-md-8">
            <label class="form-label" for="country_id">Country</label>
            <select id="country_id" name="country_id" class="form-select" onchange="this.form.submit()">
                @foreach ($countries as $c)
                    <option value="{{ $c->id }}" {{ ($country?->id) == $c->id ? 'selected' : '' }}>{{ $c->name }} ({{ $c->iso2 }})</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <button class="btn btn-outline-primary w-100" type="submit"><i class="bi bi-arrow-repeat"></i> Re-scan</button>
        </div>
    </form>
</div>

@if ($country && $scan)
<div class="admin-card mt-4">
    <div class="table-toolbar">
        <div class="toolbar-info">
            <i class="bi bi-copy"></i> {{ $country->name }} — {{ $scan['name_group_count'] }} name group(s), {{ $scan['code_group_count'] }} code group(s)
        </div>
    </div>
    <div id="dupMsg" class="mt-2 d-none"></div>

    @if ($scan['name_group_count'] === 0 && $scan['code_group_count'] === 0)
        <div class="alert alert-success mt-3 mb-0"><i class="bi bi-check-circle"></i> No duplicates found. The tree is clean.</div>
    @endif

    @foreach (['name_groups' => 'Same name + parent', 'code_groups' => 'Same code, different names'] as $key => $title)
        @foreach ($scan[$key] as $gi => $group)
            <div class="card mt-3" data-group="{{ $key }}-{{ $gi }}">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>{{ $title }}</strong>
                    <button type="button" class="btn btn-sm btn-warning merge-btn">
                        <i class="bi bi-union"></i> Merge selected into survivor
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th></th>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Code</th>
                                    <th>Parent</th>
                                    <th>Children</th>
                                    <th>Refs</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($group['members'] as $m)
                                <tr>
                                    <td>
                                        <input type="radio" class="form-check-input merge-pick" name="keep-{{ $key }}-{{ $gi }}"
                                               value="{{ $m['id'] }}" {{ $loop->first ? 'checked' : '' }} title="Survivor">
                                    </td>
                                    <td class="text-muted">{{ $m['id'] }}</td>
                                    <td class="fw-semibold">{{ $m['name'] }}</td>
                                    <td><code>{{ $m['code'] ?? '—' }}</code></td>
                                    <td>{{ $m['parent'] ?? '—' }}</td>
                                    <td>{{ $m['children'] }}</td>
                                    <td>{{ $m['refs'] }}</td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-outline-danger del-btn" data-id="{{ $m['id'] }}" title="Delete (only when no children and no references)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endforeach
    @endforeach
</div>
@endif
@endsection

@section('scripts')
<script>
(function () {
    var tokenEl = document.querySelector('meta[name="csrf-token"]');
    var token = tokenEl ? tokenEl.getAttribute('content') : '';
    var countryId = '{{ $country?->id }}';
    var msgBox = document.getElementById('dupMsg');

    function setMsg(kind, text) {
        if (!msgBox) return;
        msgBox.className = 'mt-2 alert alert-' + (kind === 'ok' ? 'success' : 'danger');
        msgBox.textContent = text;
    }

    document.querySelectorAll('.merge-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var card = btn.closest('.card');
            var checked = card.querySelector('.merge-pick:checked');
            var ids = Array.from(card.querySelectorAll('.merge-pick')).map(function (r) { return parseInt(r.value, 10); });
            if (ids.length < 2) return;
            var keepName = card.querySelector('.merge-pick:checked').closest('tr').querySelector('td:nth-child(3)').textContent.trim();
            if (!confirm('Merge ' + ids.length + ' rows into #' + checked.value + ' ("' + keepName + '")?\n\nChildren and address references move to the survivor; losers are deleted.')) return;
            btn.disabled = true;
            fetch('{{ route("admin.geo.duplicates.merge") }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({ country_id: parseInt(countryId, 10), ids: ids, keep_id: parseInt(checked.value, 10) })
            }).then(function (r) { return r.json(); }).then(function (d) {
                if (!d.success) throw new Error(d.message || 'Merge failed.');
                setMsg('ok', d.message);
                setTimeout(function () { location.reload(); }, 1200);
            }).catch(function (e) {
                btn.disabled = false;
                setMsg('err', e.message);
            });
        });
    });

    document.querySelectorAll('.del-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!confirm('Delete row #' + btn.dataset.id + '? Only allowed when nothing points at it.')) return;
            btn.disabled = true;
            fetch('{{ route("admin.geo.duplicates.destroy", ["unit" => "ID"]) }}'.replace('ID', encodeURIComponent(btn.dataset.id)) + '?country_id=' + encodeURIComponent(countryId), {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({ country_id: parseInt(countryId, 10) })
            }).then(function (r) { return r.json(); }).then(function (d) {
                if (!d.success) throw new Error(d.message || 'Delete failed.');
                setMsg('ok', d.message);
                setTimeout(function () { location.reload(); }, 1200);
            }).catch(function (e) {
                btn.disabled = false;
                setMsg('err', e.message);
            });
        });
    });
})();
</script>
@endsection
