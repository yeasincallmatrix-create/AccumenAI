@extends('layouts.institute')

@section('title', $employee->display_name.' — HR')

@section('content')

<div class="standalone-heading">
    <h4>{{ $employee->display_name }} <small class="text-muted"><code>{{ $employee->employee_code }}</code></small></h4>
    <p>{{ $employee->employment_status === 'active' ? 'Active' : ucfirst($employee->employment_status) }} @if($employee->employment_type) · {{ ucwords(str_replace('_',' ', $employee->employment_type)) }} @endif @if($currentPeriod) · Since {{ $currentPeriod->start_date->format('Y-m-d') }} @endif · {{ $totalServiceDays }} days total</p>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('hr.employees.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
        @if ($canUpdate)
            <a href="{{ route('hr.employees.edit', $employee) }}" class="btn btn-primary btn-sm"><i class="bi bi-pencil-square me-1"></i>Edit</a>
        @endif
        @if ($canDelete)
            <form method="POST" action="{{ route('hr.employees.destroy', $employee) }}" onsubmit="return confirm('Delete this employee? It will be soft-deleted and recoverable.');">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash me-1"></i>Delete</button>
            </form>
        @endif
    </div>
</div>

@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif
@if ($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="row g-3">
    <div class="col-md-4">
        <div class="admin-card p-3 text-center">
            @if ($employee->profile_photo)
                <img src="{{ Storage::url($employee->profile_photo) }}" class="rounded mb-2" style="width:140px;height:180px;object-fit:cover;" alt="">
            @else
                <div class="avatar-circle avatar-initials mx-auto mb-2" style="width:80px;height:80px;font-size:32px;line-height:80px;">{{ strtoupper(substr($employee->display_name,0,1)) }}</div>
            @endif
            <div class="fw-semibold">{{ $employee->display_name }}</div>
            <div class="text-muted small"><code>{{ $employee->employee_code }}</code></div>
            <div class="mt-2">
                <span class="badge {{ $employee->employment_status === 'active' ? 'text-bg-success' : ($employee->employment_status === 'resigned' ? 'text-bg-warning' : ($employee->employment_status === 'terminated' ? 'text-bg-danger' : 'text-bg-secondary')) }}">{{ ucfirst($employee->employment_status) }}</span>
                @if ($employee->employment_type)
                    <span class="badge text-bg-light border">{{ ucwords(str_replace('_',' ', $employee->employment_type)) }}</span>
                @endif
            </div>
            <hr>
            <div class="text-start small">
                <div><span class="text-muted">Branch</span><br><strong>{{ $employee->branch?->name ?? 'Institute-wide' }}</strong></div>
                <div class="mt-2"><span class="text-muted">Department</span><br><strong>{{ $employee->department?->name ?? '—' }}</strong></div>
                <div class="mt-2"><span class="text-muted">Designation</span><br><strong>{{ $employee->designation?->name ?? '—' }}</strong></div>
                <div class="mt-2"><span class="text-muted">Manager</span><br>
                    @if ($employee->reportingManager)
                        <a href="{{ route('hr.employees.show', $employee->reportingManager) }}" class="text-decoration-none"><strong>{{ $employee->reportingManager->display_name }}</strong> <code>{{ $employee->reportingManager->employee_code }}</code></a>
                    @else
                        <strong>—</strong>
                    @endif
                </div>
                <div class="mt-2"><span class="text-muted">Joining</span><br><strong>{{ $employee->joining_date?->format('Y-m-d') ?? '—' }}</strong></div>
            </div>
        </div>

        <div class="admin-card p-3 mt-3">
            <h6>Employment Summary</h6>
            <div class="small">
                <div class="d-flex justify-content-between"><span class="text-muted">Current Period</span><strong>{{ $currentPeriod ? $currentPeriod->start_date->format('Y-m-d').' — '.($currentPeriod->end_date?->format('Y-m-d') ?? 'present') : '—' }}</strong></div>
                <div class="d-flex justify-content-between mt-1"><span class="text-muted">Total Service</span><strong>{{ $totalServiceDays }} days</strong></div>
                <div class="d-flex justify-content-between mt-1"><span class="text-muted">Periods</span><strong>{{ $periods->count() }}</strong></div>
                <div class="d-flex justify-content-between mt-1"><span class="text-muted">History Events</span><strong>{{ $histories->count() }}</strong></div>
            </div>
            @if ($periods->isNotEmpty())
                <hr>
                <h6 class="small">Employment Periods</h6>
                <div class="table-responsive">
                    <table class="table table-sm small mb-0">
                        <thead><tr><th>Start</th><th>End</th><th>Status</th><th>Reason</th></tr></thead>
                        <tbody>
                            @foreach ($periods as $p)
                                <tr>
                                    <td>{{ $p->start_date->format('Y-m-d') }}</td>
                                    <td>{{ $p->end_date?->format('Y-m-d') ?? '—' }}</td>
                                    <td><span class="badge {{ $p->status === 'active' ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $p->status }}</span></td>
                                    <td>{{ $p->end_reason ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
    <div class="col-md-8">
        <div class="admin-card p-3">
            <h6>Identity &amp; Contact</h6>
            <div class="row g-2 small">
                <div class="col-6"><span class="text-muted">First</span><br><strong>{{ $employee->first_name }}</strong></div>
                <div class="col-6"><span class="text-muted">Middle</span><br><strong>{{ $employee->middle_name ?? '—' }}</strong></div>
                <div class="col-6"><span class="text-muted">Last</span><br><strong>{{ $employee->last_name }}</strong></div>
                <div class="col-6"><span class="text-muted">Gender</span><br><strong>{{ $employee->gender ? ucfirst($employee->gender) : '—' }}</strong></div>
                <div class="col-6"><span class="text-muted">DOB</span><br><strong>{{ $employee->date_of_birth?->format('Y-m-d') ?? '—' }}</strong></div>
                <div class="col-6"><span class="text-muted">Joining Date</span><br><strong>{{ $employee->joining_date?->format('Y-m-d') ?? '—' }}</strong></div>
                <div class="col-6"><span class="text-muted">Phone</span><br><strong>{{ $employee->phone ?? '—' }}</strong></div>
                <div class="col-6"><span class="text-muted">Email</span><br><strong>{{ $employee->email ?? '—' }}</strong></div>
                <div class="col-12"><span class="text-muted">Address</span><br><strong>{{ $employee->address ?? '—' }}</strong></div>
                <div class="col-6"><span class="text-muted">National ID</span><br><strong>{{ $employee->national_id ?? '—' }}</strong></div>
                <div class="col-6"><span class="text-muted">Passport</span><br><strong>{{ $employee->passport_no ?? '—' }}</strong></div>
                <div class="col-6"><span class="text-muted">Emergency Name</span><br><strong>{{ $employee->emergency_contact_name ?? '—' }}</strong></div>
                <div class="col-6"><span class="text-muted">Emergency Phone</span><br><strong>{{ $employee->emergency_contact_phone ?? '—' }}</strong></div>
            </div>
            @if ($employee->notes)
                <hr>
                <h6>Notes</h6>
                <p class="small text-muted mb-0">{{ $employee->notes }}</p>
            @endif
            <hr>
            <div class="small text-muted">Created {{ $employee->created_at?->diffForHumans() }} @if($employee->updated_at && $employee->updated_at != $employee->created_at) · Updated {{ $employee->updated_at->diffForHumans() }} @endif</div>
        </div>

        @if ($canHistory)
        <div class="admin-card p-3 mt-3">
            <h6>Employment History — Timeline</h6>
            <p class="small text-muted">Chronological, immutable. Effective date, previous → new, reason, changed by.</p>
            @if ($histories->isEmpty())
                <div class="text-muted small py-2">No history yet.</div>
            @else
                <div class="table-responsive">
                    <table class="table table-sm small align-middle mb-0">
                        <thead><tr><th>Date</th><th>Event</th><th>Details</th><th>By</th></tr></thead>
                        <tbody>
                            @foreach ($histories as $h)
                                <tr>
                                    <td>{{ $h->effective_date->format('Y-m-d') }}</td>
                                    <td><span class="badge text-bg-light border">{{ str_replace('_',' ', $h->event_type) }}</span>@if($h->approval_status) <span class="badge {{ $h->approval_status === 'approved' ? 'text-bg-success' : ($h->approval_status === 'pending' ? 'text-bg-warning' : 'text-bg-danger') }}">{{ $h->approval_status }}</span> @endif</td>
                                    <td>
                                        @if($h->previous_branch_id || $h->new_branch_id) Branch: {{ $h->previousBranch?->name ?? '—' }} → {{ $h->newBranch?->name ?? '—' }}<br> @endif
                                        @if($h->previous_department_id || $h->new_department_id) Dept: {{ $h->previousDepartment?->name ?? '—' }} → {{ $h->newDepartment?->name ?? '—' }}<br> @endif
                                        @if($h->previous_designation_id || $h->new_designation_id) Desig: {{ $h->previousDesignation?->name ?? '—' }} → {{ $h->newDesignation?->name ?? '—' }}<br> @endif
                                        @if($h->previous_manager_id || $h->new_manager_id) Mgr: {{ $h->previousManager?->display_name ?? '—' }} → {{ $h->newManager?->display_name ?? '—' }}<br> @endif
                                        @if($h->previous_employment_type || $h->new_employment_type) Type: {{ $h->previous_employment_type ?? '—' }} → {{ $h->new_employment_type ?? '—' }}<br> @endif
                                        @if($h->previous_employment_status || $h->new_employment_status) Status: {{ $h->previous_employment_status ?? '—' }} → {{ $h->new_employment_status ?? '—' }}<br> @endif
                                        @if($h->new_salary_reference) Salary Ref: {{ $h->new_salary_reference }}<br> @endif
                                        @if($h->title) Title: {{ $h->title }}<br> @endif
                                        @if($h->reason) <span class="text-muted">Reason:</span> {{ $h->reason }}<br> @endif
                                        @if($h->notes) <span class="text-muted">Notes:</span> {{ $h->notes }} @endif
                                        @if($h->event_type === 'resignation' && $h->approval_status === 'pending' && $canResign)
                                            <div class="mt-1">
                                                <form method="POST" action="{{ route('hr.history.resign-decision', $h) }}" class="d-inline">
                                                    @csrf
                                                    <input type="hidden" name="decision" value="approved">
                                                    <button type="submit" class="btn btn-sm btn-success py-0">Approve</button>
                                                </form>
                                                <form method="POST" action="{{ route('hr.history.resign-decision', $h) }}" class="d-inline">
                                                    @csrf
                                                    <input type="hidden" name="decision" value="rejected">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger py-0">Reject</button>
                                                </form>
                                            </div>
                                        @endif
                                    </td>
                                    <td>{{ $h->changedBy?->email ?? '—' }}<div class="text-muted">{{ $h->created_at->format('Y-m-d H:i') }}</div></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
        @endif

        <div class="admin-card p-3 mt-3">
            <h6>Performance History <span class="badge bg-light border">{{ $performanceReviews->count() }}</span></h6>
            @if($performanceReviews->isEmpty())
                <div class="text-muted small py-2">No performance reviews.</div>
            @else
                <div class="table-responsive">
                    <table class="table table-sm small mb-0">
                        <thead><tr><th>Period</th><th>Score</th><th>Status</th><th>Reviewer</th><th></th></tr></thead>
                        <tbody>
                            @foreach($performanceReviews as $r)
                                <tr>
                                    <td>{{ $r->period->name }}</td>
                                    <td>{{ $r->overall_score ?? '—' }}</td>
                                    <td><span class="badge {{ $r->status==='approved'?'text-bg-success':'text-bg-warning' }}">{{ $r->status }}</span></td>
                                    <td>{{ $r->reviewer?->display_name ?? '—' }}</td>
                                    <td><a href="{{ route('hr.performance.reviews.show', $r) }}" class="btn btn-sm btn-outline-primary">View</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
            <a href="{{ route('hr.performance.reviews') }}" class="btn btn-sm btn-outline-secondary mt-2">All Reviews</a>
        </div>

        <div class="admin-card p-3 mt-3">
            <h6>Training History <span class="badge bg-light border">{{ $trainingEnrollments->count() }}</span></h6>
            @if($trainingEnrollments->isEmpty())
                <div class="text-muted small py-2">No trainings.</div>
            @else
                <div class="table-responsive">
                    <table class="table table-sm small mb-0">
                        <thead><tr><th>Training</th><th>Status</th><th>Result</th><th>Certificate</th></tr></thead>
                        <tbody>
                            @foreach($trainingEnrollments as $en)
                                <tr>
                                    <td>{{ $en->training->title }}<div class="text-muted small">{{ $en->training->start_date->format('Y-m-d') }}</div></td>
                                    <td>{{ $en->status }}</td>
                                    <td>{{ $en->result }}</td>
                                    <td>@if($en->certificate_path)<a href="{{ Storage::url($en->certificate_path) }}" target="_blank">View</a>@else — @endif</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
            <a href="{{ route('hr.training.programs') }}" class="btn btn-sm btn-outline-secondary mt-2">All Trainings</a>
        </div>

        <div class="admin-card p-3 mt-3">
            <h6>Skills <span class="badge bg-light border">{{ $skills->count() }}</span></h6>
            @if($skills->isEmpty())
                <div class="text-muted small py-2">No skills recorded.</div>
            @else
                <div class="d-flex flex-wrap gap-1">
                    @foreach($skills as $sk)
                        <span class="badge {{ $sk->verification_status==='verified'?'text-bg-success':'text-bg-secondary' }}">{{ $sk->skill_name }} ({{ $sk->proficiency_level }})</span>
                    @endforeach
                </div>
            @endif
            <a href="{{ route('hr.training.skills') }}" class="btn btn-sm btn-outline-secondary mt-2">All Skills</a>
        </div>

        <div class="admin-card p-3 mt-3">
            <h6>Lifecycle Actions</h6>
            <p class="small text-muted">All actions create immutable history, audit log, and update current assignment. Branch-restricted users cannot transfer outside their branch.</p>
            <div class="row g-2">
                @if ($canTransfer)
                    <div class="col-md-6">
                        <button class="btn btn-outline-primary btn-sm w-100" data-bs-toggle="modal" data-bs-target="#transferModal"><i class="bi bi-arrow-left-right me-1"></i>Transfer</button>
                    </div>
                @endif
                @if ($canPromote)
                    <div class="col-md-6">
                        <button class="btn btn-outline-primary btn-sm w-100" data-bs-toggle="modal" data-bs-target="#promoteModal"><i class="bi bi-award me-1"></i>Promotion / Demotion</button>
                    </div>
                @endif
                @if ($canResign && !in_array($employee->employment_status, ['resigned','terminated']))
                    <div class="col-md-6">
                        <button class="btn btn-outline-warning btn-sm w-100" data-bs-toggle="modal" data-bs-target="#resignModal"><i class="bi bi-box-arrow-right me-1"></i>Resign</button>
                    </div>
                @endif
                @if ($canTerminate && $employee->employment_status !== 'terminated')
                    <div class="col-md-6">
                        <button class="btn btn-outline-danger btn-sm w-100" data-bs-toggle="modal" data-bs-target="#terminateModal"><i class="bi bi-x-circle me-1"></i>Terminate</button>
                    </div>
                @endif
                @if ($canReactivate && in_array($employee->employment_status, ['resigned','terminated','inactive','suspended']))
                    <div class="col-md-6">
                        <button class="btn btn-success btn-sm w-100" data-bs-toggle="modal" data-bs-target="#reactivateModal"><i class="bi bi-arrow-counterclockwise me-1"></i>Rejoin / Reactivate</button>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- HR-3 Documents --}}
@if ($canDocView)
<div class="row mt-3">
    <div class="col-12">
        <div class="admin-card p-3" id="hrDocPanel"
             data-employee-id="{{ $employee->id }}"
             data-index-url="{{ route('hr.employees.documents.index', $employee) }}"
             data-store-url="{{ route('hr.employees.documents.store', $employee) }}"
             data-categories-url="{{ route('hr.documents.categories') }}"
             data-csrf="{{ csrf_token() }}"
             data-can-manage="{{ $canDocManage ? '1' : '0' }}"
             data-can-verify="{{ $canDocVerify ? '1' : '0' }}">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0"><i class="bi bi-folder2-open me-1"></i>Employee Documents</h6>
                <span class="badge bg-primary" id="hrDocCount">0</span>
            </div>
            <div id="hrDocStatus" class="d-none"></div>
            @if ($canDocManage)
            <form id="hrDocUploadForm" class="row g-2 mb-3" enctype="multipart/form-data">
                <div class="col-md-3"><select name="category_id" class="form-select form-select-sm" id="hrDocCategory" required><option value="">Select type *</option></select></div>
                <div class="col-md-3"><input type="text" name="title" class="form-control form-control-sm" maxlength="200" placeholder="Title (optional)"></div>
                <div class="col-md-3"><input type="text" name="document_number" class="form-control form-control-sm" maxlength="100" placeholder="Doc number / ref"></div>
                <div class="col-md-3"><input type="file" name="file" class="form-control form-control-sm" required></div>
                <div class="col-md-3"><label class="small text-muted">Issue date</label><input type="date" name="issue_date" class="form-control form-control-sm"></div>
                <div class="col-md-3"><label class="small text-muted">Expiry date</label><input type="date" name="expiry_date" class="form-control form-control-sm"></div>
                <div class="col-md-4"><input type="text" name="description" class="form-control form-control-sm" maxlength="2000" placeholder="Notes"></div>
                <div class="col-md-2 text-end"><button type="submit" class="btn btn-sm btn-primary w-100"><i class="bi bi-upload me-1"></i>Upload</button></div>
            </form>
            @endif
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0" id="hrDocTable">
                    <thead><tr><th>Type</th><th>Title / File</th><th>Number</th><th>Issue / Expiry</th><th>Verification</th><th class="text-end">Actions</th></tr></thead>
                    <tbody><tr><td colspan="6" class="text-center text-muted py-3">Loading…</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Edit / Verify / Reject modals --}}
