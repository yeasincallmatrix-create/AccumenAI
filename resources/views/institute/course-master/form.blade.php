@extends('layouts.institute')

@section('title', ($course ? 'Edit Course — AccumenAI' : 'New Course — AccumenAI'))

@php
    $durationTypes = ['hours', 'days', 'weeks', 'months', 'years'];
    $modes = ['online', 'offline', 'hybrid'];
    $statusNames = ['active' => 'Active', 'inactive' => 'Inactive', 'draft' => 'Draft'];
@endphp

@section('content')
<div class="page-header d-flex flex-wrap align-items-start justify-content-between gap-3">
    <div class="page-header-text">
        <a href="{{ route('courses.manage.index') }}" class="btn btn-outline-secondary btn-sm mb-2">
            <i class="bi bi-arrow-left me-1"></i>Course Master
        </a>
        <h4 class="page-header-title">{{ $course ? 'Edit Course' : 'New Course' }}</h4>
        <p class="page-header-desc mb-0">Course Master creates an institute-owned course (institute_id is derived from your account — never from the form).</p>
    </div>
    <div class="d-flex gap-2 align-items-start pt-1" style="flex-shrink:0;">
        <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#categoryManageModal">
            <i class="bi bi-tags me-1"></i>Add/remove Category
        </button>
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#subCategoryManageModal">
            <i class="bi bi-diagram-3 me-1"></i>Add Sub Category
        </button>
    </div>
</div>

