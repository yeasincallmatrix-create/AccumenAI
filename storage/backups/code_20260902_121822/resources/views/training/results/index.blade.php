@extends('layouts.institute')
@section('title', 'Results — Training')
@section('page_title', 'Results')
@section('content')
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb small mb-0">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="#" class="text-decoration-none">Training</a></li>
        <li class="breadcrumb-item active">Results</li>
    </ol>
</nav>
<div class="page-header">
    <h4>Training Results</h4>
    <p class="text-muted small">Batch-wise results — dedicated page (was <code>exams?view=results</code>).</p>
</div>
<div class="admin-card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Batch</th><th>Course</th><th>Enrolled</th><th>Exams</th><th>Pass Rate</th><th>Status</th><th class="text-end">Action</th></tr></thead>
            <tbody>
            @forelse($batches as $batch)
                <tr>
                    <td class="fw-semibold">{{ $batch->name }} <div class="small text-muted">{{ $batch->batch_code ?? '' }}</div></td>
                    <td class="small text-muted">{{ $batch->course?->name ?? '—' }}</td>
                    <td>{{ $batch->computed_total }}</td>
                    <td>{{ $batch->exams_count }}</td>
                    <td><span class="badge text-bg-light">{{ $batch->computed_rate }}%</span></td>
                    <td>
                        @if($batch->is_published ?? false)
                            <span class="badge text-bg-success">Published ({{ $batch->published_count }})</span>
                        @else
                            <span class="badge text-bg-secondary">Not Published</span>
                        @endif
                    </td>
                    <td class="text-end d-flex gap-1 justify-content-end">
                        @if($batch->is_published ?? false)
                            <span class="btn btn-sm btn-outline-success disabled"><i class="bi bi-check-lg me-1"></i> Published</span>
                            @if($batch->status === 'completed')
                            {{-- Re-evaluate immediately after Publish --}}
                            <form action="{{ route('training.results.re-evaluate', $batch) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-warning" onclick="return confirm('Re-evaluate results? This will recalculate using the latest marks.')">
                                    <i class="bi bi-arrow-repeat"></i> Re-evaluate
                                </button>
                            </form>
                            @endif
                        @elseif($batch->status === 'completed')
                            <form method="POST" action="{{ route('training.results.publish', $batch) }}" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-primary" onclick="return confirm('Publish results for {{ addslashes($batch->name) }}? This will calculate aggregates.')"><i class="bi bi-send me-1"></i> Publish</button>
                            </form>
                            {{-- Re-evaluate immediately after Publish --}}
                            <form action="{{ route('training.results.re-evaluate', $batch) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-warning" onclick="return confirm('Re-evaluate results? This will recalculate using the latest marks.')">
                                    <i class="bi bi-arrow-repeat"></i> Re-evaluate
                                </button>
                            </form>
                        @else
                            <span class="text-muted small">Not publishable</span>
                            <span class="badge bg-secondary">{{ $batch->status }}</span>
                        @endif
                        <button type="button" class="btn btn-sm btn-outline-primary js-cert-btn" data-url="{{ route('training.certificates.index', ['batch_id' => $batch->id]) }}" data-batch="{{ $batch->name }}"><i class="bi bi-patch-check me-1"></i> Certificates</button>
                        @if($batch->computed_total > 0)
                        @php $sampleTrainee = \App\Models\Training\Enrollment::where('batch_id',$batch->id)->first(); $sid = $sampleTrainee?->trainee_id ?? \App\Models\Training\Enrollment::where('batch_id',$batch->id)->first()?->student_id; @endphp
                        @if($sid)
                        <a href="{{ route('training.results.marksheet', [$batch->id, $sid]) }}" target="_blank" class="btn btn-sm btn-primary">
                            <i class="bi bi-printer"></i> Print
                        </a>
                        @else
                        <span class="btn btn-sm btn-outline-secondary disabled">No trainees</span>
                        @endif
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">No batches yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<!-- Certificates popup -->
<div class="modal fade" id="certPopupModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-patch-check me-1"></i> Certificates — <span id="certPopupBatch"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3" id="certPopupBody" style="min-height:200px;">
                <div class="text-center py-5 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Loading...</div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function(){
    const modalEl = document.getElementById('certPopupModal');
    const bodyEl = document.getElementById('certPopupBody');
    const batchEl = document.getElementById('certPopupBatch');
    if(!modalEl || !bodyEl) return;
    function bindPopupForm(container){
        const checkAll = container.querySelector('#certCheckAll');
        if(checkAll){
            checkAll.addEventListener('change', function(){
                container.querySelectorAll('.cert-check:not(:disabled)').forEach(c=> c.checked = checkAll.checked);
            });
        }
        const genSelected = container.querySelector('#certGenerateSelected');
        const form = container.querySelector('#trainingCertForm');
        if(genSelected && form){
            genSelected.addEventListener('click', function(){ form.submit(); });
        }
    }
    document.querySelectorAll('.js-cert-btn').forEach(btn=>{
        btn.addEventListener('click', function(){
            let url = this.getAttribute('data-url');
            // request popup mode so certificate page returns only the Generate block
            url += (url.includes('?') ? '&' : '?') + 'popup=1';
            const batch = this.getAttribute('data-batch') || '';
            if(batchEl) batchEl.textContent = batch;
            bodyEl.innerHTML = '<div class="text-center py-5 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Loading...</div>';
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
            fetch(url, {headers:{'X-Requested-With':'XMLHttpRequest','Accept':'text/html'}, credentials:'same-origin'})
                .then(r=>{ if(!r.ok) throw new Error('HTTP '+r.status); return r.text(); })
                .then(html=>{
                    const doc = new DOMParser().parseFromString(html, 'text/html');
                    // Find the Generate Certificates card - first .admin-card.mb-4 that contains that heading
                    let target = null;
                    doc.querySelectorAll('.admin-card').forEach(card=>{
                        const h = card.querySelector('h6');
                        if(h && h.textContent.includes('Generate Certificates')){
                            if(!target) target = card;
                        }
                    });
                    // fallback: first .admin-card.mb-4
                    if(!target) target = doc.querySelector('.admin-card.mb-4');
                    if(!target) target = doc.querySelector('.admin-card');
                    if(target){
                        bodyEl.innerHTML = target.outerHTML;
                        bindPopupForm(bodyEl);
                    } else {
                        // fallback: show main content half
                        const main = doc.querySelector('main.content') || doc.body;
                        bodyEl.innerHTML = main.innerHTML;
                    }
                })
                .catch(err=>{
                    bodyEl.innerHTML = '<div class="alert alert-danger m-3">Failed to load certificates: '+ err.message +'<br><a href="'+ url +'" target="_blank" class="alert-link">Open full page</a></div>';
                });
        });
    });
})();
</script>
@endpush