<div class="modal fade" id="hrDocEditModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content">
        <form id="hrDocEditForm"><input type="hidden" name="id" id="hrDocEditId">
        <div class="modal-header"><h6 class="modal-title">Edit Document</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="mb-2"><label class="form-label small">Title</label><input type="text" name="title" id="hrDocEditTitle" class="form-control form-control-sm" maxlength="200"></div>
            <div class="mb-2"><label class="form-label small">Document number</label><input type="text" name="document_number" id="hrDocEditNumber" class="form-control form-control-sm" maxlength="100"></div>
            <div class="mb-2"><label class="form-label small">Category</label><select name="category_id" id="hrDocEditCategory" class="form-select form-select-sm"></select></div>
            <div class="mb-2"><label class="form-label small">Issue date</label><input type="date" name="issue_date" id="hrDocEditIssue" class="form-control form-control-sm"></div>
            <div class="mb-2"><label class="form-label small">Expiry date</label><input type="date" name="expiry_date" id="hrDocEditExpiry" class="form-control form-control-sm"></div>
            <div class="mb-2"><label class="form-label small">Notes</label><textarea name="description" id="hrDocEditDesc" class="form-control form-control-sm" rows="2" maxlength="2000"></textarea></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-sm btn-primary">Save</button></div>
        </form>
    </div></div>
