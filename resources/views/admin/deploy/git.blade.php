@extends('layouts.admin')
@section('title', 'Git Management — AccumenAI')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-git me-2"></i> Git Management</h4>
        <p class="text-muted mb-0 small">Full Git control — pull, push, branches, stash, remotes, and history.</p>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <a href="{{ route('admin.deploy.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-rocket-takeoff me-1"></i> Deployment Center</a>
        @if($isGitAvailable)
            <span class="badge bg-success"><i class="bi bi-shield-check me-1"></i> Git Connected</span>
        @else
            <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i> Git Not Available</span>
        @endif
    </div>
</div>

@if(session('status'))
    <div class="alert alert-success"><i class="bi bi-check-circle-fill me-1"></i> {{ session('status') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i> {{ session('error') }}</div>
@endif

@if(! $isGitAvailable)
    <div class="admin-card">
        <div class="alert alert-warning d-flex align-items-center gap-2 mb-0">
            <i class="bi bi-exclamation-triangle-fill fs-5"></i>
            <div>
                <strong>Git not available</strong><br>
                <span class="small">Git is not installed or not in PATH on this server. Git management is disabled.</span>
            </div>
        </div>
    </div>
@else

{{-- Status Bar --}}
<div class="admin-card mb-4">
    <div class="row align-items-center g-3">
        <div class="col-md-3">
            <div class="small text-muted mb-1">Current Branch</div>
            <div class="fw-bold"><i class="bi bi-diagram-3 me-1"></i> {{ $currentBranch ?? 'unknown' }}</div>
        </div>
        <div class="col-md-4">
            <div class="small text-muted mb-1">Commit Hash</div>
            <code class="small" title="{{ $currentHash }}">{{ \Illuminate\Support\Str::limit($currentHash ?? 'unknown', 16) }}</code>
        </div>
        <div class="col-md-3">
            <div class="small text-muted mb-1">Working Tree</div>
            @if($hasChanges)
                <span class="badge bg-warning text-dark"><i class="bi bi-pencil-square me-1"></i> Uncommitted Changes</span>
            @else
                <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i> Clean</span>
            @endif
        </div>
        <div class="col-md-2 text-end">
            <button class="btn btn-sm btn-outline-primary" onclick="location.reload()"><i class="bi bi-arrow-clockwise me-1"></i> Refresh</button>
        </div>
    </div>
</div>

{{-- Main Tabs --}}
<div class="admin-card mb-4">
    <ul class="nav nav-tabs" id="gitTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-status" type="button" role="tab"><i class="bi bi-clipboard-check me-1"></i> Status</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-pull" type="button" role="tab"><i class="bi bi-cloud-download me-1"></i> Pull</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-push" type="button" role="tab"><i class="bi bi-cloud-upload me-1"></i> Push</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-branches" type="button" role="tab"><i class="bi bi-diagram-3 me-1"></i> Branches</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-stash" type="button" role="tab"><i class="bi bi-box-arrow-down me-1"></i> Stash</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-remote" type="button" role="tab"><i class="bi bi-cloud me-1"></i> Remote</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-log" type="button" role="tab"><i class="bi bi-clock-history me-1"></i> History</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-diff" type="button" role="tab"><i class="bi bi-file-diff me-1"></i> Diff</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-reset" type="button" role="tab"><i class="bi bi-arrow-counterclockwise me-1"></i> Reset</button>
        </li>
    </ul>

    <div class="tab-content border border-top-0 rounded-bottom p-0">

        {{-- STATUS TAB --}}
        <div class="tab-pane fade show active p-3" id="tab-status" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0"><i class="bi bi-clipboard-check me-1"></i> Working Tree Status</h6>
                <button class="btn btn-sm btn-outline-primary" onclick="fetchStatus()"><i class="bi bi-arrow-clockwise me-1"></i> Refresh</button>
            </div>
            @if($status && $status['clean'])
                <div class="alert alert-success small mb-0"><i class="bi bi-check-circle-fill me-1"></i> Nothing to commit — working tree is clean.</div>
            @else
                @if($status && trim($status['staged'] ?? '') !== '')
                    <div class="mb-3">
                        <div class="small fw-bold text-success mb-1"><i class="bi bi-plus-circle me-1"></i> Staged for Commit</div>
                        <pre class="small bg-light p-2 rounded mb-0" style="white-space:pre-wrap;">{{ $status['staged'] }}</pre>
                    </div>
                @endif
                @if($status && trim($status['modified'] ?? '') !== '')
                    <div class="mb-3">
                        <div class="small fw-bold text-warning mb-1"><i class="bi bi-pencil me-1"></i> Modified (not staged)</div>
                        <pre class="small bg-light p-2 rounded mb-0" style="white-space:pre-wrap;">{{ $status['modified'] }}</pre>
                    </div>
                @endif
                @if($status && trim($status['deleted'] ?? '') !== '')
                    <div class="mb-3">
                        <div class="small fw-bold text-danger mb-1"><i class="bi bi-trash me-1"></i> Deleted</div>
                        <pre class="small bg-light p-2 rounded mb-0" style="white-space:pre-wrap;">{{ $status['deleted'] }}</pre>
                    </div>
                @endif
                @if($status && trim($status['untracked'] ?? '') !== '')
                    <div class="mb-3">
                        <div class="small fw-bold text-secondary mb-1"><i class="bi bi-question-circle me-1"></i> Untracked Files</div>
                        <pre class="small bg-light p-2 rounded mb-0" style="white-space:pre-wrap;">{{ $status['untracked'] }}</pre>
                    </div>
                @endif
            @endif
        </div>

        {{-- PULL TAB --}}
        <div class="tab-pane fade p-3" id="tab-pull" role="tabpanel">
            <h6 class="mb-3"><i class="bi bi-cloud-download me-1"></i> Pull from Remote</h6>
            <div class="row g-3">
                <div class="col-md-6">
                    <form method="POST" action="{{ route('admin.git.pull') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Remote</label>
                            <select class="form-select" name="remote">
                                @foreach($remotes as $name => $urls)
                                    <option value="{{ $name }}">{{ $name }}</option>
                                @endforeach
                                @if(empty($remotes))
                                    <option value="origin">origin</option>
                                @endif
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Branch</label>
                            <input type="text" class="form-control" name="branch" value="{{ $currentBranch ?? 'main' }}" required>
                        </div>
                        <button class="btn btn-primary"><i class="bi bi-cloud-download me-1"></i> Pull</button>
                    </form>
                </div>
                <div class="col-md-6">
                    <div class="alert alert-info small mb-0">
                        <i class="bi bi-info-circle me-1"></i> <strong>Pull</strong> fetches and merges changes from the remote branch into your current local branch. Make sure your working tree is clean or stash changes first.
                    </div>
                </div>
            </div>
        </div>

        {{-- PUSH TAB --}}
        <div class="tab-pane fade p-3" id="tab-push" role="tabpanel">
            <h6 class="mb-3"><i class="bi bi-cloud-upload me-1"></i> Push to Remote</h6>
            <div class="row g-3">
                <div class="col-md-6">
                    <form method="POST" action="{{ route('admin.git.push') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Remote</label>
                            <select class="form-select" name="remote">
                                @foreach($remotes as $name => $urls)
                                    <option value="{{ $name }}">{{ $name }}</option>
                                @endforeach
                                @if(empty($remotes))
                                    <option value="origin">origin</option>
                                @endif
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Branch</label>
                            <input type="text" class="form-control" name="branch" value="{{ $currentBranch ?? 'main' }}" required>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" value="1" id="pushForce" name="force">
                            <label class="form-check-label small" for="pushForce">Force push (use with caution)</label>
                        </div>
                        <button class="btn btn-primary" onclick="return document.getElementById('pushForce').checked ? confirm('Force push will overwrite remote history. Continue?') : true"><i class="bi bi-cloud-upload me-1"></i> Push</button>
                    </form>
                </div>
                <div class="col-md-6">
                    <div class="alert alert-info small mb-0">
                        <i class="bi bi-info-circle me-1"></i> <strong>Push</strong> uploads your local commits to the remote repository. Force push is dangerous — it overwrites remote history and cannot be undone.
                    </div>
                </div>
            </div>
        </div>

        {{-- BRANCHES TAB --}}
        <div class="tab-pane fade p-3" id="tab-branches" role="tabpanel">
            <div class="row g-4">
                <div class="col-md-6">
                    <h6><i class="bi bi-diagram-3 me-1"></i> Local Branches</h6>
                    <div class="list-group mb-3">
                        @forelse($localBranches as $b)
                            <div class="list-group-item d-flex justify-content-between align-items-center {{ $b === $currentBranch ? 'list-group-item-primary' : '' }}">
                                <span>
                                    <i class="bi bi-git me-1"></i> <strong>{{ $b }}</strong>
                                    @if($b === $currentBranch) <span class="badge bg-primary ms-1">HEAD</span> @endif
                                </span>
                                @if($b !== $currentBranch)
                                    <div class="btn-group btn-group-sm">
                                        <form method="POST" action="{{ route('admin.git.branch.switch') }}" class="d-inline">@csrf<input type="hidden" name="branch" value="{{ $b }}"><button class="btn btn-outline-primary btn-sm" title="Switch to this branch"><i class="bi bi-arrow-left-right"></i></button></form>
                                        <form method="POST" action="{{ route('admin.git.branch.delete') }}" class="d-inline" onsubmit="return confirm('Delete branch {{ $b }}?')">@csrf<input type="hidden" name="branch" value="{{ $b }}"><button class="btn btn-outline-danger btn-sm" title="Delete this branch"><i class="bi bi-trash"></i></button></form>
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="list-group-item text-muted small">No local branches found.</div>
                        @endforelse
                    </div>

                    <div class="admin-card">
                        <h6 class="mb-3"><i class="bi bi-plus-circle me-1"></i> Create New Branch</h6>
                        <form method="POST" action="{{ route('admin.git.branch.create') }}">
                            @csrf
                            <div class="input-group">
                                <input type="text" class="form-control" name="name" placeholder="feature/new-thing" pattern="[a-zA-Z0-9_\-/.]+" maxlength="100" required>
                                <button class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Create</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="col-md-6">
                    <h6><i class="bi bi-cloud me-1"></i> Remote Branches</h6>
                    <div class="list-group mb-3">
                        @forelse($branches as $b)
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-diagram-3 me-1"></i> {{ $b }}</span>
                                @if($b !== $currentBranch)
                                    <form method="POST" action="{{ route('admin.git.branch.switch') }}" class="d-inline">@csrf<input type="hidden" name="branch" value="{{ $b }}"><button class="btn btn-outline-primary btn-sm"><i class="bi bi-arrow-down-circle me-1"></i> Checkout</button></form>
                                @endif
                            </div>
                        @empty
                            <div class="list-group-item text-muted small">No remote branches found.</div>
                        @endforelse
                    </div>

                    <div class="admin-card">
                        <h6 class="mb-3"><i class="bi bi-merge me-1"></i> Merge Branch into <code>{{ $currentBranch }}</code></h6>
                        <form method="POST" action="{{ route('admin.git.branch.merge') }}">
                            @csrf
                            <div class="input-group">
                                <select class="form-select" name="branch" required>
                                    <option value="" disabled selected>Select branch to merge...</option>
                                    @foreach($localBranches as $b)
                                        @if($b !== $currentBranch)
                                            <option value="{{ $b }}">{{ $b }}</option>
                                        @endif
                                    @endforeach
                                </select>
                                <button class="btn btn-warning" onclick="return confirm('Merge selected branch into {{ $currentBranch }}?')"><i class="bi bi-merge me-1"></i> Merge</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        {{-- STASH TAB --}}
        <div class="tab-pane fade p-3" id="tab-stash" role="tabpanel">
            <div class="row g-4">
                <div class="col-md-6">
                    <h6><i class="bi bi-box-arrow-down me-1"></i> Stash List</h6>
                    @if(empty($stashList))
                        <div class="alert alert-secondary small mb-0"><i class="bi bi-inbox me-1"></i> No stashed changes.</div>
                    @else
                        <div class="list-group mb-3">
                            @foreach($stashList as $i => $item)
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <span class="small font-monospace">{{ $item }}</span>
                                    <form method="POST" action="{{ route('admin.git.stash.drop') }}" class="d-inline" onsubmit="return confirm('Drop this stash?')">
                                        @csrf
                                        <input type="hidden" name="stash_id" value="stash@{{$i}}">
                                        <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                                    </form>
                                </div>
                            @endforeach
                        </div>
                        <form method="POST" action="{{ route('admin.git.stash.pop') }}">
                            @csrf
                            <button class="btn btn-success"><i class="bi bi-box-arrow-up me-1"></i> Pop Latest Stash</button>
                        </form>
                    @endif
                </div>
                <div class="col-md-6">
                    <h6><i class="bi bi-plus-circle me-1"></i> Create Stash</h6>
                    <div class="admin-card">
                        <form method="POST" action="{{ route('admin.git.stash') }}">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Message (optional)</label>
                                <input type="text" class="form-control" name="message" placeholder="WIP: saving work in progress...">
                            </div>
                            <button class="btn btn-primary"><i class="bi bi-box-arrow-down me-1"></i> Stash Changes</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        {{-- REMOTE TAB --}}
        <div class="tab-pane fade p-3" id="tab-remote" role="tabpanel">
            <div class="row g-4">
                <div class="col-md-6">
                    <h6><i class="bi bi-cloud me-1"></i> Configured Remotes</h6>
                    @if(empty($remotes))
                        <div class="alert alert-secondary small mb-0"><i class="bi bi-inbox me-1"></i> No remotes configured.</div>
                    @else
                        @foreach($remotes as $name => $urls)
                            <div class="admin-card mb-3">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong><i class="bi bi-link-45deg me-1"></i> {{ $name }}</strong>
                                        <div class="small text-muted mt-1">
                                            @if(isset($urls['fetch']))<div>Fetch: <code>{{ $urls['fetch'] }}</code></div>@endif
                                            @if(isset($urls['push']))<div>Push: <code>{{ $urls['push'] }}</code></div>@endif
                                        </div>
                                    </div>
                                    <form method="POST" action="{{ route('admin.git.remote.remove') }}" onsubmit="return confirm('Remove remote {{ $name }}?')">
                                        @csrf
                                        <input type="hidden" name="name" value="{{ $name }}">
                                        <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>
                <div class="col-md-6">
                    <h6><i class="bi bi-plus-circle me-1"></i> Add Remote</h6>
                    <div class="admin-card">
                        <form method="POST" action="{{ route('admin.git.remote.add') }}">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Name</label>
                                <input type="text" class="form-control" name="name" placeholder="origin" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">URL</label>
                                <input type="url" class="form-control" name="url" placeholder="https://github.com/user/repo.git" required>
                            </div>
                            <button class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Add Remote</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        {{-- HISTORY TAB --}}
        <div class="tab-pane fade p-3" id="tab-log" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0"><i class="bi bi-clock-history me-1"></i> Commit History</h6>
                <button class="btn btn-sm btn-outline-primary" onclick="fetchLog()"><i class="bi bi-arrow-clockwise me-1"></i> Refresh</button>
            </div>
            <div id="logContainer">
                @if(empty($gitLog))
                    <div class="alert alert-secondary small mb-0"><i class="bi bi-inbox me-1"></i> No commit history found.</div>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th style="width:90px">Hash</th>
                                    <th style="width:140px">Author</th>
                                    <th style="width:170px">Date</th>
                                    <th>Message</th>
                                    <th style="width:50px"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($gitLog as $entry)
                                    <tr>
                                        <td class="font-monospace small"><code>{{ $entry['short'] }}</code></td>
                                        <td class="small">{{ $entry['author'] }}</td>
                                        <td class="small text-muted text-nowrap">{{ $entry['date'] }}</td>
                                        <td class="small text-truncate" style="max-width:300px;" title="{{ $entry['message'] }}">{{ $entry['message'] }}</td>
                                        <td><button class="btn btn-sm btn-outline-secondary" onclick="showDiff('{{ $entry['hash'] }}')" title="View diff"><i class="bi bi-file-diff"></i></button></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        {{-- DIFF TAB --}}
        <div class="tab-pane fade p-3" id="tab-diff" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0"><i class="bi bi-file-diff me-1"></i> Diff Viewer</h6>
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-outline-primary" onclick="fetchDiff(false)"><i class="bi bi-file-diff me-1"></i> Working Tree</button>
                    <button class="btn btn-sm btn-outline-success" onclick="fetchDiff(true)"><i class="bi bi-plus-circle me-1"></i> Staged</button>
                </div>
            </div>
            <pre id="diffOutput" class="small p-3 rounded mb-0" style="background:#1e1e2f; color:#d4d4d4; min-height:200px; white-space:pre-wrap; word-break:break-word; max-height:60vh; overflow:auto;">Click "Working Tree" or "Staged" above to view diff. You can also click a commit hash in the History tab.</pre>
        </div>

        {{-- RESET TAB --}}
        <div class="tab-pane fade p-3" id="tab-reset" role="tabpanel">
            <h6 class="mb-3"><i class="bi bi-arrow-counterclockwise me-1"></i> Reset / Restore</h6>
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="admin-card">
                        <h6 class="text-warning"><i class="bi bi-exclamation-triangle me-1"></i> Soft Reset</h6>
                        <p class="small text-muted mb-3">Undo the last commit but keep all changes staged in the index. Safe — no data loss.</p>
                        <form method="POST" action="{{ route('admin.git.reset') }}">
                            @csrf
                            <input type="hidden" name="mode" value="soft">
                            <input type="hidden" name="commit" value="HEAD~1">
                            <button class="btn btn-warning" onclick="return confirm('Soft reset to HEAD~1? The last commit will be undone, changes stay staged.')"><i class="bi bi-arrow-counterclockwise me-1"></i> Soft Reset (undo last commit)</button>
                        </form>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="admin-card">
                        <h6 class="text-danger"><i class="bi bi-exclamation-triangle me-1"></i> Hard Reset</h6>
                        <p class="small text-muted mb-3">Discard ALL uncommitted changes and reset to HEAD. <strong>This cannot be undone.</strong></p>
                        <form method="POST" action="{{ route('admin.git.reset') }}">
                            @csrf
                            <input type="hidden" name="mode" value="hard">
                            <input type="hidden" name="commit" value="HEAD">
                            <button class="btn btn-danger" onclick="return confirm('HARD RESET: All uncommitted changes will be PERMANENTLY LOST. Continue?')"><i class="bi bi-exclamation-triangle me-1"></i> Hard Reset (discard everything)</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

@endif
@endsection

@section('scripts')
<script>
function fetchStatus() {
    fetch('{{ route("admin.git.status") }}', { headers: { 'Accept': 'application/json' } })
        .then(r => r.json())
        .then(() => location.reload());
}

function fetchDiff(cached) {
    fetch('{{ route("admin.git.diff") }}?cached=' + (cached ? '1' : '0'), { headers: { 'Accept': 'application/json' } })
        .then(r => r.json())
        .then(data => {
            document.getElementById('diffOutput').textContent = data.diff || '(no diff)';
        });
}

function showDiff(commit) {
    fetch('{{ route("admin.git.diff") }}?commit=' + commit, { headers: { 'Accept': 'application/json' } })
        .then(r => r.json())
        .then(data => {
            document.getElementById('diffOutput').textContent = data.diff || '(no diff)';
            new bootstrap.Tab(document.querySelector('[data-bs-target="#tab-diff"]')).show();
        });
}

function fetchLog() {
    fetch('{{ route("admin.git.log") }}', { headers: { 'Accept': 'application/json' } })
        .then(r => r.json())
        .then(data => {
            if (!Array.isArray(data)) return;
            let html = '<div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th style="width:90px">Hash</th><th style="width:140px">Author</th><th style="width:170px">Date</th><th>Message</th><th style="width:50px"></th></tr></thead><tbody>';
            data.forEach(e => {
                html += '<tr><td class="font-monospace small"><code>' + e.short + '</code></td><td class="small">' + e.author + '</td><td class="small text-muted text-nowrap">' + e.date + '</td><td class="small text-truncate" style="max-width:300px;" title="' + e.message + '">' + e.message + '</td><td><button class="btn btn-sm btn-outline-secondary" onclick="showDiff(\'' + e.hash + '\')" title="View diff"><i class="bi bi-file-diff"></i></button></td></tr>';
            });
            html += '</tbody></table></div>';
            document.getElementById('logContainer').innerHTML = html;
        });
}
</script>
@endsection
