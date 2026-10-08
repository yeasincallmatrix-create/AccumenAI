@extends('layouts.standalone')

@php
    $backUrl = $backUrl ?? route('medical.appointments.index');
    $pageTitle = $pageTitle ?? 'OPD Queue Display';
    $hideTopbar = true;
    $qdDate = $date ?? today()->format('Y-m-d');
    $qdRefresh = max(5, (int) ($refreshSeconds ?? config('medicine.queue_display.refresh_seconds', 30)));
    $qdRows = collect($queueData ?? [])->values();
    // One doctor on screen = the giant broadcast layout (Live Broadcast merged here).
    $qdTv = $qdRows->count() === 1;
    $qdIds = $qdRows->pluck('doctor_id')->all();
    $qdDataUrl = route('medical.queue.display.data', array_filter([
        'doctors' => implode(',', $qdIds),
        'date' => $qdDate,
    ]));
    $qdSelectorUrl = route('medical.queue.display.selector');
@endphp

@section('title', $pageTitle . ' - AccumenAI')
@section('page_title', $pageTitle)

@push('styles')
<style>
.qd-topbar, .standalone-page > .standalone-container > .alert { display: none !important; }
.standalone-page { background: #0b1220; min-height: 100vh; padding: 0; }
.standalone-container { max-width: 100% !important; padding: 0 !important; }
.qd-wrap { min-height: 100vh; display: flex; flex-direction: column; color: #e8eefc; background: radial-gradient(1200px 600px at 20% -10%, #16325c 0%, transparent 60%), #0b1220; }

.qd-top { display: flex; align-items: center; justify-content: space-between; gap: 24px; padding: 22px 34px; border-bottom: 1px solid rgba(255,255,255,.08); }
.qd-inst { font-size: clamp(20px, 2.2vw, 34px); font-weight: 800; letter-spacing: .3px; line-height: 1.1; }
.qd-sub { font-size: clamp(12px, 1.1vw, 17px); color: #9fb3d9; margin-top: 4px; }
.qd-clock { font-size: clamp(24px, 2.6vw, 44px); font-weight: 800; font-variant-numeric: tabular-nums; text-align: right; line-height: 1; }
.qd-actions { display: flex; gap: 10px; margin-top: 8px; justify-content: flex-end; }
.qd-btn { border: 1px solid rgba(255,255,255,.22); background: rgba(255,255,255,.06); color: #dbe6ff; border-radius: 999px; padding: 6px 16px; font-size: 13px; text-decoration: none; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; }
.qd-btn:hover { background: rgba(255,255,255,.14); color: #fff; }

.qd-grid { flex: 1; display: grid; gap: 26px; padding: 30px 34px; grid-template-columns: repeat(var(--qd-cols, 1), minmax(0, 1fr)); align-content: start; }
@media (max-width: 1100px) { .qd-grid { grid-template-columns: minmax(0, 1fr); } }

.qd-card { background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.10); border-radius: 20px; overflow: hidden; display: flex; flex-direction: column; min-height: 460px; }
.qd-card-head { background: linear-gradient(90deg, #1d4ed8, #2563eb); padding: 16px 24px; }
.qd-doc { font-size: clamp(20px, 2vw, 30px); font-weight: 800; line-height: 1.15; }
.qd-dept { font-size: clamp(12px, 1.1vw, 16px); opacity: .92; margin-top: 3px; }

.qd-now { padding: 24px; text-align: center; background: rgba(0,0,0,.28); border-bottom: 1px dashed rgba(255,255,255,.14); }
.qd-now-label { font-size: clamp(12px, 1vw, 15px); letter-spacing: .28em; text-transform: uppercase; color: #7dd3fc; font-weight: 700; }
.qd-now-serial { font-size: clamp(56px, 8vw, 132px); font-weight: 900; line-height: 1; margin: 8px 0 2px; color: #fde047; font-variant-numeric: tabular-nums; text-shadow: 0 6px 26px rgba(253,224,71,.35); }
.qd-now-name { font-size: clamp(22px, 2.4vw, 40px); font-weight: 800; }
.qd-now-time { font-size: clamp(14px, 1.3vw, 20px); color: #9fb3d9; margin-top: 6px; }
.qd-now-empty { font-size: clamp(24px, 3vw, 48px); font-weight: 700; color: #94a3b8; padding: 26px 0; }

.qd-next { padding: 18px 24px; flex: 1; }
.qd-next-head { display: flex; align-items: center; justify-content: space-between; font-size: clamp(13px, 1.2vw, 18px); text-transform: uppercase; letter-spacing: .18em; color: #9fb3d9; font-weight: 700; margin-bottom: 12px; }
.qd-badge { background: #2563eb; color: #fff; border-radius: 999px; padding: 3px 14px; font-size: 15px; letter-spacing: 0; }
.qd-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 10px; }
.qd-row { display: flex; align-items: center; gap: 16px; background: rgba(255,255,255,.05); border-radius: 12px; padding: 12px 16px; }
.qd-serial { font-size: clamp(24px, 2.2vw, 36px); font-weight: 900; min-width: 74px; text-align: center; color: #fde047; font-variant-numeric: tabular-nums; }
.qd-name { font-size: clamp(18px, 1.8vw, 28px); font-weight: 700; flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.qd-time { font-size: clamp(14px, 1.3vw, 20px); color: #9fb3d9; font-variant-numeric: tabular-nums; }
.qd-none { color: #7f8ea8; font-size: clamp(15px, 1.4vw, 20px); padding: 14px 2px; }

/* One doctor = giant broadcast layout (Live Broadcast). Declared after the
   base card rules so the plain two-up cards stay the default. */
.qd-grid--tv { gap: 0; padding: 0; align-content: stretch; }
.qd-card--tv { display: grid; grid-template-columns: minmax(0, 1.7fr) minmax(0, 1fr); grid-template-rows: auto minmax(0, 1fr) auto; background: transparent; border: none; border-radius: 0; min-height: 0; }
.qd-card--tv .qd-card-head { grid-column: 1 / -1; padding: 24px 44px; }
.qd-card--tv .qd-doc { font-size: clamp(34px, 4vw, 72px); font-weight: 900; }
.qd-card--tv .qd-dept { font-size: clamp(16px, 1.7vw, 28px); margin-top: 6px; }
.qd-card--tv .qd-now { grid-column: 1; grid-row: 2; display: flex; flex-direction: column; justify-content: center; padding: 34px 44px; border-bottom: none; border-radius: 20px; }
.qd-card--tv .qd-now-serial { font-size: clamp(96px, 14vw, 260px); }
.qd-card--tv .qd-now-name { font-size: clamp(34px, 4vw, 76px); }
.qd-card--tv .qd-now-time { font-size: clamp(18px, 1.8vw, 30px); }
.qd-card--tv .qd-now-empty { font-size: clamp(30px, 4vw, 64px); padding: 40px 0; }
.qd-card--tv .qd-next { grid-column: 2; grid-row: 2; background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.10); border-radius: 20px; overflow-y: auto; }
.qd-card--tv .qd-next-head { font-size: clamp(15px, 1.5vw, 24px); }
.qd-card--tv .qd-row { padding: 16px 20px; }
.qd-card--tv .qd-serial { font-size: clamp(30px, 3vw, 54px); min-width: 96px; }
.qd-card--tv .qd-name { font-size: clamp(22px, 2.4vw, 44px); }
.qd-card--tv .qd-time { font-size: clamp(16px, 1.6vw, 26px); }
.qd-card--tv .qd-none { font-size: clamp(17px, 1.7vw, 26px); }
.qd-card--tv .qd-foot { grid-column: 1 / -1; grid-row: 3; font-size: clamp(15px, 1.4vw, 22px); padding: 16px 44px 26px; }
.qd-card--tv .qd-stats b { font-size: clamp(18px, 1.8vw, 28px); }
@media (max-width: 1100px) { .qd-card--tv { grid-template-columns: minmax(0, 1fr); } .qd-card--tv .qd-now, .qd-card--tv .qd-next, .qd-card--tv .qd-foot { grid-column: 1; grid-row: auto; } }

.qd-foot { display: flex; align-items: center; justify-content: space-between; gap: 18px; flex-wrap: wrap; padding: 14px 34px 22px; color: #8ba0c4; font-size: 13px; border-top: 1px solid rgba(255,255,255,.08); }
.qd-stats { display: flex; gap: 22px; flex-wrap: wrap; }
.qd-stats b { color: #e8eefc; font-size: 16px; }
.qd-live { display: inline-flex; align-items: center; gap: 8px; }
.qd-dot { width: 9px; height: 9px; border-radius: 50%; background: #22c55e; box-shadow: 0 0 0 0 rgba(34,197,94,.6); animation: qdPulse 1.8s infinite; }
.qd-dot.is-error { background: #ef4444; animation: none; }
@keyframes qdPulse { 70% { box-shadow: 0 0 0 12px rgba(34,197,94,0); } 100% { box-shadow: 0 0 0 0 rgba(34,197,94,0); } }
.qd-flash { animation: qdFlash .9s ease-out; }
@keyframes qdFlash { 0% { background: rgba(34,197,94,.35); } 100% { background: rgba(255,255,255,.05); } }
</style>
@endpush

@section('content')
<div class="qd-wrap" id="qdWrap">

    <div class="qd-top">
        <div>
            <div class="qd-inst">{{ optional($institute)->name ?? config('app.name') }}</div>
            <div class="qd-sub" id="qdDateLabel">{{ \Illuminate\Support\Carbon::parse($qdDate)->isoFormat('dddd, DD MMMM YYYY') }}</div>
        </div>
        <div>
            <div class="qd-clock" id="qdClock">--:--:--</div>
            <div class="qd-actions">
                <button class="qd-btn" type="button" id="qdFsBtn" title="Toggle fullscreen"><i class="bi bi-fullscreen"></i> Fullscreen</button>
                <a class="qd-btn" href="{{ $qdSelectorUrl }}"><i class="bi bi-people"></i> Change doctors</a>
                <a class="qd-btn" href="{{ $backUrl }}"><i class="bi bi-box-arrow-in-right"></i> Exit</a>
            </div>
        </div>
    </div>

    <div class="qd-grid{{ $qdTv ? ' qd-grid--tv' : '' }}" data-layout="{{ $qdTv ? 'tv' : 'cards' }}" style="--qd-cols: {{ max(1, min(2, $qdRows->count())) }};">

        @forelse ($qdRows as $row)
            @php $now = $row['now_serving'] ?? null; @endphp
            <section class="qd-card{{ $qdTv ? ' qd-card--tv' : '' }}" data-doctor-id="{{ $row['doctor_id'] }}">
                <div class="qd-card-head">
                    <div class="qd-doc" data-field="doctor_name">{{ $row['doctor_name'] }}</div>
                    @if (! empty($row['department_name']))
                        <div class="qd-dept" data-field="department_name">{{ $row['department_name'] }}</div>
                    @endif
                </div>

                <div class="qd-now">
                    <div class="qd-now-label">Now Serving</div>
                    <div data-field="now">
                        @if ($now)
                            <div class="qd-now-serial">#{{ $now['serial_number'] ?? '—' }}</div>
                            <div class="qd-now-name">{{ $now['display_name'] ?? $now['patient_name'] ?? '' }}</div>
                            <div class="qd-now-time">{{ $now['time'] ?? '' }}</div>
                        @else
                            <div class="qd-now-empty">No patient in consultation</div>
                        @endif
                    </div>
                </div>

                <div class="qd-next">
                    <div class="qd-next-head">
                        <span>Up Next</span>
                        <span class="qd-badge" data-field="waiting_count">{{ $row['waiting_count'] }}</span>
                    </div>
                    <ul class="qd-list" data-field="up_next">
                        @forelse ($row['up_next'] as $next)
                            <li class="qd-row">
                                <span class="qd-serial">#{{ $next['serial_number'] ?? '—' }}</span>
                                <span class="qd-name">{{ $next['display_name'] ?? $next['patient_name'] ?? '' }}</span>
                                <span class="qd-time">{{ $next['time'] ?? '' }}</span>
                            </li>
                        @empty
                            <li class="qd-none">No one waiting</li>
                        @endforelse
                    </ul>
                </div>

                <div class="qd-foot">
                    <div class="qd-stats">
                        <span>Total today <b data-field="total_today">{{ $row['total_today'] }}</b></span>
                        <span>Waiting <b data-field="waiting">{{ $row['waiting_count'] }}</b></span>
                    </div>
                </div>
            </section>
        @empty
            <section class="qd-card">
                <div class="qd-card-head"><div class="qd-doc">No doctor selected</div></div>
                <div class="qd-now"><div class="qd-now-empty">Choose up to two doctors to start</div></div>
                <div class="qd-next">
                    <a class="qd-btn" href="{{ $qdSelectorUrl }}"><i class="bi bi-people"></i> Select doctors</a>
                </div>
            </section>
        @endforelse

    </div>

    <div class="qd-foot">
        <div class="qd-stats"><span>Date: <b id="qdFootDate">{{ $qdDate }}</b></span></div>
        <div class="qd-live">
            <span class="qd-dot" id="qdDot"></span>
            <span id="qdStatus">Live</span>
            <span>· Refreshing every {{ $qdRefresh }}s</span>
            <span>· Updated <span id="qdUpdated">just now</span></span>
        </div>
    </div>

</div>
@endsection

@section('scripts')
<script>
(function () {
    'use strict';

    var REFRESH_SECONDS = {{ $qdRefresh }};
    var DATA_URL = @json($qdDataUrl);

    var clock = document.getElementById('qdClock');
    function tickClock() {
        if (!clock) return;
        var d = new Date();
        var p = function (n) { return n < 10 ? '0' + n : '' + n; };
        clock.textContent = p(d.getHours()) + ':' + p(d.getMinutes()) + ':' + p(d.getSeconds());
    }
    tickClock();
    setInterval(tickClock, 1000);

    var fsBtn = document.getElementById('qdFsBtn');
    if (fsBtn) {
        fsBtn.addEventListener('click', function () {
            if (document.fullscreenElement) {
                document.exitFullscreen();
            } else if (document.documentElement.requestFullscreen) {
                document.documentElement.requestFullscreen();
            }
        });
    }

    var dot = document.getElementById('qdDot');
    var status = document.getElementById('qdStatus');
    var updated = document.getElementById('qdUpdated');

    function setStatus(ok, label) {
        if (dot) dot.classList.toggle('is-error', !ok);
        if (status) status.textContent = label;
    }

    function esc(value) {
        var div = document.createElement('div');
        div.textContent = value === null || value === undefined ? '' : String(value);
        return div.innerHTML;
    }

    function rowHtml(item) {
        var serial = item && item.serial_number !== null && item.serial_number !== undefined ? '#' + esc(item.serial_number) : '#—';
        var name = item ? (item.display_name || item.patient_name || '') : '';
        var time = item ? (item.time || '') : '';
        return '<li class="qd-row qd-flash">' +
            '<span class="qd-serial">' + serial + '</span>' +
            '<span class="qd-name">' + esc(name) + '</span>' +
            '<span class="qd-time">' + esc(time) + '</span>' +
            '</li>';
    }

    function nowHtml(now) {
        if (!now) return '<div class="qd-now-empty">No patient in consultation</div>';
        var serial = now.serial_number !== null && now.serial_number !== undefined ? '#' + esc(now.serial_number) : '#—';
        return '<div class="qd-now-serial">' + serial + '</div>' +
            '<div class="qd-now-name">' + esc(now.display_name || now.patient_name || '') + '</div>' +
            '<div class="qd-now-time">' + esc(now.time || '') + '</div>';
    }

    function setText(card, field, value) {
        var node = card.querySelector('[data-field="' + field + '"]');
        if (node && node.textContent !== String(value)) node.textContent = value;
    }

    function render(payload) {
        var doctors = payload && payload.doctors ? payload.doctors : {};
        Object.keys(doctors).forEach(function (doctorId) {
            var card = document.querySelector('[data-doctor-id="' + doctorId + '"]');
            if (!card) return;
            var row = doctors[doctorId];

            setText(card, 'doctor_name', row.doctor_name || '');
            setText(card, 'waiting_count', row.waiting_count);
            setText(card, 'total_today', row.total_today);
            setText(card, 'waiting', row.waiting_count);

            var dept = card.querySelector('[data-field="department_name"]');
            if (dept) dept.textContent = row.department_name || '';

            var nowNode = card.querySelector('[data-field="now"]');
            if (nowNode) nowNode.innerHTML = nowHtml(row.now_serving);

            var list = card.querySelector('[data-field="up_next"]');
            if (list) {
                var items = Array.isArray(row.up_next) ? row.up_next : [];
                list.innerHTML = items.length
                    ? items.map(rowHtml).join('')
                    : '<li class="qd-none">No one waiting</li>';
            }
        });

        if (updated) {
            updated.textContent = payload && payload.generated_at
                ? String(payload.generated_at)
                : new Date().toTimeString().slice(0, 8);
        }
    }

    function refresh() {
        fetch(DATA_URL, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (res) {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            })
            .then(function (payload) { render(payload); setStatus(true, 'Live'); })
            .catch(function () { setStatus(false, 'Reconnecting…'); });
    }

    if (REFRESH_SECONDS > 0) {
        setInterval(refresh, REFRESH_SECONDS * 1000);
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) refresh();
        });
    }
})();
</script>
@endsection