</div>

<div class="modal fade" id="hrDocVerifyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content">
        <form id="hrDocVerifyForm"><input type="hidden" id="hrDocVerifyId">
        <div class="modal-header"><h6 class="modal-title">Verify Document</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body"><div class="mb-2"><label class="form-label small">Notes (optional)</label><textarea name="notes" id="hrDocVerifyNotes" class="form-control form-control-sm" rows="2" maxlength="2000"></textarea></div></div>
        <div class="modal-footer"><button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-sm btn-success">Verify</button></div>
        </form>
    </div></div>
</div>

<div class="modal fade" id="hrDocRejectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content">
        <form id="hrDocRejectForm"><input type="hidden" id="hrDocRejectId">
        <div class="modal-header"><h6 class="modal-title">Reject Document</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="mb-2"><label class="form-label small">Reason *</label><textarea name="reason" id="hrDocRejectReason" class="form-control form-control-sm" rows="2" maxlength="2000" required></textarea></div>
            <div class="mb-2"><label class="form-label small">Notes</label><textarea name="notes" id="hrDocRejectNotes" class="form-control form-control-sm" rows="2" maxlength="2000"></textarea></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-sm btn-danger">Reject</button></div>
        </form>
    </div></div>
</div>

@push('scripts')
<script>
(function(){
'use strict';
var panel=document.getElementById('hrDocPanel');
if(!panel) return;
function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}
function status(msg,ok){var el=document.getElementById('hrDocStatus');if(!msg){el.className='d-none';return;} el.className='alert alert-'+(ok?'success':'danger')+' py-1 px-2'; el.textContent=msg; el.classList.remove('d-none');}
function fetchJson(url, opts){
    opts=opts||{}; var headers={'Accept':'application/json'}; if(opts.method && ['POST','PATCH','DELETE'].indexOf(opts.method)!==-1) headers['X-CSRF-TOKEN']=panel.dataset.csrf;
    var body=opts.body; var isForm=typeof FormData!=='undefined' && body instanceof FormData; if(body && !isForm && typeof body!=='string'){headers['Content-Type']='application/json'; body=JSON.stringify(body);}
    return fetch(url,{method:opts.method||'GET',headers:headers,body:body,credentials:'same-origin'}).then(function(r){return r.json().catch(function(){return {success:false,message:'Error'};});});
}
function badgeFor(d){
    var s=d.effective_verification_status||d.verification_status;
    var cls='secondary'; if(s==='verified') cls='success'; else if(s==='pending_verification') cls='warning'; else if(s==='rejected') cls='danger'; else if(s==='expired') cls='dark';
    var label=s.replace('_',' '); return '<span class="badge text-bg-'+cls+'">'+esc(label)+'</span>' + (d.is_expired? ' <span class="badge text-bg-danger">expired</span>':'') + (d.is_expiring_soon && !d.is_expired ? ' <span class="badge text-bg-warning">expiring soon</span>':'');
}
function loadCategories(){
    fetchJson(panel.dataset.categoriesUrl+'?entity=hr-employee').then(function(res){
        if(!res||!res.success) return;
        var opts='<option value="">Select type *</option>'; var editOpts='';
        (res.data||[]).forEach(function(c){ opts+='<option value="'+c.id+'">'+esc(c.name)+(c.is_required?' *':'')+'</option>'; editOpts+='<option value="'+c.id+'">'+esc(c.name)+'</option>';});
        var sel=document.getElementById('hrDocCategory'); if(sel) sel.innerHTML=opts;
        var editSel=document.getElementById('hrDocEditCategory'); if(editSel) editSel.innerHTML=editOpts;
    });
}
function loadDocs(){
    fetchJson(panel.dataset.indexUrl).then(function(res){
        if(!res||!res.success) return;
        render(res.data||[]);
    });
}
function render(docs){
    var tbody=document.querySelector('#hrDocTable tbody'); var count=document.getElementById('hrDocCount'); if(count) count.textContent=docs.length;
    if(!docs.length){ tbody.innerHTML='<tr><td colspan="6" class="text-center text-muted py-3">No documents</td></tr>'; return;}
    var canManage=panel.dataset.canManage==='1', canVerify=panel.dataset.canVerify==='1';
    tbody.innerHTML=docs.map(function(d){
        var actions='<a href="'+esc(d.download_url)+'" class="btn btn-sm btn-outline-primary" title="Download"><i class="bi bi-download"></i></a>';
        if(canManage){
            actions+=' <button type="button" class="btn btn-sm btn-outline-secondary hr-doc-replace" data-url="'+esc(d.replace_url)+'" title="Replace"><i class="bi bi-arrow-repeat"></i></button>';
            actions+=' <button type="button" class="btn btn-sm btn-outline-secondary hr-doc-edit" data-id="'+d.id+'" data-title="'+esc(d.title||'')+'" data-number="'+esc(d.document_number||'')+'" data-category="'+(d.category_id||'')+'" data-issue="'+esc(d.issue_date||'')+'" data-expiry="'+esc(d.expiry_date||'')+'" data-desc="'+esc(d.description||'')+'" data-url="'+esc(d.update_url)+'" title="Edit"><i class="bi bi-pencil"></i></button>';
            if(d.status==='archived') actions+=' <button type="button" class="btn btn-sm btn-outline-success hr-doc-restore" data-url="'+esc(d.archive_url.replace("/archive","/restore"))+'" title="Restore"><i class="bi bi-arrow-counterclockwise"></i></button>';
            else actions+=' <button type="button" class="btn btn-sm btn-outline-warning hr-doc-archive" data-url="'+esc(d.archive_url)+'" title="Archive"><i class="bi bi-archive"></i></button>';
            actions+=' <button type="button" class="btn btn-sm btn-outline-danger hr-doc-delete" data-url="'+esc(d.delete_url)+'" title="Delete"><i class="bi bi-trash"></i></button>';
        }
        if(canVerify){
            if(d.verification_status!=='verified') actions+=' <button type="button" class="btn btn-sm btn-outline-success hr-doc-verify" data-id="'+d.id+'" data-url="'+esc(d.verify_url)+'" title="Verify"><i class="bi bi-check-circle"></i></button>';
            actions+=' <button type="button" class="btn btn-sm btn-outline-danger hr-doc-reject" data-id="'+d.id+'" data-url="'+esc(d.reject_url)+'" title="Reject"><i class="bi bi-x-circle"></i></button>';
        }
        actions+=' <button type="button" class="btn btn-sm btn-outline-info hr-doc-versions" data-url="'+esc(d.versions_url)+'" title="Versions">v'+esc(d.version)+'</button>';
        var title= d.title ? esc(d.title) : esc(d.original_filename);
        var issueExpiry=(d.issue_date? esc(d.issue_date):'—')+' / '+(d.expiry_date? esc(d.expiry_date):'—');
        return '<tr><td><span class="badge bg-light text-dark border">'+esc(d.category||'Other')+'</span></td><td><span class="fw-semibold">'+title+'</span><small class="text-muted d-block">'+esc(d.original_filename)+'</small></td><td>'+esc(d.document_number||'—')+'</td><td><small>'+esc(issueExpiry)+'</small></td><td>'+badgeFor(d)+(d.rejection_reason? '<small class="text-danger d-block">'+esc(d.rejection_reason)+'</small>':'')+'</td><td class="text-end text-nowrap">'+actions+'</td></tr>';
    }).join('');
}
var form=document.getElementById('hrDocUploadForm');
if(form){ form.addEventListener('submit',function(e){
    e.preventDefault(); var fd=new FormData(form);
    var btn=form.querySelector('[type="submit"]'); if(btn) btn.disabled=true;
    fetchJson(panel.dataset.storeUrl,{method:'POST',body:fd}).then(function(res){ if(btn) btn.disabled=false; status(res.message,res.success!==false); if(res.success===false) return; form.reset(); loadDocs();});
});}
document.addEventListener('click',function(e){
    var b=e.target.closest('.hr-doc-replace'); if(b){ var inp=document.createElement('input'); inp.type='file'; inp.addEventListener('change',function(){ if(!inp.files.length) return; var fd=new FormData(); fd.append('file',inp.files[0]); fetchJson(b.dataset.url,{method:'POST',body:fd}).then(function(res){ status(res.message,res.success!==false); if(res.success!==false) loadDocs();});}); inp.click(); return;}
    b=e.target.closest('.hr-doc-edit'); if(b){ document.getElementById('hrDocEditId').value=b.dataset.id; document.getElementById('hrDocEditId').dataset.url=b.dataset.url; document.getElementById('hrDocEditTitle').value=b.dataset.title||''; document.getElementById('hrDocEditNumber').value=b.dataset.number||''; document.getElementById('hrDocEditCategory').value=b.dataset.category||''; document.getElementById('hrDocEditIssue').value=b.dataset.issue||''; document.getElementById('hrDocEditExpiry').value=b.dataset.expiry||''; document.getElementById('hrDocEditDesc').value=b.dataset.desc||''; if(window.bootstrap) bootstrap.Modal.getOrCreateInstance(document.getElementById('hrDocEditModal')).show(); return;}
    b=e.target.closest('.hr-doc-archive,.hr-doc-delete'); if(b){ if(!confirm(b.classList.contains('hr-doc-delete')?'Delete this document?':'Archive this document?')) return; var m=b.classList.contains('hr-doc-delete')?'DELETE':'POST'; fetchJson(b.dataset.url,{method:m}).then(function(res){ status(res.message,res.success!==false); if(res.success!==false) loadDocs();}); return;}
    b=e.target.closest('.hr-doc-verify'); if(b){ document.getElementById('hrDocVerifyId').value=b.dataset.id; document.getElementById('hrDocVerifyId').dataset.url=b.dataset.url; if(window.bootstrap) bootstrap.Modal.getOrCreateInstance(document.getElementById('hrDocVerifyModal')).show(); return;}
    b=e.target.closest('.hr-doc-reject'); if(b){ document.getElementById('hrDocRejectId').value=b.dataset.id; document.getElementById('hrDocRejectId').dataset.url=b.dataset.url; if(window.bootstrap) bootstrap.Modal.getOrCreateInstance(document.getElementById('hrDocRejectModal')).show(); return;}
    b=e.target.closest('.hr-doc-versions'); if(b){ fetchJson(b.dataset.url).then(function(res){ if(!res||!res.success) return; alert('Versions: '+JSON.stringify(res.data,null,2));}); return;}
});
var editForm=document.getElementById('hrDocEditForm'); if(editForm){ editForm.addEventListener('submit',function(e){ e.preventDefault(); var idEl=document.getElementById('hrDocEditId'); var url=idEl.dataset.url; var fd=new FormData(editForm); fd.append('_method','PATCH'); fetchJson(url,{method:'POST',body:fd}).then(function(res){ status(res.message,res.success!==false); if(res.success===false) return; if(window.bootstrap) bootstrap.Modal.getInstance(document.getElementById('hrDocEditModal')).hide(); loadDocs();});});}
var vForm=document.getElementById('hrDocVerifyForm'); if(vForm){ vForm.addEventListener('submit',function(e){ e.preventDefault(); var url=document.getElementById('hrDocVerifyId').dataset.url; var fd=new FormData(); fd.append('notes',document.getElementById('hrDocVerifyNotes').value); fetchJson(url,{method:'POST',body:fd}).then(function(res){ status(res.message,res.success!==false); if(res.success===false) return; bootstrap.Modal.getInstance(document.getElementById('hrDocVerifyModal')).hide(); loadDocs();});});}
var rForm=document.getElementById('hrDocRejectForm'); if(rForm){ rForm.addEventListener('submit',function(e){ e.preventDefault(); var url=document.getElementById('hrDocRejectId').dataset.url; var fd=new FormData(); fd.append('reason',document.getElementById('hrDocRejectReason').value); fd.append('notes',document.getElementById('hrDocRejectNotes').value); fetchJson(url,{method:'POST',body:fd}).then(function(res){ status(res.message,res.success!==false); if(res.success===false) return; bootstrap.Modal.getInstance(document.getElementById('hrDocRejectModal')).hide(); loadDocs();});});}
loadCategories(); loadDocs();
})();
</script>
@endpush
@endif

