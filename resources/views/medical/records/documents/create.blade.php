@extends('layouts.institute')

@section('title', 'Upload Document — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="mb-0"><i class="bi bi-upload"></i> Upload Medical Document</h4>
        <a href="{{ route('medical.records.documents.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <p class="text-muted small">Next number: <strong>{{ $documentNumber }}</strong> (assigned on save)</p>
            <form method="POST" action="{{ route('medical.records.documents.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Patient *</label>
                        <select name="patient_id" class="form-select @error('patient_id') is-invalid @enderror" required>
                            <option value="">Select patient...</option>
                            @foreach($patients as $p)
                                <option value="{{ $p->id }}" {{ (string) old('patient_id', $preselectedPatient) === (string) $p->id ? 'selected' : '' }}>
                                    {{ $p->full_name ?? ($p->first_name . ' ' . $p->last_name) }} ({{ $p->mr_number ?? $p->id }})
                                </option>
                            @endforeach
                        </select>
                        @error('patient_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Document Type *</label>
                        <select name="document_type" class="form-select @error('document_type') is-invalid @enderror" required>
                            @foreach($types as $key => $label)
                                <option value="{{ $key }}" {{ old('document_type') === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('document_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Document Date</label>
                        <input type="date" name="document_date" class="form-control" value="{{ old('document_date', date('Y-m-d')) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Title *</label>
                        <input type="text" name="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title') }}" required>
                        @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">File * (max 20MB, auto-compressed to ~100KB)</label>
                        <input type="file" name="file" id="docFile" class="form-control @error('file') is-invalid @enderror" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx,.txt,.dcm" required>
                        <div class="form-text">Images are compressed in your browser and on the server to ~100KB. PDFs are compressed on the server to ~100KB (needs Ghostscript). Office/DICOM files are stored as-is (max 20MB).</div>
                        <div id="compressStatus" class="form-text text-info d-none"></div>
                        @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2">{{ old('description') }}</textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Access Level</label>
                        <select name="access_level" class="form-select">
                            @foreach($levels as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Tags (comma separated)</label>
                        <input type="text" name="tags" class="form-control" value="{{ old('tags') }}">
                    </div>
                    <div class="col-md-4 d-flex align-items-end gap-3">
                        <div class="form-check">
                            <input type="checkbox" name="is_confidential" value="1" class="form-check-input" id="conf">
                            <label class="form-check-label" for="conf">Confidential</label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" name="is_patient_visible" value="1" class="form-check-input" id="vis">
                            <label class="form-check-label" for="vis">Patient visible</label>
                        </div>
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-upload"></i> Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Client-side image pre-compression toward ~100KB so big photos upload fast.
// Server re-compresses again as the source of truth (images + PDFs).
(function () {
    const TARGET = 100 * 1024;
    const input = document.getElementById('docFile');
    const status = document.getElementById('compressStatus');
    if (!input) return;

    const fmt = (b) => b >= 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.round(b / 1024) + ' KB';

    function show(msg) {
        if (!status) return;
        status.textContent = msg;
        status.classList.remove('d-none');
    }

    async function compressImage(file) {
        const bitmap = await createImageBitmap(file);
        let w = bitmap.width, h = bitmap.height;
        const MAX = 1600;
        if (w > MAX || h > MAX) {
            const r = Math.min(MAX / w, MAX / h);
            w = Math.round(w * r); h = Math.round(h * r);
        }
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        let qualitySteps = [0.82, 0.72, 0.62, 0.52, 0.42, 0.32];

        for (let shrink = 0; shrink < 6; shrink++) {
            canvas.width = w; canvas.height = h;
            ctx.fillStyle = '#fff';
            ctx.fillRect(0, 0, w, h);
            ctx.drawImage(bitmap, 0, 0, w, h);
            for (const q of qualitySteps) {
                const blob = await new Promise((res) => canvas.toBlob(res, 'image/jpeg', q));
                if (blob && blob.size <= TARGET) return blob;
                if (blob && !compressImage._best || (blob && blob.size < (compressImage._best?.size || Infinity))) {
                    compressImage._best = blob;
                }
            }
            w = Math.round(w * 0.8); h = Math.round(h * 0.8);
            if (w < 320 || h < 320) break;
        }
        return compressImage._best || null;
    }

    input.addEventListener('change', async () => {
        compressImage._best = null;
        const file = input.files && input.files[0];
        if (!file) return;
        if (!file.type.startsWith('image/')) {
            if (file.type === 'application/pdf' && file.size > TARGET) {
                show('Original: ' + fmt(file.size) + ' — PDF will be auto-compressed on the server to ~100KB.');
            } else {
                show('Original: ' + fmt(file.size));
            }
            return;
        }
        if (file.size <= TARGET) {
            show('Original: ' + fmt(file.size) + ' — already under ~100KB.');
            return;
        }
        show('Compressing ' + fmt(file.size) + ' → ~100KB in browser…');
        try {
            const blob = await compressImage(file);
            if (blob) {
                const name = (file.name.replace(/\.[^.]+$/, '') || 'document') + '.jpg';
                const compressed = new File([blob], name, { type: 'image/jpeg' });
                const dt = new DataTransfer();
                dt.items.add(compressed);
                input.files = dt.files;
                show('Original: ' + fmt(file.size) + ' → ready to upload: ' + fmt(compressed.size) + ' (server enforces ~100KB).');
            } else {
                show('Could not pre-compress; server will compress after upload.');
            }
        } catch (e) {
            show('Browser compression skipped; server will compress after upload.');
        }
    });
})();
</script>
@endsection