<div class="admin-card">
    <form id="courseMasterForm" method="POST" action="{{ $course ? route('courses.manage.update', $course) : route('courses.manage.store') }}" enctype="multipart/form-data">
        @csrf
        @if ($course) @method('PUT') @endif

        <h6 class="text-muted text-uppercase small fw-semibold mb-2">Identity &amp; classification</h6>
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label">Course name <span class="text-danger">*</span></label>
                <input type="text" class="form-control form-control-sm @error('name') is-invalid @enderror" name="name" value="{{ old('name', $course?->name) }}" required maxlength="200">
                @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <label class="form-label">Short name</label>
                <input type="text" class="form-control form-control-sm @error('short_name') is-invalid @enderror" name="short_name" value="{{ old('short_name', $course?->short_name) }}" maxlength="100">
                @error('short_name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <label class="form-label">Status <span class="text-danger">*</span></label>
                <select class="form-select form-select-sm @error('status') is-invalid @enderror" name="status" required>
                    @foreach ($statusNames as $slug => $label)
                        <option value="{{ $slug }}" @selected(old('status', $course?->status ?? 'draft') === $slug)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('status')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">Category <span class="text-danger">*</span></label>
                <select class="form-select form-select-sm @error('category_id') is-invalid @enderror" name="category_id" id="categorySelect" required>
                    <option value="">— Select category —</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) old('category_id', $course?->category_id) === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
                @error('category_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">Sub category</label>
                <select class="form-select form-select-sm @error('sub_category_id') is-invalid @enderror" name="sub_category_id" id="subCategorySelect">
                    <option value="">— Not set —</option>
                    @foreach ($subCategories as $sub)
                        <option value="{{ $sub->id }}" @selected((string) old('sub_category_id', $course?->sub_category_id) === (string) $sub->id)>{{ $sub->name }}</option>
                    @endforeach
                </select>
                @error('sub_category_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">Course Level</label>
                <select name="level" id="level" class="form-select form-select-sm @error('level') is-invalid @enderror">
                    <option value="basic" {{ old('level', $course->level ?? 'basic') == 'basic' ? 'selected' : '' }}>Basic (বেসিক)</option>
                    <option value="intermediate" {{ old('level', $course->level ?? 'basic') == 'intermediate' ? 'selected' : '' }}>Intermediate (মাঝারি)</option>
                    <option value="advanced" {{ old('level', $course->level ?? 'basic') == 'advanced' ? 'selected' : '' }}>Advanced (এডভান্সড)</option>
                </select>
                @error('level')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">Language</label>
                <input type="text" class="form-control form-control-sm @error('language') is-invalid @enderror" name="language" value="{{ old('language', $course?->language) }}" maxlength="30">
                @error('language')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">Mode</label>
                <select class="form-select form-select-sm @error('mode') is-invalid @enderror" name="mode">
                    <option value="">— Not set —</option>
                    @foreach ($modes as $mode)
                        <option value="{{ $mode }}" @selected(old('mode', $course?->mode) === $mode)>{{ ucfirst($mode) }}</option>
                    @endforeach
                </select>
                @error('mode')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">Display order</label>
                <input type="number" class="form-control form-control-sm @error('display_order') is-invalid @enderror" name="display_order" value="{{ old('display_order', $course?->display_order ?? 0) }}" min="0">
                @error('display_order')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
        </div>

        <h6 class="text-muted text-uppercase small fw-semibold mb-2">Description &amp; SEO</h6>
        <div class="row g-3 mb-3">
            <div class="col-12">
                <label class="form-label">Description</label>
                <textarea class="form-control form-control-sm @error('description') is-invalid @enderror" name="description" rows="4">{{ old('description', $course?->description) }}</textarea>
                @error('description')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label">Short description</label>
                <textarea class="form-control form-control-sm @error('short_description') is-invalid @enderror" name="short_description" rows="2" maxlength="500">{{ old('short_description', $course?->short_description) }}</textarea>
                @error('short_description')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label">Intro video URL</label>
                <input type="text" class="form-control form-control-sm @error('intro_video') is-invalid @enderror" name="intro_video" value="{{ old('intro_video', $course?->intro_video) }}" maxlength="500">
                @error('intro_video')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">Meta title</label>
                <input type="text" class="form-control form-control-sm" name="meta_title" value="{{ old('meta_title', $course?->meta_title) }}" maxlength="200">
            </div>
            <div class="col-md-4">
                <label class="form-label">Meta description</label>
                <input type="text" class="form-control form-control-sm" name="meta_description" value="{{ old('meta_description', $course?->meta_description) }}" maxlength="500">
            </div>
            <div class="col-md-4">
                <label class="form-label">Meta keywords</label>
                <input type="text" class="form-control form-control-sm" name="meta_keywords" value="{{ old('meta_keywords', $course?->meta_keywords) }}" maxlength="500">
            </div>
            <div class="col-md-6">
                <label class="form-label">Banner</label>
                <input type="file" class="form-control form-control-sm @error('banner') is-invalid @enderror" name="banner" accept="image/jpeg,image/webp,image/png">
                <div class="form-text">JPG or WebP (PNG auto-converted), max 5 MB upload → auto-compressed to ≤200 KB (WebP/JPG).</div>
                @error('banner')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                @if ($course?->banner)
                    <div class="mt-2 d-flex align-items-center gap-3">
                        <img src="{{ asset('storage/' . $course->banner) }}" alt="banner" style="max-height:50px">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="remove_banner" value="1" id="remove_banner">
                            <label class="form-check-label" for="remove_banner">Remove current banner</label>
                        </div>
                    </div>
                @endif
            </div>
            <div class="col-md-6 d-flex align-items-end">
                <div class="form-check form-switch">
                    <input class="form-check-input @error('is_featured') is-invalid @enderror" type="checkbox" name="is_featured" value="1" id="is_featured" @checked(old('is_featured', $course?->is_featured))>
                    <label class="form-check-label" for="is_featured">Featured course</label>
                    @error('is_featured')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <h6 class="text-muted text-uppercase small fw-semibold mb-2">Duration &amp; structure</h6>
        <div class="row g-3 mb-3">
            <div class="col-md-3">
                <label class="form-label">Duration value</label>
                <input type="number" class="form-control form-control-sm @error('duration_value') is-invalid @enderror" name="duration_value" value="{{ old('duration_value', $course?->duration_value) }}" min="1" max="3650">
                @error('duration_value')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <label class="form-label">Duration type <span class="text-danger">*</span></label>
                <select class="form-select form-select-sm @error('duration_type') is-invalid @enderror" name="duration_type" required>
                    <option value="">— Select —</option>
                    @foreach ($durationTypes as $type)
                        <option value="{{ $type }}" @selected(old('duration_type', $course?->duration_type) === $type)>{{ ucfirst($type) }}</option>
                    @endforeach
                </select>
                @error('duration_type')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-2">
                <label class="form-label">Weekly classes</label>
                <input type="number" class="form-control form-control-sm @error('weekly_classes') is-invalid @enderror" name="weekly_classes" value="{{ old('weekly_classes', $course?->weekly_classes) }}" min="1" max="60">
                @error('weekly_classes')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-2">
                <label class="form-label">Total classes</label>
                <input type="number" class="form-control form-control-sm @error('total_classes') is-invalid @enderror" name="total_classes" value="{{ old('total_classes', $course?->total_classes) }}" min="1" max="10000">
                @error('total_classes')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-2">
                <label class="form-label">Total hours</label>
                <input type="number" class="form-control form-control-sm @error('total_hours') is-invalid @enderror" name="total_hours" value="{{ old('total_hours', $course?->total_hours) }}" min="0" step="0.5">
                @error('total_hours')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">Default batch capacity</label>
                <input type="number" class="form-control form-control-sm @error('batch_capacity_default') is-invalid @enderror" name="batch_capacity_default" value="{{ old('batch_capacity_default', $course?->batch_capacity_default) }}" min="1" max="10000">
                @error('batch_capacity_default')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
        </div>

        <h6 class="text-muted text-uppercase small fw-semibold mb-2">Pricing</h6>
        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <label class="form-label">Course fee</label>
                <input type="number" class="form-control form-control-sm @error('fee') is-invalid @enderror" name="fee" value="{{ old('fee', $course?->fee) }}" min="0" step="0.01">
                @error('fee')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">Discount</label>
                <input type="number" class="form-control form-control-sm @error('discount') is-invalid @enderror" name="discount" value="{{ old('discount', $course?->discount) }}" min="0" step="0.01">
                @error('discount')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">Admission fee</label>
                <input type="number" class="form-control form-control-sm @error('admission_fee') is-invalid @enderror" name="admission_fee" value="{{ old('admission_fee', $course?->admission_fee) }}" min="0" step="0.01">
                @error('admission_fee')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">Exam fee</label>
                <input type="number" class="form-control form-control-sm @error('exam_fee') is-invalid @enderror" name="exam_fee" value="{{ old('exam_fee', $course?->exam_fee) }}" min="0" step="0.01">
                @error('exam_fee')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">Certificate fee</label>
                <input type="number" class="form-control form-control-sm @error('certificate_fee') is-invalid @enderror" name="certificate_fee" value="{{ old('certificate_fee', $course?->certificate_fee) }}" min="0" step="0.01">
                @error('certificate_fee')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
        </div>

        <h6 class="text-muted text-uppercase small fw-semibold mb-2">Requirements, outcomes &amp; prerequisites</h6>
        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <label class="form-label">Requirements <span class="text-muted">(one per line)</span></label>
                <textarea class="form-control form-control-sm @error('requirements') is-invalid @enderror" name="requirements" rows="5">{{ old('requirements', is_array($course?->requirements) ? implode("\n", $course->requirements) : '') }}</textarea>
                @error('requirements')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">Outcomes <span class="text-muted">(one per line)</span></label>
                <textarea class="form-control form-control-sm @error('outcomes') is-invalid @enderror" name="outcomes" rows="5">{{ old('outcomes', is_array($course?->outcomes) ? implode("\n", $course->outcomes) : '') }}</textarea>
                @error('outcomes')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">Prerequisites <span class="text-muted">(one per line)</span></label>
                <textarea class="form-control form-control-sm @error('prerequisites') is-invalid @enderror" name="prerequisites" rows="5">{{ old('prerequisites', is_array($course?->prerequisites) ? implode("\n", $course->prerequisites) : '') }}</textarea>
                @error('prerequisites')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center pt-2 border-top">
            <div>
                @if ($course)
                    <button type="button" class="btn btn-outline-danger btn-sm" data-confirm-delete
                            data-href="{{ route('courses.manage.destroy', $course) }}">
                        <i class="bi bi-trash me-1"></i>Delete course
                    </button>
                @endif
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('courses.manage.index') }}" class="btn btn-outline-secondary btn-sm">Cancel</a>
                <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-lg me-1"></i>{{ $course ? 'Save changes' : 'Create course' }}</button>
            </div>
        </div>
    </form>
</div>

@if ($course && optional(auth('institute_user')->user())->hasPermission('documents.view'))
    <div class="admin-card mt-4">
        @include('documents._panel', ['entityType' => 'course', 'entityId' => $course->id])
    </div>
@endif

<form id="deleteForm" method="POST" class="d-none">
    @csrf
    @method('DELETE')
</form>