{{-- Transfer Modal --}}
@if ($canTransfer)
<div class="modal fade" id="transferModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('hr.employees.transfer', $employee) }}">
            @csrf
            <div class="modal-content">
                <div class="modal-header"><h6 class="modal-title">Transfer Employee</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-2"><label class="form-label small">Effective Date *</label><input type="date" name="effective_date" class="form-control form-control-sm" value="{{ now()->toDateString() }}" required></div>
                    <div class="mb-2"><label class="form-label small">Branch</label><select name="branch_id" class="form-select form-select-sm"><option value="">— Keep —</option>@foreach($branches as $b)<option value="{{ $b->id }}" @selected($employee->branch_id==$b->id)>{{ $b->name }}</option>@endforeach</select></div>
                    <div class="mb-2"><label class="form-label small">Department</label><select name="department_id" class="form-select form-select-sm"><option value="">— Keep —</option>@foreach($departments as $d)<option value="{{ $d->id }}" @selected($employee->department_id==$d->id)>{{ $d->name }}</option>@endforeach</select></div>
                    <div class="mb-2"><label class="form-label small">Designation</label><select name="designation_id" class="form-select form-select-sm"><option value="">— Keep —</option>@foreach($designations as $de)<option value="{{ $de->id }}" @selected($employee->designation_id==$de->id)>{{ $de->name }}</option>@endforeach</select></div>
                    <div class="mb-2"><label class="form-label small">Reporting Manager</label><select name="reporting_manager_id" class="form-select form-select-sm"><option value="">— Keep —</option>@foreach($managers as $m)<option value="{{ $m->id }}" @selected($employee->reporting_manager_id==$m->id)>{{ $m->display_name }} ({{ $m->employee_code }})</option>@endforeach</select></div>
                    <div class="mb-2"><label class="form-label small">Salary Reference (optional)</label><input type="text" name="salary_reference" class="form-control form-control-sm" maxlength="100" placeholder="New salary reference, not payroll"></div>
                    <div class="mb-2"><label class="form-label small">Reason</label><input type="text" name="reason" class="form-control form-control-sm" maxlength="2000"></div>
                    <div class="mb-2"><label class="form-label small">Notes</label><textarea name="notes" class="form-control form-control-sm" rows="2"></textarea></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-sm btn-primary">Save Transfer</button></div>
            </div>
        </form>
    </div>
