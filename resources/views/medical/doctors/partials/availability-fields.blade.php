@php
$days = ['saturday' => 'Saturday', 'sunday' => 'Sunday', 'monday' => 'Monday', 'tuesday' => 'Tuesday', 'wednesday' => 'Wednesday', 'thursday' => 'Thursday', 'friday' => 'Friday'];

// Priority: old input (validation failure) > existing doctor availabilities > one blank row.
$initial = collect(old('availabilities', []))->map(function ($a) {
    return [
        'day' => $a['day'] ?? '',
        'start' => $a['start_time'] ?? '',
        'end' => $a['end_time'] ?? '',
        'duration' => $a['slot_duration'] ?? 10,
        'room' => $a['room_no'] ?? '',
    ];
})->values()->all();

if (empty($initial) && isset($doctor) && $doctor->relationLoaded('availabilities') && $doctor->availabilities->isNotEmpty()) {
    $initial = $doctor->availabilities->map(function ($a) {
        return [
            'day' => $a->day_of_week,
            'start' => substr((string) $a->start_time, 0, 5),
            'end' => substr((string) $a->end_time, 0, 5),
            'duration' => $a->slot_duration ?? 10,
            'room' => $a->room_no ?? '',
        ];
    })->values()->all();
}

if (empty($initial)) {
    $initial = [['day' => '', 'start' => '09:00', 'end' => '17:00', 'duration' => 10, 'room' => '']];
}
@endphp

<div class="card mt-3" id="availability-card" x-data="{ availabilities: @js($initial) }">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong><i class="bi bi-calendar-week me-1"></i>Weekly Availability</strong>
        <button type="button" class="btn btn-sm btn-outline-primary" id="availability-add-btn"
                @click="availabilities.push({ day: '', start: '09:00', end: '17:00', duration: 10, room: '' })">
            <i class="bi bi-plus-lg me-1"></i>Add Availability
        </button>
    </div>
    <div class="card-body" id="availability-body">
        <div id="availability-warning" class="alert alert-warning d-none" role="alert">
            <strong><i class="bi bi-exclamation-triangle me-1"></i>Please fix availability before saving:</strong>
            <ul id="availability-warning-list" class="mb-0 mt-1 ps-3"></ul>
        </div>
        <template x-if="availabilities.length === 0">
            <p class="text-muted mb-0">No availability set. Click “Add Availability” to add.</p>
        </template>
        <template x-for="(avail, index) in availabilities" :key="index">
            <div class="row g-2 mb-2 align-items-center" data-avail-row>
                <div class="col-md-2">
                    <select :name="`availabilities[${index}][day]`" x-model="avail.day" class="form-select" data-avail-day required>
                        <option value="">Select Day</option>
                        @foreach($days as $val => $label)
                            <option value="{{ $val }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="time" :name="`availabilities[${index}][start_time]`" class="form-control" data-avail-start x-model="avail.start" required>
                </div>
                <div class="col-md-2">
                    <input type="time" :name="`availabilities[${index}][end_time]`" class="form-control" data-avail-end x-model="avail.end" required>
                </div>
                <div class="col-md-2">
                    <input type="number" :name="`availabilities[${index}][slot_duration]`" class="form-control"
                           x-model.number="avail.duration" min="5" max="60" placeholder="min" title="Slot length (minutes)">
                </div>
                <div class="col-md-2">
                    <input type="text" :name="`availabilities[${index}][room_no]`" class="form-control" data-avail-room
                           x-model="avail.room" maxlength="50" placeholder="Room no." title="Room no.">
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-outline-danger btn-sm w-100"
                            @click="availabilities.splice(index, 1)">
                        <i class="bi bi-trash me-1"></i>Remove
                    </button>
                </div>
            </div>
        </template>
        @error('availabilities')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
    </div>
</div>