{{-- ── Large modal: Add/remove Category ─────────────────────────────────── --}}
<div class="modal fade" id="categoryManageModal" tabindex="-1" aria-labelledby="categoryManageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="categoryManageModalLabel"><i class="bi bi-tags me-2"></i>Add / remove Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info small py-2 mb-3">
                    <i class="bi bi-info-circle me-1"></i>
                    Categories are institute-scoped (professional). Adding a category creates it for your institute only. Deleting a category that still has courses / batches / subjects will ask you to pick a replacement so linked records are moved instead of orphaned.
                </div>
                <div class="card border mb-3">
                    <div class="card-body py-3">
                        <label class="form-label small fw-semibold mb-1">Add new category</label>
                        <div class="input-group">
                            <input type="text" id="catAddInput" class="form-control form-control-sm" placeholder="Enter category name (e.g. Computer Basics)" maxlength="100">
                            <button type="button" class="btn btn-primary btn-sm" id="catAddBtn"><i class="bi bi-plus-lg me-1"></i>Add</button>
                        </div>
                        <div id="catAddFeedback" class="small mt-1 text-danger" style="min-height:18px;"></div>
                    </div>
                </div>
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <h6 class="mb-0 small text-uppercase text-muted fw-semibold"><i class="bi bi-list-ul me-1"></i> Existing categories</h6>
                    <span class="badge text-bg-light border small" id="catCountBadge">0</span>
                </div>
                <div id="catListWrap" class="border rounded" style="max-height:420px; overflow-y:auto;">
                    <div id="catListLoader" class="text-center text-muted small py-4"><span class="spinner-border spinner-border-sm me-2"></span>Loading…</div>
                    <div id="catListEmpty" class="text-center text-muted small py-4 d-none">No categories yet.</div>
                    <table class="table table-sm table-hover align-middle mb-0 d-none" id="catTable">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th style="width:40%;">Name</th>
                                <th>Usage</th>
                                <th class="text-end" style="width:220px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="catTableBody"></tbody>
                    </table>
                </div>
                <div id="catInlineEditWrap" class="d-none mt-3 p-3 border rounded bg-light">
                    <label class="form-label small fw-semibold">Edit category</label>
                    <div class="input-group input-group-sm">
                        <input type="text" id="catEditInput" class="form-control" maxlength="100">
                        <button type="button" class="btn btn-primary" id="catEditSaveBtn">Save</button>
                        <button type="button" class="btn btn-outline-secondary" id="catEditCancelBtn">Cancel</button>
                    </div>
                    <div id="catEditFeedback" class="small text-danger mt-1"></div>
                </div>
                <div id="catDeleteWrap" class="d-none mt-3 p-3 border rounded bg-warning bg-opacity-10 border-warning">
                    <div class="d-flex align-items-start gap-2">
                        <i class="bi bi-exclamation-triangle-fill text-warning fs-5"></i>
                        <div class="flex-grow-1">
                            <div class="fw-semibold small">Delete <span id="catDeleteName" class="text-danger"></span>?</div>
                            <div id="catDeleteCounts" class="small text-muted mb-2"></div>
                            <div id="catDeleteReassignGroup" class="d-none">
                                <label class="form-label small mb-1">Move all linked courses / subjects / batches / sub-categories to <span class="text-danger">*</span></label>
                                <select id="catDeleteReassignSelect" class="form-select form-select-sm">
                                    <option value="">— Select replacement —</option>
                                </select>
                                <div class="form-text small">Required: this category owns <span id="catDeleteDepCount">0</span> dependent record(s).</div>
                            </div>
                            <div class="d-flex gap-2 mt-2">
                                <button type="button" class="btn btn-danger btn-sm" id="catDeleteConfirmBtn"><i class="bi bi-trash me-1"></i>Confirm delete</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="catDeleteCancelBtn">Cancel</button>
                            </div>
                            <div id="catDeleteFeedback" class="small text-danger mt-2"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