</div>
@endif

{{-- Promote Modal --}}
@if ($canPromote)
<div class="modal fade" id="promoteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('hr.employees.promote', $employee) }}">
            @csrf
            <div class="modal-content">
                <div class="modal-header"><h6 class="modal-title">Promotion / Demotion</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-2"><label class="form-label small">Effective Date *</label><input type="date" name="effective_date" class="form-control form-control-sm" value="{{ now()->toDateString() }}" required></div>
                    <div class="mb-2"><label class="form-label small">Type</label><select name="event_type" class="form-select form-select-sm"><option value="promotion">Promotion</option><option value="demotion">Demotion</option></select></div>
                    <div class="mb-2"><label class="form-label small">Department</label><select name="department_id" class="form-select form-select-sm"><option value="">— Keep —</option>@foreach($departments as $d)<option value="{{ $d->id }}" @selected($employee->department_id==$d->id)>{{ $d->name }}</option>@endforeach</select></div>
                    <div class="mb-2"><label class="form-label small">Designation</label><select name="designation_id" class="form-select form-select-sm"><option value="">— Keep —</option>@foreach($designations as $de)<option value="{{ $de->id }}" @selected($employee->designation_id==$de->id)>{{ $de->name }}</option>@endforeach</select></div>
                    <div class="mb-2"><label class="form-label small">Title (optional)</label><input type="text" name="title" class="form-control form-control-sm" maxlength="150"></div>
                    <div class="mb-2"><label class="form-label small">Salary Reference</label><input type="text" name="salary_reference" class="form-control form-control-sm" maxlength="100"></div>
                    <div class="mb-2"><label class="form-label small">Reason</label><input type="text" name="reason" class="form-control form-control-sm"></div>
                    <div class="mb-2"><label class="form-label small">Notes</label><textarea name="notes" class="form-control form-control-sm" rows="2"></textarea></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-sm btn-primary">Save</button></div>
            </div>
        </form>
    </div>
