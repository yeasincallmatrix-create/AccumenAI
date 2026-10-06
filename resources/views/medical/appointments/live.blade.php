@extends('layouts.standalone')

@php
    $backUrl = route('medical.appointments.index', ['q_doctor' => $doctor->id]);
    $docName = $doctor->full_name;
    $docDept = $doctor->department_name;
    $liveDataUrl = route('medical.appointments.live.data', $doctor);
    $liveQueue = collect($queue['queue'] ?? []);
    $liveTotal = (int) ($queue['total'] ?? 0);
    $liveNow = $liveQueue->firstWhere('status', 'in_progress');
    $liveNext = $liveQueue->where('status', '!=', 'in_progress')->values();
    $liveWaiting = $liveNext->count();
@endphp

@section('title', 'Live Broadcast - '.$docName)
@section('page_title', 'Live Broadcast')

@push('styles')
<style>
/* Fullscreen board: drop the standalone chrome, own the viewport. */
.standalone-page > .standalone-container > .alert { display: none !important; }
.standalone-page { background: #0b1220; min-height: 100vh; }
.standalone-container { max-width: 100% !important; padding: 0 !important; }
body > .topbar { display: none !important; }

.lb-wrap { min-height: 100vh; background: #0b1220; color: #e8eefc; }

.lb-head {
    position: sticky; top: 0; z-index: 5;
    display: flex; align-items: center; justify-content: space-between; gap: 20px;
    padding: 18px 34px; background: rgba(255,255,255,.04);
    border-bottom: 1px solid rgba(255,255,255,.10);
}
.lb-doc { font-size: clamp(20px, 2.2vw, 38px); font-weight: 800; line-height: 1.15; }
.lb-sub { font-size: clamp(12px, 1.1vw, 18px); color: #9fb3d9; margin-top: 3px; }
.lb-clock { font-size: clamp(26px, 3vw, 56px); font-weight: 800; font-variant-numeric: tabular-nums; line-height: 1; text-align: center; }
.lb-actions { display: flex; gap: 10px; justify-content: flex-end; flex-wrap: wrap; }
.lb-btn {
    border: 1px solid rgba(255,255,255,.24); background: rgba(255,255,255,.07); color: #dbe6ff;
    border-radius: 999px; padding: 9px 20px; font-size: 15px; cursor: pointer;
    display: inline-flex; align-items: center; gap: 8px; text-decoration: none;
}
.lb-btn:hover { background: rgba(255,255,255,.16); color: #fff; }

.lb-main { flex: 1; display: flex; flex-direction: column; gap: 24px; padding: 28px 34px; }

.lb-now {
    background: rgba(0,0,0,.30); border: 1px solid rgba(255,255,255,.10);
    border-radius: 22px; padding: 30px; text-align: center;
}
.lb-label { font-size: clamp(13px, 1.2vw, 20px); letter-spacing: .3em; text-transform: uppercase; color: #7dd3fc; font-weight: 700; }
.lb-serial { font-size: clamp(128px, 15vw, 260px); font-weight: 900; line-height: 1; margin: 10px 0 4px; color: #4ade80; font-variant-numeric: tabular-nums; text-shadow: 0 8px 34px rgba(74,222,128,.35); }
.lb-name { font-size: clamp(48px, 5vw, 104px); font-weight: 800; line-height: 1.1; word-break: break-word; }
.lb-time { font-size: clamp(20px, 1.8vw, 34px); color: #9fb3d9; margin-top: 8px; font-variant-numeric: tabular-nums; }
.lb-idle { font-size: clamp(40px, 4.5vw, 90px); font-weight: 700; color: #94a3b8; padding: 22px 0; }

.lb-panel { background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.10); border-radius: 22px; padding: 22px 26px; flex: 1; }
.lb-panel-head { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin-bottom: 14px; }
.lb-panel-title { font-size: clamp(16px, 1.5vw, 26px); text-transform: uppercase; letter-spacing: .2em; color: #9fb3d9; font-weight: 700; }
.lb-waiting { font-size: clamp(18px, 1.6vw, 30px); font-weight: 800; background: #2563eb; color: #fff; border-radius: 999px; padding: 5px 20px; }

.lb-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 12px; }
.lb-row { display: flex; align-items: center; gap: 20px; background: rgba(255,255,255,.06); border-radius: 14px; padding: 16px 20px; }
.lb-row-serial { font-size: clamp(30px, 3vw, 54px); font-weight: 900; min-width: 92px; text-align: center; color: #fde047; font-variant-numeric: tabular-nums; }
.lb-row-name { font-size: clamp(22px, 2.2vw, 42px); font-weight: 700; flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.lb-row-time { font-size: clamp(16px, 1.5vw, 28px); color: #9fb3d9; font-variant-numeric: tabular-nums; }
.lb-none { color: #7f8ea8; font-size: clamp(18px, 1.7vw, 30px); padding: 12px 4px; }

.lb-foot { display: flex; align-items: center; justify-content: space-between; gap: 18px; flex-wrap: wrap; padding: 6px 34px 22px; color: #8ba0c4; font-size: clamp(13px, 1.1vw, 17px); }
.lb-conn-ok { color: #4ade80; }
.lb-conn-bad { color: #f87171; font-weight: 700; }
@media (max-width: 800px) { .lb-head { flex-direction: column; align-items: flex-start; } .lb-clock { text-align: left; } }
</style>
@endpush

@section('content')
<div class="vh-100 d-flex flex-column bg-dark text-white lb-wrap"
     data-url="{{ $liveDataUrl }}"
     data-empty="{{ $liveTotal === 0 ? '1' : '0' }}">

    <header class="lb-head">
        <div>
            <div class="lb-doc">Dr. {{ $docName }} <span style="opacity:.6">—</span> {{ $docDept }}</div>
            <div class="lb-sub">{{ $instituteName }}</div>
        </div>
        <div class="lb-clock" id="lbClock">--:--:--</div>
        <div class="lb-actions">
            <button type="button" class="lb-btn" id="lbFullscreen" title="Toggle fullscreen">
                <i class="bi bi-fullscreen"></i> Fullscreen
            </button>
            <button type="button" class="lb-btn" id="lbExit" title="Close broadcast">
                <i class="bi bi-box-arrow-right"></i> Exit
            </button>
        </div>
    </header>

    <main class="lb-main">
        <section class="lb-now" aria-live="polite">
            <div class="lb-label">Now Serving</div>

            <div id="lbNowServing" @if($liveNow === null) style="display:none" @endif>
                <div class="lb-serial" id="lbSerial">{{ $liveNow['serial'] ?? '' }}</div>
                <div class="lb-name" id="lbPatient">{{ $liveNow['patient_name'] ?? '' }}</div>
                <div class="lb-time" id="lbTime">{{ $liveNow['estimated_time'] ?? '' }}</div>
            </div>

            <div class="lb-idle" id="lbIdle" @if($liveNow !== null) style="display:none" @endif>
                {{ $liveTotal === 0 ? 'No appointments today' : 'Waiting for next patient' }}
            </div>
        </section>

        <section class="lb-panel">
            <div class="lb-panel-head">
                <div class="lb-panel-title">Up Next</div>
                <div class="lb-waiting">WAITING: <span id="lbWaiting">{{ $liveWaiting }}</span></div>
            </div>
            <ul class="lb-list" id="lbUpNext">
                @forelse($liveNext as $row)
                    <li class="lb-row">
                        <span class="lb-row-serial">{{ $row['serial'] }}</span>
                        <span class="lb-row-name">{{ $row['patient_name'] }}</span>
                        <span class="lb-row-time">{{ $row['estimated_time'] }}</span>
                    </li>
                @empty
                    <li class="lb-none" id="lbNoNext">{{ $liveTotal === 0 ? 'No appointments today' : 'No one waiting' }}</li>
                @endforelse
            </ul>
        </section>
    </main>

    <footer class="lb-foot">
        <span id="lbConn" class="lb-conn-ok">Live</span>
        <span id="lbUpdated">Updated just now</span>
    </footer>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var wrap = document.querySelector('.lb-wrap');
    if (!wrap) return;

    var DATA_URL = wrap.dataset.url;
    var POLL_MS = 10000;
    var RETRY_MS = 5000;

    var elClock = document.getElementById('lbClock');
    var elNow = document.getElementById('lbNowServing');
    var elIdle = document.getElementById('lbIdle');
    var elSerial = document.getElementById('lbSerial');
    var elPatient = document.getElementById('lbPatient');
    var elTime = document.getElementById('lbTime');
    var elUpNext = document.getElementById('lbUpNext');
    var elWaiting = document.getElementById('lbWaiting');
    var elConn = document.getElementById('lbConn');
    var elUpdated = document.getElementById('lbUpdated');

    var lastOk = Date.now();
    var pollTimer = null;
    var tickTimer = null;

    function pad(n) { return n < 10 ? '0' + n : '' + n; }

    function tickClock() {
        var d = new Date();
        elClock.textContent = pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());

        var secs = Math.round((Date.now() - lastOk) / 1000);
        if (secs < 2) {
            elUpdated.textContent = 'Updated just now';
        } else if (secs < 60) {
            elUpdated.textContent = 'Updated ' + secs + 's ago';
        } else {
            elUpdated.textContent = 'Updated ' + Math.floor(secs / 60) + 'm ' + (secs % 60) + 's ago';
        }
    }

    function rowsOf(queue) {
        if (!queue) return [];
        if (Array.isArray(queue)) return queue;
        if (Array.isArray(queue.queue)) return queue.queue;
        return [];
    }

    function esc(value) {
        var d = document.createElement('div');
        d.textContent = value == null ? '' : String(value);
        return d.innerHTML;
    }

    function render(payload) {
        var q = payload.queue || {};
        var rows = rowsOf(q);
        var now = null;
        var next = [];
        for (var i = 0; i < rows.length; i++) {
            var row = rows[i];
            if (row.status === 'in_progress' && !now) {
                now = row;
            } else if (row.status !== 'in_progress') {
                next.push(row);
            }
        }

        if (rows.length === 0) {
            elNow.style.display = 'none';
            elIdle.style.display = '';
            elIdle.textContent = 'No appointments today';
        } else if (!now) {
            elNow.style.display = 'none';
            elIdle.style.display = '';
            elIdle.textContent = 'Waiting for next patient';
        } else {
            elIdle.style.display = 'none';
            elNow.style.display = '';
            elSerial.textContent = now.serial == null ? '-' : now.serial;
            elPatient.textContent = now.patient_name || '';
            elTime.textContent = now.estimated_time || '';
        }

        elWaiting.textContent = next.length;

        var html = '';
        if (next.length === 0) {
            html = '<li class="lb-none">' + (rows.length === 0
                ? 'No appointments today'
                : 'No one waiting') + '</li>';
        } else {
            for (var j = 0; j < next.length; j++) {
                var r = next[j];
                html += '<li class="lb-row">'
                    + '<span class="lb-row-serial">' + esc(r.serial) + '</span>'
                    + '<span class="lb-row-name">' + esc(r.patient_name) + '</span>'
                    + '<span class="lb-row-time">' + esc(r.estimated_time) + '</span>'
                    + '</li>';
            }
        }
        elUpNext.innerHTML = html;
    }

    function schedule(delay) {
        clearTimeout(pollTimer);
        pollTimer = setTimeout(poll, delay);
    }

    function poll() {
        if (document.hidden) return; // visibilitychange resumes polling
        fetch(DATA_URL, { headers: { 'Accept': 'application/json' }, cache: 'no-store' })
            .then(function (res) {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            })
            .then(function (data) {
                render(data);
                lastOk = Date.now();
                elConn.textContent = 'Live';
                elConn.className = 'lb-conn-ok';
                schedule(POLL_MS);
            })
            .catch(function () {
                elConn.textContent = 'Connection lost — retrying';
                elConn.className = 'lb-conn-bad';
                schedule(RETRY_MS);
            });
    }

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            clearTimeout(pollTimer);
        } else {
            poll();
        }
    });

    document.getElementById('lbFullscreen').addEventListener('click', function () {
        if (!document.fullscreenElement) {
            (document.documentElement.requestFullscreen || function () {}).call(document.documentElement);
        } else {
            (document.exitFullscreen || function () {}).call(document);
        }
    });

    document.getElementById('lbExit').addEventListener('click', function () {
        if (window.history.length > 1) {
            window.history.back();
        } else {
            window.close();
        }
    });

    tickClock();
    tickTimer = setInterval(tickClock, 1000);
    schedule(POLL_MS);
})();
</script>
@endpush
