@php
$days = ['saturday' => 'Saturday', 'sunday' => 'Sunday', 'monday' => 'Monday', 'tuesday' => 'Tuesday', 'wednesday' => 'Wednesday', 'thursday' => 'Thursday', 'friday' => 'Friday'];

// Priority: old input (validation failure) > existing doctor availabilities > one blank row.
$initial = collect(old('availabilities', []))->map(function ($a) {
    return [
        'day' => $a['day'] ?? '',
        'start' => $a['start_time'] ?? '',
        'end' => $a['end_time'] ?? '',
        'duration' => $a['slot_duration'] ?? 10,
    ];
})->values()->all();

if (empty($initial) && isset($doctor) && $doctor->relationLoaded('availabilities') && $doctor->availabilities->isNotEmpty()) {
    $initial = $doctor->availabilities->map(function ($a) {
        return [
            'day' => $a->day_of_week,
            'start' => substr((string) $a->start_time, 0, 5),
            'end' => substr((string) $a->end_time, 0, 5),
            'duration' => $a->slot_duration ?? 10,
        ];
    })->values()->all();
}

if (empty($initial)) {
    $initial = [['day' => '', 'start' => '09:00', 'end' => '17:00', 'duration' => 10]];
}
@endphp

<div class="card mt-3" x-data="{ availabilities: @js($initial) }">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong><i class="bi bi-calendar-week me-1"></i>Weekly Availability</strong>
        <button type="button" class="btn btn-sm btn-outline-primary"
                @click="availabilities.push({ day: '', start: '09:00', end: '17:00', duration: 10 })">
            <i class="bi bi-plus-lg me-1"></i>Add Availability
        </button>
    </div>
    <div class="card-body">
        <template x-if="availabilities.length === 0">
            <p class="text-muted mb-0">No availability set. Click “Add Availability” to add.</p>
        </template>
        <template x-for="(avail, index) in availabilities" :key="index">
            <div class="row g-2 mb-2 align-items-center">
                <div class="col-md-3">
                    <select :name="`availabilities[${index}][day]`" x-model="avail.day" class="form-select" required>
                        <option value="">Select Day</option>
                        @foreach($days as $val => $label)
                            <option value="{{ $val }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="time" :name="`availabilities[${index}][start_time]`" class="form-control" x-model="avail.start" required>
                </div>
                <div class="col-md-2">
                    <input type="time" :name="`availabilities[${index}][end_time]`" class="form-control" x-model="avail.end" required>
                </div>
                <div class="col-md-2">
                    <input type="number" :name="`availabilities[${index}][slot_duration]`" class="form-control"
                           x-model.number="avail.duration" min="5" max="60" placeholder="min">
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