</div>
@endif

{{-- Resign Modal --}}
@if ($canResign)
<div class="modal fade" id="resignModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('hr.employees.resign', $employee) }}">
            @csrf
            <div class="modal-content">
                <div class="modal-header"><h6 class="modal-title">Resignation</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-2"><label class="form-label small">Resignation Date *</label><input type="date" name="resignation_date" class="form-control form-control-sm" value="{{ now()->toDateString() }}" required></div>
                    <div class="mb-2"><label class="form-label small">Last Working Date *</label><input type="date" name="last_working_date" class="form-control form-control-sm" value="{{ now()->addDays(30)->toDateString() }}" required></div>
                    <div class="mb-2"><label class="form-label small">Reason</label><input type="text" name="reason" class="form-control form-control-sm"></div>
                    <div class="mb-2"><label class="form-label small">Notes</label><textarea name="notes" class="form-control form-control-sm" rows="2"></textarea></div>
                    <div class="small text-muted">Creates pending resignation; approval required via timeline. No payroll settlement here.</div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-sm btn-warning">Submit Resignation</button></div>
            </div>
        </form>
    </div>
</div>
@endif

{{-- Terminate Modal --}}
@if ($canTerminate)
<div class="modal fade" id="terminateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('hr.employees.terminate', $employee) }}">
            @csrf
            <div class="modal-content">
                <div class="modal-header"><h6 class="modal-title">Termination</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-2"><label class="form-label small">Termination Date *</label><input type="date" name="termination_date" class="form-control form-control-sm" value="{{ now()->toDateString() }}" required></div>
                    <div class="mb-2"><label class="form-label small">Reason *</label><input type="text" name="reason" class="form-control form-control-sm" required></div>
                    <div class="mb-2"><label class="form-label small">Notes</label><textarea name="notes" class="form-control form-control-sm" rows="2"></textarea></div>
                    <div class="small text-muted">Authorized action only. Audited.</div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-sm btn-danger">Terminate</button></div>
            </div>
        </form>
    </div>
</div>
@endif

{{-- Reactivate Modal --}}
@if ($canReactivate)
<div class="modal fade" id="reactivateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('hr.employees.reactivate', $employee) }}">
            @csrf
            <div class="modal-content">
                <div class="modal-header"><h6 class="modal-title">Rejoin / Reactivate</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-2"><label class="form-label small">Effective Date *</label><input type="date" name="effective_date" class="form-control form-control-sm" value="{{ now()->toDateString() }}" required></div>
                    <div class="mb-2"><label class="form-label small">Reason</label><input type="text" name="reason" class="form-control form-control-sm"></div>
                    <div class="mb-2"><label class="form-label small">Notes</label><textarea name="notes" class="form-control form-control-sm" rows="2"></textarea></div>
                    <div class="small text-muted">Preserves previous periods; opens new active period.</div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-sm btn-success">Reactivate</button></div>
            </div>
        </form>
    </div>
</div>
@endif

@endsection