<script>
(function () {
    const card = document.getElementById('availability-card');
    if (!card) return;
    const form = card.closest('form');
    const warningBox = document.getElementById('availability-warning');
    const warningList = document.getElementById('availability-warning-list');

    function cap(s) { return s ? s.charAt(0).toUpperCase() + s.slice(1) : s; }

    function collectRows() {
        return Array.from(card.querySelectorAll('[data-avail-row]')).map(function (row, i) {
            const dayEl = row.querySelector('[data-avail-day]');
            const startEl = row.querySelector('[data-avail-start]');
            const endEl = row.querySelector('[data-avail-end]');
            return {
                n: i + 1,
                day: (dayEl?.value || '').trim().toLowerCase(),
                start: (startEl?.value || '').trim().slice(0, 5),
                end: (endEl?.value || '').trim().slice(0, 5),
                dayEl, startEl, endEl,
            };
        });
    }

    function validate() {
        const rows = collectRows();
        const errors = [];
        const seen = {};

        rows.forEach(function (r) {
            if (!r.day || !r.start || !r.end) return;
            if (r.end <= r.start) {
                errors.push({ row: r, msg: `Row ${r.n}: End time (${r.end}) must be after start time (${r.start}) on ${cap(r.day)}.` });
            }
            const key = r.day + '|' + r.start;
            if (seen[key]) {
                errors.push({ row: r, msg: `Row ${r.n}: Duplicate — ${cap(r.day)} at ${r.start} is already used in row ${seen[key].n}. Remove or change it.` });
            } else {
                seen[key] = r;
            }
        });

        // Overlap check per day.
        const byDay = {};
        rows.forEach(function (r) {
            if (!r.day || !r.start || !r.end) return;
            (byDay[r.day] = byDay[r.day] || []).push(r);
        });
        Object.keys(byDay).forEach(function (day) {
            const sorted = byDay[day].slice().sort((a, b) => a.start < b.start ? -1 : a.start > b.start ? 1 : 0);
            for (let i = 1; i < sorted.length; i++) {
                if (sorted[i].start < sorted[i - 1].end) {
                    errors.push({ row: sorted[i], msg: `Row ${sorted[i].n}: Overlaps ${cap(day)} ${sorted[i - 1].start}–${sorted[i - 1].end} (row ${sorted[i - 1].n}).` });
                }
            }
        });

        return { rows, errors };
    }

    function render() {
        const { rows, errors } = validate();
        rows.forEach(function (r) {
            [r.dayEl, r.startEl, r.endEl].forEach(function (el) { el?.classList.remove('is-invalid'); });
        });
        if (!errors.length) {
            warningBox?.classList.add('d-none');
            if (warningList) warningList.innerHTML = '';
            return errors;
        }
        if (warningList) {
            warningList.innerHTML = '';
            // Deduplicate identical messages.
            [...new Set(errors.map(e => e.msg))].forEach(function (msg) {
                const li = document.createElement('li');
                li.textContent = msg;
                warningList.appendChild(li);
            });
        }
        warningBox?.classList.remove('d-none');
        errors.forEach(function (e) {
            e.row.dayEl?.classList.add('is-invalid');
            e.row.startEl?.classList.add('is-invalid');
            e.row.endEl?.classList.add('is-invalid');
        });
        return errors;
    }

    // Live warning as the user types/changes, including Alpine add/remove.
    card.addEventListener('input', function () { render(); });
    card.addEventListener('change', function () { render(); });
    new MutationObserver(function () { render(); }).observe(card, { childList: true, subtree: true });

    // Block submit while duplicates/overlaps exist — warn before submit.
    form?.addEventListener('submit', function (e) {
        const errors = render();
        if (errors.length) {
            e.preventDefault();
            e.stopPropagation();
            warningBox?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });

    // Default each row's Room no. from the doctor-level Room field above.
    // Only fills rows that are still empty, so per-day edits are preserved.
    const roomSrc = document.getElementById('room_no');
    function applyDefaultRoom() {
        if (!roomSrc) return;
        const def = roomSrc.value.trim();
        if (!def) return;
        card.querySelectorAll('[data-avail-room]').forEach(function (input) {
            if (input.value.trim() === '') {
                input.value = def;
                input.dispatchEvent(new Event('input', { bubbles: true }));
            }
        });
    }
    roomSrc?.addEventListener('input', applyDefaultRoom);
    document.getElementById('availability-add-btn')?.addEventListener('click', function () {
        setTimeout(applyDefaultRoom, 0);
    });

    render();
    applyDefaultRoom();
})();
</script>