{{-- ── Large modal: Add Sub Category ────────────────────────────────────── --}}
<div class="modal fade" id="subCategoryManageModal" tabindex="-1" aria-labelledby="subCategoryManageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="subCategoryManageModalLabel"><i class="bi bi-diagram-3 me-2"></i>Add Sub Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info small py-2 mb-3">
                    <i class="bi bi-info-circle me-1"></i>
                    Sub categories belong to a parent category. Choose the parent category first, then name the sub category. Deleting a sub category with linked courses / batches will ask for a replacement.
                </div>
                <div class="card border mb-3">
                    <div class="card-body py-3">
                        <label class="form-label small fw-semibold mb-1">Add new sub category</label>
                        <div class="row g-2">
                            <div class="col-md-5">
                                <select id="subAddCategorySelect" class="form-select form-select-sm">
                                    <option value="">— Select category —</option>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <input type="text" id="subAddInput" class="form-control form-control-sm" placeholder="Sub category name" maxlength="100">
                            </div>
                            <div class="col-md-2 d-grid">
                                <button type="button" class="btn btn-primary btn-sm" id="subAddBtn"><i class="bi bi-plus-lg me-1"></i>Add</button>
                            </div>
                        </div>
                        <div id="subAddFeedback" class="small mt-1 text-danger" style="min-height:18px;"></div>
                    </div>
                </div>
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <h6 class="mb-0 small text-uppercase text-muted fw-semibold"><i class="bi bi-list-ul me-1"></i> Existing sub categories</h6>
                    <span class="badge text-bg-light border small" id="subCountBadge">0</span>
                </div>
                <div id="subListWrap" class="border rounded" style="max-height:420px; overflow-y:auto;">
                    <div id="subListLoader" class="text-center text-muted small py-4"><span class="spinner-border spinner-border-sm me-2"></span>Loading…</div>
                    <div id="subListEmpty" class="text-center text-muted small py-4 d-none">No sub categories yet.</div>
                    <table class="table table-sm table-hover align-middle mb-0 d-none" id="subTable">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th style="width:35%;">Name</th>
                                <th style="width:30%;">Parent category</th>
                                <th>Usage</th>
                                <th class="text-end" style="width:220px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="subTableBody"></tbody>
                    </table>
                </div>
                <div id="subInlineEditWrap" class="d-none mt-3 p-3 border rounded bg-light">
                    <label class="form-label small fw-semibold">Edit sub category</label>
                    <div class="row g-2">
                        <div class="col-md-5">
                            <select id="subEditCategorySelect" class="form-select form-select-sm"></select>
                        </div>
                        <div class="col-md-7">
                            <div class="input-group input-group-sm">
                                <input type="text" id="subEditInput" class="form-control" maxlength="100">
                                <button type="button" class="btn btn-primary" id="subEditSaveBtn">Save</button>
                                <button type="button" class="btn btn-outline-secondary" id="subEditCancelBtn">Cancel</button>
                            </div>
                        </div>
                    </div>
                    <div id="subEditFeedback" class="small text-danger mt-1"></div>
                </div>
                <div id="subDeleteWrap" class="d-none mt-3 p-3 border rounded bg-warning bg-opacity-10 border-warning">
                    <div class="d-flex align-items-start gap-2">
                        <i class="bi bi-exclamation-triangle-fill text-warning fs-5"></i>
                        <div class="flex-grow-1">
                            <div class="fw-semibold small">Delete <span id="subDeleteName" class="text-danger"></span>?</div>
                            <div id="subDeleteCounts" class="small text-muted mb-2"></div>
                            <div id="subDeleteReassignGroup" class="d-none">
                                <label class="form-label small mb-1">Move linked courses / batches to <span class="text-danger">*</span></label>
                                <select id="subDeleteReassignSelect" class="form-select form-select-sm">
                                    <option value="">— Select replacement sub category —</option>
                                </select>
                                <div class="form-text small">Required: this sub category owns <span id="subDeleteDepCount">0</span> dependent record(s).</div>
                                <div id="subDeleteReassignHint" class="small text-muted mt-1 d-none"></div>
                            </div>
                            <div class="d-flex gap-2 mt-2">
                                <button type="button" class="btn btn-danger btn-sm" id="subDeleteConfirmBtn"><i class="bi bi-trash me-1"></i>Confirm delete</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="subDeleteCancelBtn">Cancel</button>
                            </div>
                            <div id="subDeleteFeedback" class="small text-danger mt-2"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    // ── keep existing course delete ─────────────────────────────
    var deleteBtn = document.querySelector('[data-confirm-delete]');
    var deleteForm = document.getElementById('deleteForm');
    if (deleteBtn && deleteForm) {
        deleteBtn.addEventListener('click', function () {
            if (confirm('Delete this course? This cannot be undone.')) {
                deleteForm.action = deleteBtn.getAttribute('data-href');
                deleteForm.submit();
            }
        });
    }

    // ── preserve all text inputs across refresh (clear on submit/cancel) ──
    (function(){
        var form = document.getElementById('courseMasterForm');
        if (!form) return;
        var KEY = 'course_master_form_' + location.pathname;
        var cancelBtn = form.querySelector('a[href*="courses/manage"]'); // Cancel link
        function collect(){
            var data = {};
            form.querySelectorAll('input, select, textarea').forEach(function(el){
                if (!el.name || el.type === 'file' || el.type === 'hidden' && el.name === '_token') return;
                if (el.type === 'checkbox') data[el.name] = el.checked ? el.value : '';
                else if (el.type === 'radio') { if (el.checked) data[el.name] = el.value; }
                else data[el.name] = el.value;
            });
            try{ sessionStorage.setItem(KEY, JSON.stringify(data)); }catch(e){}
        }
        function restore(){
            var raw; try{ raw = sessionStorage.getItem(KEY); }catch(e){ return; }
            if (!raw) return;
            var data; try{ data = JSON.parse(raw); }catch(e){ return; }
            // Only restore if form is in create mode (no course) or user has unsaved data; for edit, don't overwrite server values if no storage? We still restore to preserve refresh.
            Object.keys(data).forEach(function(name){
                var el = form.querySelector('[name="'+name+'"]');
                if (!el) return;
                // Don't overwrite file inputs
                if (el.type === 'file') return;
                // For checkboxes
                if (el.type === 'checkbox') {
                    el.checked = !!data[name] && data[name] !== '';
                    // trigger change for any dependent UI
                } else {
                    // Only restore if data differs from server-rendered value? Always restore text fields to keep refresh persistence.
                    // But avoid wiping server old() after validation error where storage may be stale; prefer storage only on hard reload without old.
                    // We check if el is select and value exists
                    if (el.tagName === 'SELECT') {
                        // ensure option exists, if not keep storage value for later when categories reload
                        el.value = data[name];
                    } else {
                        el.value = data[name];
                    }
                }
            });
            // After restoring category, trigger sub-category reload
            var catSel = document.getElementById('categorySelect');
            if (catSel && catSel.value) {
                catSel.dispatchEvent(new Event('change', {bubbles:true}));
            }
        }
        // Restore on load (hard reload preservation)
        restore();
        // Save on every input/change
        form.addEventListener('input', collect);
        form.addEventListener('change', collect);
        // Clear on submit (success) and cancel
        form.addEventListener('submit', function(){ try{ sessionStorage.removeItem(KEY);}catch(e){} });
        if (cancelBtn) cancelBtn.addEventListener('click', function(){ try{ sessionStorage.removeItem(KEY);}catch(e){} });
        // Also clear when user explicitly navigates via Course Master breadcrumb
        document.querySelectorAll('a[href*="courses.manage.index"]').forEach(function(a){
            a.addEventListener('click', function(){ try{ sessionStorage.removeItem(KEY);}catch(e){} });
        });
    })();

    var CAT_INDEX = @json(route('courses.manage.categories.index'));
    var CAT_STORE = @json(route('courses.manage.categories.store'));
    var SUB_INDEX = @json(route('courses.manage.sub-categories.index'));
    var SUB_STORE = @json(route('courses.manage.sub-categories.store'));
    var catCategoriesCache = [];
    var subCategoriesCache = [];
    var catEditId = null, catDeleteId = null, catDeleteData = null;
    var subEditId = null, subDeleteId = null, subDeleteData = null;

    function catUrl(id, isUpdate) {
        // routes are /courses/manage/categories and /courses/manage/categories/{id}
        return CAT_STORE + '/' + id;
    }
    function subUrl(id) {
        return SUB_STORE + '/' + id;
    }

    // ── helpers ─────────────────────────────────────────────────
    function toast(msg, type) {
        if (window.Monetix && Monetix.toast) { Monetix.toast(msg, type || 'success'); }
    }
    function setFeedback(el, msg, isSuccess) {
        if (!el) return;
        el.textContent = msg || '';
        el.className = 'small mt-1 ' + (isSuccess ? 'text-success' : 'text-danger');
    }
    function refreshMainCategorySelect(categories, preserveVal) {
        var sel = document.getElementById('categorySelect');
        if (!sel) return;
        var prev = preserveVal !== undefined ? preserveVal : sel.value;
        sel.innerHTML = '<option value="">— Not set —</option>';
        categories.forEach(function(c){
            var opt = document.createElement('option');
            opt.value = c.id;
            opt.textContent = c.name;
            if (String(c.id) === String(prev)) opt.selected = true;
            sel.appendChild(opt);
        });
    }
    function refreshMainSubCategories(subs, preserveVal) {
        var sel = document.getElementById('subCategorySelect');
        if (!sel) return;
        var catSel = document.getElementById('categorySelect');
        var filtered = subs;
        if (catSel && catSel.value) {
            filtered = subs.filter(function(s){ return String(s.category_id) === String(catSel.value); });
        }
        // when showing all (e.g. no category selected) we show all subs
        if (subs.length && filtered.length === 0 && (!catSel || !catSel.value)) { filtered = subs; }
        var prev = preserveVal !== undefined ? preserveVal : sel.value;
        sel.innerHTML = '<option value="">— Not set —</option>';
        // if filtered by category, list only those; otherwise all
        var toShow = (catSel && catSel.value) ? filtered : subs;
        toShow.forEach(function(s){
            var opt = document.createElement('option');
            opt.value = s.id;
            opt.textContent = s.name + ' (' + (s.category_name || '') + ')';
            if (String(s.id) === String(prev)) opt.selected = true;
            sel.appendChild(opt);
        });
    }

    // ── Category modal logic ────────────────────────────────────
    function loadCategories() {
        var loader = document.getElementById('catListLoader');
        var empty = document.getElementById('catListEmpty');
        var table = document.getElementById('catTable');
        var badge = document.getElementById('catCountBadge');
        if (loader) loader.classList.remove('d-none');
        if (empty) empty.classList.add('d-none');
        if (table) table.classList.add('d-none');
        return Monetix.request(CAT_INDEX, { method: 'GET' }).then(function(res){
            if (loader) loader.classList.add('d-none');
            if (!res || !res.success) { toast((res && res.message) || 'Failed to load categories', 'danger'); return; }
            var cats = res.data || [];
            catCategoriesCache = cats;
            if (badge) badge.textContent = cats.length;
            refreshMainCategorySelect(cats);
            // also update sub modal dropdowns
            renderSubAddCategorySelect(cats);
            renderSubEditCategorySelect(cats);
            if (!cats.length) {
                if (empty) empty.classList.remove('d-none');
                if (table) table.classList.add('d-none');
                document.getElementById('catTableBody').innerHTML = '';
                return;
            }
            if (table) table.classList.remove('d-none');
            if (empty) empty.classList.add('d-none');
            renderCategoryRows(cats);
        });
    }

    function renderCategoryRows(cats) {
        var tbody = document.getElementById('catTableBody');
        if (!tbody) return;
        tbody.innerHTML = '';
        cats.forEach(function(c){
            var tr = document.createElement('tr');
            var usage = [];
            if (c.courses_count) usage.push(c.courses_count + ' course(s)');
            if (c.subjects_count) usage.push(c.subjects_count + ' subject(s)');
            if (c.batches_count) usage.push(c.batches_count + ' batch(es)');
            if (c.sub_categories_count) usage.push(c.sub_categories_count + ' sub-cat(s)');
            var usageHtml = usage.length ? '<span class="badge text-bg-light border me-1">' + usage.join('</span> <span class="badge text-bg-light border me-1">') + '</span>' : '<span class="text-muted small">Unused</span>';
            // reassign options for delete modal built later
            tr.innerHTML = '<td class="small fw-semibold">' + esc(c.name) + '<div class="text-muted" style="font-size:11px;">' + esc(c.slug) + '</div></td>' +
                '<td class="small">' + usageHtml + '</td>' +
                '<td class="text-end text-nowrap">' +
                '  <button type="button" class="btn btn-outline-secondary btn-sm me-1 cat-edit-btn" data-id="' + c.id + '" data-name="' + escAttr(c.name) + '"><i class="bi bi-pencil me-1"></i>Edit</button>' +
                '  <button type="button" class="btn btn-outline-danger btn-sm cat-delete-btn" data-id="' + c.id + '" data-name="' + escAttr(c.name) + '"><i class="bi bi-trash me-1"></i>Delete</button>' +
                '</td>';
            tbody.appendChild(tr);
        });
    }

    function esc(s){ var d=document.createElement('div'); d.textContent=s; return d.innerHTML; }
    function escAttr(s){ return String(s).replace(/"/g,'&quot;'); }

    function renderSubAddCategorySelect(cats){
        var sel = document.getElementById('subAddCategorySelect');
        if (!sel) return;
        var prev = sel.value;
        sel.innerHTML = '<option value="">— Select category —</option>';
        (cats || catCategoriesCache).forEach(function(c){
            var o=document.createElement('option'); o.value=c.id; o.textContent=c.name;
            if (String(c.id)===String(prev)) o.selected = true;
            sel.appendChild(o);
        });
    }
    function renderSubEditCategorySelect(cats){
        var sel = document.getElementById('subEditCategorySelect');
        if (!sel) return;
        var prev = sel.value;
        var list = cats || catCategoriesCache;
        sel.innerHTML = '';
        list.forEach(function(c){
            var o=document.createElement('option'); o.value=c.id; o.textContent=c.name;
            if (String(c.id)===String(prev)) o.selected = true;
            sel.appendChild(o);
        });
        if (list.length && !prev) { sel.value = list[0].id; }
    }

    // category add
    var catAddBtn = document.getElementById('catAddBtn');
    if (catAddBtn) {
        catAddBtn.addEventListener('click', function(){
            var inp = document.getElementById('catAddInput');
            var fb = document.getElementById('catAddFeedback');
            var name = inp ? inp.value.trim() : '';
            if (!name) { setFeedback(fb, 'Name is required.'); return; }
            setFeedback(fb, '');
            catAddBtn.disabled = true;
            Monetix.request(CAT_STORE, { method:'POST', body: { name: name } }).then(function(res){
                catAddBtn.disabled = false;
                if (!res || res.success === false) {
                    var msg = (res && res.errors && res.errors.name) ? res.errors.name[0] : (res && res.message) || 'Failed';
                    setFeedback(fb, msg);
                    toast(msg,'danger');
                    return;
                }
                if (inp) inp.value = '';
                setFeedback(fb, res.message || 'Category created.', true);
                toast(res.message || 'Category created.');
                loadCategories();
            });
        });
    }

    // delegated edit/delete in category list
    document.addEventListener('click', function(e){
        var btn = e.target.closest('.cat-edit-btn');
        if (btn) {
            catEditId = btn.getAttribute('data-id');
            var name = btn.getAttribute('data-name') || '';
            document.getElementById('catEditInput').value = name;
            document.getElementById('catEditFeedback').textContent = '';
            document.getElementById('catInlineEditWrap').classList.remove('d-none');
            document.getElementById('catDeleteWrap').classList.add('d-none');
            document.getElementById('catEditInput').focus();
        }
        var dbtn = e.target.closest('.cat-delete-btn');
        if (dbtn) {
            catDeleteId = dbtn.getAttribute('data-id');
            var cat = catCategoriesCache.find(function(c){ return String(c.id)===String(catDeleteId); });
            catDeleteData = cat;
            document.getElementById('catInlineEditWrap').classList.add('d-none');
            document.getElementById('catDeleteName').textContent = cat ? cat.name : '';
            var counts = cat ? (cat.courses_count + ' course(s), ' + cat.subjects_count + ' subject(s), ' + cat.batches_count + ' batch(es), ' + cat.sub_categories_count + ' sub-cat(s)') : '';
            document.getElementById('catDeleteCounts').textContent = counts;
            var dep = cat ? (cat.courses_count + cat.subjects_count + cat.batches_count + cat.sub_categories_count) : 0;
            document.getElementById('catDeleteDepCount').textContent = dep;
            document.getElementById('catDeleteFeedback').textContent = '';
            var group = document.getElementById('catDeleteReassignGroup');
            var sel = document.getElementById('catDeleteReassignSelect');
            if (dep > 0) {
                group.classList.remove('d-none');
                sel.innerHTML = '<option value="">— Select replacement —</option>';
                catCategoriesCache.forEach(function(c){
                    if (String(c.id) === String(catDeleteId)) return;
                    var o=document.createElement('option'); o.value=c.id; o.textContent=c.name; sel.appendChild(o);
                });
            } else {
                group.classList.add('d-none');
                sel.innerHTML = '<option value="">— Select replacement —</option>';
            }
            document.getElementById('catDeleteWrap').classList.remove('d-none');
            document.getElementById('catDeleteWrap').scrollIntoView({ behavior:'smooth', block:'nearest'});
        }
    });

    var catEditCancelBtn = document.getElementById('catEditCancelBtn');
    if (catEditCancelBtn) catEditCancelBtn.addEventListener('click', function(){
        catEditId = null;
        document.getElementById('catInlineEditWrap').classList.add('d-none');
    });
    var catEditSaveBtn = document.getElementById('catEditSaveBtn');
    if (catEditSaveBtn) catEditSaveBtn.addEventListener('click', function(){
        if (!catEditId) return;
        var name = document.getElementById('catEditInput').value.trim();
        var fb = document.getElementById('catEditFeedback');
        if (!name) { fb.textContent = 'Name is required.'; return; }
        fb.textContent = '';
        catEditSaveBtn.disabled = true;
        Monetix.request(catUrl(catEditId), { method:'PUT', body: { name: name } }).then(function(res){
            catEditSaveBtn.disabled = false;
            if (!res || res.success === false) {
                var msg = (res && res.errors && res.errors.name) ? res.errors.name[0] : (res && res.message) || 'Failed';
                fb.textContent = msg; toast(msg,'danger'); return;
            }
            document.getElementById('catInlineEditWrap').classList.add('d-none');
            catEditId = null;
            toast(res.message || 'Category updated.');
            loadCategories();
        });
    });

    var catDeleteCancelBtn = document.getElementById('catDeleteCancelBtn');
    if (catDeleteCancelBtn) catDeleteCancelBtn.addEventListener('click', function(){
        catDeleteId = null; catDeleteData = null;
        document.getElementById('catDeleteWrap').classList.add('d-none');
    });
    var catDeleteConfirmBtn = document.getElementById('catDeleteConfirmBtn');
    if (catDeleteConfirmBtn) catDeleteConfirmBtn.addEventListener('click', function(){
        if (!catDeleteId) return;
        var dep = catDeleteData ? (catDeleteData.courses_count + catDeleteData.subjects_count + catDeleteData.batches_count + catDeleteData.sub_categories_count) : 0;
        var sel = document.getElementById('catDeleteReassignSelect');
        var fb = document.getElementById('catDeleteFeedback');
        fb.textContent = '';
        var payload = {};
        if (dep > 0) {
            if (!sel.value) { fb.textContent = 'Please select a replacement category.'; return; }
            payload.replacement_category_id = sel.value;
        }
        catDeleteConfirmBtn.disabled = true;
        Monetix.request(catUrl(catDeleteId), { method:'DELETE', body: payload }).then(function(res){
            catDeleteConfirmBtn.disabled = false;
            if (!res || res.success === false) {
                var msg = (res && res.errors && (res.errors.replacement_category_id || res.errors.name)) ? (res.errors.replacement_category_id||res.errors.name)[0] : (res && res.message) || 'Failed';
                fb.textContent = msg; toast(msg,'danger'); return;
            }
            document.getElementById('catDeleteWrap').classList.add('d-none');
            catDeleteId = null; catDeleteData = null;
            toast(res.message || 'Category deleted.');
            loadCategories();
            // if current course's category was deleted and we had reassign, reset main select? load will preserve.
        });
    });

    // open category modal triggers load
    var catModalEl = document.getElementById('categoryManageModal');
    if (catModalEl) {
        catModalEl.addEventListener('show.bs.modal', function(){ loadCategories(); });
        catModalEl.addEventListener('hidden.bs.modal', function(){
            document.getElementById('catInlineEditWrap').classList.add('d-none');
            document.getElementById('catDeleteWrap').classList.add('d-none');
            document.getElementById('catAddFeedback').textContent = '';
            catEditId=null; catDeleteId=null;
        });
    }

    // ── Sub category modal logic ─────────────────────────────────
    function loadSubCategories() {
        var loader = document.getElementById('subListLoader');
        var empty = document.getElementById('subListEmpty');
        var table = document.getElementById('subTable');
        var badge = document.getElementById('subCountBadge');
        if (loader) loader.classList.remove('d-none');
        if (empty) empty.classList.add('d-none');
        if (table) table.classList.add('d-none');
        return Monetix.request(SUB_INDEX, { method:'GET' }).then(function(res){
            if (loader) loader.classList.add('d-none');
            if (!res || !res.success) { toast((res && res.message)||'Failed to load sub categories','danger'); return; }
            var payload = res.data || {};
            var subs = payload.sub_categories || [];
            var cats = payload.categories || [];
            subCategoriesCache = subs;
            // if categories came, refresh cache and selectors
            if (cats.length) {
                catCategoriesCache = cats.map(function(c){ return { id:c.id, name:c.name }; });
                refreshMainCategorySelect(cats);
                renderSubAddCategorySelect(cats);
                renderSubEditCategorySelect(cats);
            }
            refreshMainSubCategories(subs);
            if (badge) badge.textContent = subs.length;
            if (!subs.length) {
                if (empty) empty.classList.remove('d-none');
                if (table) table.classList.add('d-none');
                document.getElementById('subTableBody').innerHTML = '';
                return;
            }
            if (table) table.classList.remove('d-none');
            if (empty) empty.classList.add('d-none');
            renderSubRows(subs);
        });
    }

    function renderSubRows(subs){
        var tbody = document.getElementById('subTableBody');
        if (!tbody) return;
        tbody.innerHTML = '';
        subs.forEach(function(s){
            var usage = [];
            if (s.courses_count) usage.push(s.courses_count + ' course(s)');
            if (s.batches_count) usage.push(s.batches_count + ' batch(es)');
            var usageHtml = usage.length ? '<span class="badge text-bg-light border me-1">' + usage.join('</span> <span class="badge text-bg-light border me-1">') + '</span>' : '<span class="text-muted small">Unused</span>';
            var tr=document.createElement('tr');
            tr.innerHTML = '<td class="small fw-semibold">' + esc(s.name) + '<div class="text-muted" style="font-size:11px;">' + esc(s.slug) + '</div></td>' +
                '<td class="small">' + esc(s.category_name) + '</td>' +
                '<td class="small">' + usageHtml + '</td>' +
                '<td class="text-end text-nowrap">' +
                '  <button type="button" class="btn btn-outline-secondary btn-sm me-1 sub-edit-btn" data-id="' + s.id + '" data-name="' + escAttr(s.name) + '" data-cat="' + s.category_id + '"><i class="bi bi-pencil me-1"></i>Edit</button>' +
                '  <button type="button" class="btn btn-outline-danger btn-sm sub-delete-btn" data-id="' + s.id + '" data-name="' + escAttr(s.name) + '"><i class="bi bi-trash me-1"></i>Delete</button>' +
                '</td>';
            tbody.appendChild(tr);
        });
    }

    var subAddBtn = document.getElementById('subAddBtn');
    if (subAddBtn) {
        subAddBtn.addEventListener('click', function(){
            var nameInp = document.getElementById('subAddInput');
            var catSel = document.getElementById('subAddCategorySelect');
            var fb = document.getElementById('subAddFeedback');
            var name = nameInp ? nameInp.value.trim() : '';
            var catId = catSel ? catSel.value : '';
            if (!catId) { setFeedback(fb, 'Please select a category.'); return; }
            if (!name) { setFeedback(fb, 'Name is required.'); return; }
            setFeedback(fb,'');
            subAddBtn.disabled = true;
            Monetix.request(SUB_STORE, { method:'POST', body: { name: name, category_id: catId } }).then(function(res){
                subAddBtn.disabled = false;
                if (!res || res.success===false) {
                    var msg = (res && res.errors && (res.errors.name || res.errors.category_id)) ? (res.errors.name||res.errors.category_id)[0] : (res && res.message)||'Failed';
                    setFeedback(fb, msg); toast(msg,'danger'); return;
                }
                if (nameInp) nameInp.value = '';
                setFeedback(fb, res.message||'Sub Category created.', true);
                toast(res.message||'Sub Category created.');
                loadSubCategories();
            });
        });
    }

    document.addEventListener('click', function(e){
        var btn = e.target.closest('.sub-edit-btn');
        if (btn) {
            subEditId = btn.getAttribute('data-id');
            var name = btn.getAttribute('data-name') || '';
            var catId = btn.getAttribute('data-cat') || '';
            document.getElementById('subEditInput').value = name;
            document.getElementById('subEditCategorySelect').value = catId;
            document.getElementById('subEditFeedback').textContent = '';
            document.getElementById('subInlineEditWrap').classList.remove('d-none');
            document.getElementById('subDeleteWrap').classList.add('d-none');
        }
        var dbtn = e.target.closest('.sub-delete-btn');
        if (dbtn) {
            subDeleteId = dbtn.getAttribute('data-id');
            var sub = subCategoriesCache.find(function(s){ return String(s.id)===String(subDeleteId); });
            subDeleteData = sub;
            document.getElementById('subInlineEditWrap').classList.add('d-none');
            document.getElementById('subDeleteName').textContent = sub ? sub.name : '';
            var counts = sub ? (sub.courses_count + ' course(s), ' + sub.batches_count + ' batch(es)') : '';
            document.getElementById('subDeleteCounts').textContent = counts;
            var dep = sub ? (sub.courses_count + sub.batches_count) : 0;
            document.getElementById('subDeleteDepCount').textContent = dep;
            document.getElementById('subDeleteFeedback').textContent = '';
            var group = document.getElementById('subDeleteReassignGroup');
            var sel = document.getElementById('subDeleteReassignSelect');
            var subDeleteHint = document.getElementById('subDeleteReassignHint');
            if (dep > 0) {
                group.classList.remove('d-none');
                sel.innerHTML = '<option value="">— Select replacement —</option>';
                var sameParent = subCategoriesCache.filter(function(s){ return String(s.category_id)===String(sub ? sub.category_id : '') && String(s.id)!==String(subDeleteId); });
                var candidates = sameParent.length ? sameParent : subCategoriesCache.filter(function(s){ return String(s.id)!==String(subDeleteId); });
                var isFallbackGlobal = sameParent.length === 0 && candidates.length > 0;
                if (subDeleteHint) {
                    subDeleteHint.textContent = isFallbackGlobal
                        ? 'No other sub-category in "' + (sub ? sub.category_name : '') + '"; showing all sub-categories globally.'
                        : 'Filtered: only sub-categories under "' + (sub ? sub.category_name : '') + '".';
                    subDeleteHint.classList.toggle('d-none', false);
                }
                if (!candidates.length) {
                    var o0=document.createElement('option'); o0.value=''; o0.textContent='— No replacement available (create one first) —'; o0.disabled=true; sel.appendChild(o0);
                    document.getElementById('subDeleteConfirmBtn').disabled = true;
                    document.getElementById('subDeleteFeedback').textContent = 'No replacement sub-category exists. Create another sub-category first.';
                } else {
                    document.getElementById('subDeleteConfirmBtn').disabled = false;
                    document.getElementById('subDeleteFeedback').textContent = '';
                    candidates.forEach(function(s){
                        var o=document.createElement('option'); o.value=s.id; o.textContent=s.name + ' (' + s.category_name + ')'; sel.appendChild(o);
                    });
                }
            } else {
                group.classList.add('d-none');
                sel.innerHTML = '<option value="">— Select replacement —</option>';
                if (document.getElementById('subDeleteReassignHint')) document.getElementById('subDeleteReassignHint').classList.add('d-none');
                document.getElementById('subDeleteConfirmBtn').disabled = false;
            }
            document.getElementById('subDeleteWrap').classList.remove('d-none');
            document.getElementById('subDeleteWrap').scrollIntoView({ behavior:'smooth', block:'nearest'});
        }
    });

    var subEditCancelBtn = document.getElementById('subEditCancelBtn');
    if (subEditCancelBtn) subEditCancelBtn.addEventListener('click', function(){
        subEditId=null;
        document.getElementById('subInlineEditWrap').classList.add('d-none');
    });
    var subEditSaveBtn = document.getElementById('subEditSaveBtn');
    if (subEditSaveBtn) subEditSaveBtn.addEventListener('click', function(){
        if (!subEditId) return;
        var name = document.getElementById('subEditInput').value.trim();
        var catId = document.getElementById('subEditCategorySelect').value;
        var fb = document.getElementById('subEditFeedback');
        if (!catId) { fb.textContent='Please select a category.'; return; }
        if (!name) { fb.textContent='Name is required.'; return; }
        fb.textContent='';
        subEditSaveBtn.disabled = true;
        Monetix.request(subUrl(subEditId), { method:'PUT', body: { name:name, category_id: catId } }).then(function(res){
            subEditSaveBtn.disabled = false;
            if (!res || res.success===false) {
                var msg = (res && res.errors && (res.errors.name||res.errors.category_id)) ? (res.errors.name||res.errors.category_id)[0] : (res&&res.message)||'Failed';
                fb.textContent = msg; toast(msg,'danger'); return;
            }
            document.getElementById('subInlineEditWrap').classList.add('d-none');
            subEditId=null;
            toast(res.message||'Sub Category updated.');
            loadSubCategories();
        });
    });

    var subDeleteCancelBtn = document.getElementById('subDeleteCancelBtn');
    if (subDeleteCancelBtn) subDeleteCancelBtn.addEventListener('click', function(){
        subDeleteId=null; subDeleteData=null;
        document.getElementById('subDeleteWrap').classList.add('d-none');
    });
    var subDeleteConfirmBtn = document.getElementById('subDeleteConfirmBtn');
    if (subDeleteConfirmBtn) subDeleteConfirmBtn.addEventListener('click', function(){
        if (!subDeleteId) return;
        var dep = subDeleteData ? (subDeleteData.courses_count + subDeleteData.batches_count) : 0;
        var sel = document.getElementById('subDeleteReassignSelect');
        var fb = document.getElementById('subDeleteFeedback');
        fb.textContent='';
        var payload = {};
        if (dep > 0) {
            if (!sel.value) { fb.textContent='Please select a replacement sub category.'; return; }
            payload.replacement_sub_category_id = sel.value;
        }
        subDeleteConfirmBtn.disabled = true;
        Monetix.request(subUrl(subDeleteId), { method:'DELETE', body: payload }).then(function(res){
            subDeleteConfirmBtn.disabled = false;
            if (!res || res.success===false) {
                var msg = (res && res.errors && res.errors.replacement_sub_category_id) ? res.errors.replacement_sub_category_id[0] : (res&&res.message)||'Failed';
                fb.textContent = msg; toast(msg,'danger'); return;
            }
            document.getElementById('subDeleteWrap').classList.add('d-none');
            subDeleteId=null; subDeleteData=null;
            toast(res.message||'Sub Category deleted.');
            loadSubCategories();
        });
    });

    var subModalEl = document.getElementById('subCategoryManageModal');
    if (subModalEl) {
        subModalEl.addEventListener('show.bs.modal', function(){ loadSubCategories(); loadCategories(); });
        subModalEl.addEventListener('hidden.bs.modal', function(){
            document.getElementById('subInlineEditWrap').classList.add('d-none');
            document.getElementById('subDeleteWrap').classList.add('d-none');
            document.getElementById('subAddFeedback').textContent='';
            subEditId=null; subDeleteId=null;
        });
    }

    // ── main form category -> sub category dynamic ──────────────
    var categorySelect = document.getElementById('categorySelect');
    var subCategorySelect = document.getElementById('subCategorySelect');
    if (categorySelect) {
        categorySelect.addEventListener('change', function(){
            var cid = this.value;
            if (!cid) {
                // reload all subs for unfiltered view
                if (subCategoriesCache.length) {
                    refreshMainSubCategories(subCategoriesCache);
                } else {
                    Monetix.request(SUB_INDEX, { method:'GET' }).then(function(res){
                        if (res && res.success) {
                            var payload = res.data || {};
                            var subs = payload.sub_categories || [];
                            subCategoriesCache = subs;
                            refreshMainSubCategories(subs);
                        }
                    });
                }
                return;
            }
            Monetix.request(SUB_INDEX + '?category_id=' + encodeURIComponent(cid), { method:'GET' }).then(function(res){
                if (!res || !res.success) return;
                var payload = res.data || {};
                var subs = payload.sub_categories || [];
                // keep cache for other actions? but preserve full cache separately if needed
                var prev = subCategorySelect ? subCategorySelect.value : '';
                if (subCategorySelect) {
                    subCategorySelect.innerHTML = '<option value="">— Not set —</option>';
                    subs.forEach(function(s){
                        var opt=document.createElement('option');
                        opt.value=s.id; opt.textContent=s.name;
                        if (String(s.id)===String(prev)) opt.selected = true;
                        subCategorySelect.appendChild(opt);
                    });
                }
            });
        });
    }

    // initial load for edit case: preload subs for existing category
    (function preloadSubs(){
        var catSel = document.getElementById('categorySelect');
        if (catSel && catSel.value) {
            Monetix.request(SUB_INDEX + '?category_id=' + encodeURIComponent(catSel.value), { method:'GET' }).then(function(res){
                if (res && res.success) {
                    var payload = res.data || {};
                    var subs = payload.sub_categories || [];
                    subCategoriesCache = subs;
                    var scSel = document.getElementById('subCategorySelect');
                    var prev = scSel ? scSel.value : '';
                    if (scSel) {
                        // re-render but keep selected
                        var tmp = subs;
                        scSel.innerHTML = '<option value="">— Not set —</option>';
                        tmp.forEach(function(s){
                            var opt=document.createElement('option');
                            opt.value=s.id; opt.textContent=s.name;
                            if (String(s.id)===String(prev)) opt.selected = true;
                            scSel.appendChild(opt);
                        });
                    }
                }
            });
        }
    })();

})();
</script>
@endpush