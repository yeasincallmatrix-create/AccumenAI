@extends('layouts.standalone')

@php
    $backUrl = $backUrl ?? route('medical.appointments.index');
    $hideTopbar = true;
    $qdMax = max(1, (int) ($maxDoctors ?? 2));
    $qdSelected = array_values(array_map('intval', $selectedIds ?? []));
@endphp

@section('title', 'Choose Doctors - OPD Queue Display')
@section('page_title', 'OPD Queue Display')

@push('styles')
<style>
.qd-sel-shell { max-width: 1100px; }
.qd-sel-bar { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
.qd-count { font-weight: 700; color: var(--bs-body-color); }
.qd-doctor-grid { display: grid; gap: 16px; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); }
.qd-doctor-card { position: relative; border: 2px solid var(--bs-border-color); border-radius: 16px; padding: 18px; background: var(--bs-body-bg); cursor: pointer; transition: border-color .15s ease, box-shadow .15s ease; }
.qd-doctor-card:hover { border-color: var(--bs-primary); }
.qd-doctor-card.is-selected { border-color: var(--bs-primary); box-shadow: 0 0 0 3px color-mix(in srgb, var(--bs-primary) 25%, transparent); }
.qd-doctor-card.is-disabled { opacity: .45; }
.qd-doctor-card input { position: absolute; opacity: 0; pointer-events: none; }
.qd-doc-name { font-size: 1.05rem; font-weight: 700; }
.qd-doc-meta { font-size: .85rem; color: var(--bs-secondary-color); margin-top: 4px; }
.qd-doc-check { position: absolute; top: 14px; right: 14px; width: 26px; height: 26px; border-radius: 50%; border: 2px solid var(--bs-border-color); display: flex; align-items: center; justify-content: center; font-size: .9rem; color: transparent; }
.qd-doctor-card.is-selected .qd-doc-check { background: var(--bs-primary); border-color: var(--bs-primary); color: #fff; }
.qd-empty { color: var(--bs-secondary-color); padding: 30px 0; }
.qd-hint { font-size: .85rem; color: var(--bs-secondary-color); }
</style>
@endpush

@section('content')
<div class="qd-sel-shell">

    @if (session('status'))
        <div class="alert alert-info" data-auto-dismiss>
            <i class="bi bi-info-circle-fill me-1"></i>{{ session('status') }}
        </div>
    @endif

    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-4">
        <div>
            <h4 class="mb-1">Choose up to {{ $qdMax }} doctor{{ $qdMax > 1 ? 's' : '' }}</h4>
            <p class="mb-0 qd-hint">The queue display opens fullscreen and refreshes automatically.</p>
        </div>
        <div class="qd-count"><span id="qdSelCount">{{ count($qdSelected) }}</span> / {{ $qdMax }} selected</div>
    </div>

    <form method="GET" action="{{ route('medical.queue.display.selector') }}" class="qd-sel-bar mb-4">
        <input type="search" name="search" value="{{ $search }}" class="form-control" style="max-width:320px" placeholder="Search doctor name..." aria-label="Search doctor">
        @if ($qdSelected)
            <input type="hidden" name="doctors[]" value="{{ implode(',', $qdSelected) }}">
        @endif
        <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i> Search</button>
        @if ($search)
            <a class="btn btn-link" href="{{ route('medical.queue.display.selector') }}">Clear</a>
        @endif
    </form>

    @if ($doctors->isEmpty())
        <div class="qd-empty">No doctors match your search.</div>
    @else
        <form method="GET" action="{{ route('medical.queue.display') }}" id="qdOpenForm">
            <input type="hidden" name="date" value="{{ $date }}">
            <div class="qd-doctor-grid" data-max="{{ $qdMax }}">
                @foreach ($doctors as $doctor)
                    @php $isOn = in_array((int) $doctor->id, $qdSelected, true); @endphp
                    <label class="qd-doctor-card {{ $isOn ? 'is-selected' : '' }}"
                           data-doctor-id="{{ $doctor->id }}">
                        <input type="checkbox" name="doctors[]" value="{{ $doctor->id }}" @checked($isOn)>
                        <span class="qd-doc-check"><i class="bi bi-check-lg"></i></span>
                        <div class="qd-doc-name">{{ $doctor->name }}</div>
                        <div class="qd-doc-meta">Doctor #{{ $doctor->id }}</div>
                    </label>
                @endforeach
            </div>

            <div class="d-flex align-items-center gap-3 flex-wrap mt-4">
                <button class="btn btn-primary btn-lg" type="submit" id="qdOpenBtn" @disabled(count($qdSelected) === 0)>
                    <i class="bi bi-tv me-1"></i> Open queue display
                </button>
                <a class="btn btn-outline-secondary" href="{{ $backUrl }}">Back</a>
                <span class="qd-hint">Select {{ $qdMax === 1 ? 'a doctor' : 'up to '.$qdMax.' doctors' }} to continue.</span>
            </div>
        </form>
    @endif

</div>
@endsection

@section('scripts')
<script>
(function () {
    'use strict';
    var grid = document.querySelector('.qd-doctor-grid');
    if (!grid) return;

    var max = parseInt(grid.getAttribute('data-max'), 10) || 2;
    var countEl = document.getElementById('qdSelCount');
    var openBtn = document.getElementById('qdOpenBtn');
    var cards = Array.prototype.slice.call(grid.querySelectorAll('.qd-doctor-card'));

    function boxes() {
        return cards.map(function (card) { return card.querySelector('input[type="checkbox"]'); });
    }

    function sync() {
        var checked = boxes().filter(function (box) { return box.checked; });
        if (checked.length > max) {
            var extra = checked[checked.length - 1];
            extra.checked = false;
            checked = boxes().filter(function (box) { return box.checked; });
        }

        cards.forEach(function (card) {
            var box = card.querySelector('input[type="checkbox"]');
            card.classList.toggle('is-selected', box.checked);
            card.classList.toggle('is-disabled', !box.checked && checked.length >= max);
        });

        if (countEl) countEl.textContent = String(checked.length);
        if (openBtn) openBtn.disabled = checked.length === 0;
    }

    cards.forEach(function (card) {
        card.addEventListener('change', sync);
        card.addEventListener('click', function (event) {
            var box = card.querySelector('input[type="checkbox"]');
            if (box.checked && event.target !== box && checkedCount() >= max) {
                event.preventDefault();
            }
        });
    });

    function checkedCount() {
        return boxes().filter(function (box) { return box.checked; }).length;
    }

    sync();
})();
</script>
@endsection
