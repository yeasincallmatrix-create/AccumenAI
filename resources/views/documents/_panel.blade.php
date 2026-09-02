@php
    $docUser = $user ?? auth('institute_user')->user();
    $docCanManage = $docUser !== null && $docUser->hasPermission('documents.manage');
    $docPanelId = 'docPanel-'.$entityType.'-'.$entityId;
@endphp

<div class="document-panel" id="{{ $docPanelId }}"
     data-entity="{{ $entityType }}"
     data-entity-id="{{ $entityId }}"
     data-csrf="{{ csrf_token() }}"
     data-index-url="{{ route('documents.index') }}"
     data-store-url="{{ route('documents.store') }}"
     data-categories-url="{{ route('documents.categories') }}"
     data-can-manage="{{ $docCanManage ? '1' : '0' }}"
     data-translations="{{ json_encode([
         'select_type' => mawa_e('documents.select_type'),
         'no_documents' => mawa_e('documents.no_documents'),
         'download' => mawa_e('documents.download'),
         'replace' => mawa_e('documents.replace'),
         'edit' => mawa_e('documents.edit'),
         'restore' => mawa_e('documents.restore'),
         'archive' => mawa_e('documents.archive'),
         'delete' => mawa_e('documents.delete'),
         'other' => mawa_e('documents.other'),
         'error' => mawa_e('documents.error_try_again'),
         'select_file' => mawa_e('documents.select_file'),
         'select_doc_type' => mawa_e('documents.select_doc_type'),
         'confirm_delete' => mawa_e('documents.confirm_delete'),
         'confirm_archive' => mawa_e('documents.confirm_archive'),
         'confirm_restore' => mawa_e('documents.confirm_restore'),
     ]) }}">

    <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="fw-bold text-primary mb-0"><i class="bi bi-folder2-open me-1"></i>{{ mawa_e('documents.title') }}</h6>
        <span class="badge bg-primary document-count">0</span>
    </div>

    @if ($docCanManage)
        <form class="document-upload-form row g-2 mb-3" enctype="multipart/form-data">
            <div class="col-md-4">
                <select name="category_id" class="form-select form-select-sm document-category-select" required aria-label="Document type">
                    <option value="">{{ mawa_e('documents.select_type') }}</option>
                </select>
            </div>
            <div class="col-md-4">
                <input type="text" name="title" class="form-control form-control-sm" maxlength="200" placeholder="{{ mawa_e('documents.title_optional') }}">
            </div>
            <div class="col-md-4">
                <input type="file" name="file" class="form-control form-control-sm" required>
            </div>
            <div class="col-12 text-end">
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="bi bi-upload me-1"></i>{{ mawa_e('documents.upload') }}
                </button>
            </div>
        </form>
    @endif

    <div class="document-status d-none" role="status"></div>

    <div class="table-responsive">
        <table class="table align-middle mb-0 document-list-table">
            <thead>
                <tr>
                    <th>{{ mawa_e('documents.file') }}</th>
                    <th>{{ mawa_e('documents.type_label') }}</th>
                    <th class="text-end">{{ mawa_e('documents.size') }}</th>
                    <th class="text-end">{{ mawa_e('documents.version') }}</th>
                    <th>{{ mawa_e('documents.uploaded') }}</th>
                    <th class="text-end">{{ mawa_e('documents.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr class="document-empty">
                    <td colspan="6" class="text-center text-muted py-4">{{ mawa_e('documents.loading') }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

@if ($docCanManage)
    <div class="modal fade" id="docEditModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form class="document-edit-form">
                    <input type="hidden" name="id" value="">
                    <div class="modal-header">
                        <h6 class="modal-title">{{ mawa_e('documents.edit_document') }}</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">{{ mawa_e('documents.title_label') }}</label>
                            <input type="text" name="title" class="form-control" maxlength="200">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ mawa_e('documents.type_label') }}</label>
                            <select name="category_id" class="form-select document-edit-category"></select>
                        </div>
                        <div class="mb-0">
                            <label class="form-label">{{ mawa_e('documents.description_label') }}</label>
                            <textarea name="description" class="form-control" rows="2" maxlength="2000"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">{{ mawa_e('documents.cancel') }}</button>
                        <button type="submit" class="btn btn-sm btn-primary">{{ mawa_e('documents.save') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif

@push('scripts')
    <script>
        (function () {
            'use strict';

            function panelOf(el) {
                return el ? el.closest('.document-panel') : null;
            }

            function esc(s) {
                return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
                    return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
                });
            }

            function t(panel, key) {
                try { return JSON.parse(panel.dataset.translations || '{}')[key] || ''; } catch (e) { return ''; }
            }

            function status(panel, msg, ok) {
                var el = panel.querySelector('.document-status');
                if (!el) { return; }
                if (!msg) { el.classList.add('d-none'); return; }
                el.className = 'document-status alert alert-' + (ok ? 'success' : 'danger') + ' py-1 px-2';
                el.innerHTML = esc(msg);
            }

            function request(panel, url, options) {
                options = options || {};
                var method = (options.method || 'GET').toUpperCase();
                var headers = { 'Accept': 'application/json' };
                if (['POST', 'PUT', 'PATCH', 'DELETE'].indexOf(method) !== -1) {
                    headers['X-CSRF-TOKEN'] = panel.dataset.csrf;
                }
                var body = options.body;
                var isForm = typeof FormData !== 'undefined' && body instanceof FormData;
                if (body && !isForm && typeof body !== 'string') {
                    headers['Content-Type'] = 'application/json';
                    body = JSON.stringify(body);
                }
                return fetch(url, {
                    method: method,
                    headers: headers,
                    body: body,
                    credentials: 'same-origin',
                }).then(function (res) {
                    return res.json().catch(function () {
                        return { success: false, message: t(panel, 'error') };
                    });
                });
            }

            function loadCategories(panel) {
                var url = panel.dataset.categoriesUrl + '?entity=' + encodeURIComponent(panel.dataset.entity);
                request(panel, url).then(function (res) {
                    if (!res || res.success === false) { return; }
                    var options = '<option value="">' + t(panel, 'select_type') + '</option>';
                    (res.data || []).forEach(function (c) {
                        options += '<option value="' + c.id + '">' + esc(c.name) + '</option>';
                    });
                    var sel = panel.querySelector('.document-category-select');
                    if (sel) { sel.innerHTML = options; }
                    var edit = document.querySelector('.document-edit-category');
                    if (edit) { edit.innerHTML = (res.data || []).map(function (c) {
                        return '<option value="' + c.id + '">' + esc(c.name) + '</option>';
                    }).join(''); }
                });
            }

            function loadDocs(panel) {
                var url = panel.dataset.indexUrl + '?entity=' + encodeURIComponent(panel.dataset.entity)
                    + '&id=' + encodeURIComponent(panel.dataset.entityId);
                request(panel, url).then(function (res) {
                    if (!res || res.success === false) { return; }
                    renderDocs(panel, res.data || []);
                });
            }

            function renderDocs(panel, docs) {
                var tbody = panel.querySelector('.document-list-table tbody');
                var count = panel.querySelector('.document-count');
                if (count) { count.textContent = docs.length; }
                if (!docs.length) {
                    tbody.innerHTML = '<tr class="document-empty"><td colspan="6" class="text-center text-muted py-4">' + t(panel, 'no_documents') + '</td></tr>';
                    return;
                }
                var canManage = panel.dataset.canManage === '1';
                tbody.innerHTML = docs.map(function (d) {
                    var actions = '<a href="' + esc(d.download_url) + '" class="btn btn-sm btn-outline-primary" title="' + t(panel, 'download') + '"><i class="bi bi-download"></i></a>';
                    if (canManage) {
                        actions += '<button type="button" class="btn btn-sm btn-outline-secondary doc-replace" title="' + t(panel, 'replace') + '" data-id="' + d.id + '" data-url="' + esc(d.replace_url) + '"><i class="bi bi-arrow-repeat"></i></button>';
                        actions += '<button type="button" class="btn btn-sm btn-outline-secondary doc-edit" title="' + t(panel, 'edit') + '" data-id="' + d.id + '" data-title="' + esc(d.title || '') + '" data-description="' + esc(d.description || '') + '" data-category="' + (d.category_id || '') + '" data-url="' + esc(d.update_url) + '"><i class="bi bi-pencil"></i></button>';
                        if (d.status === 'archived') {
                            actions += '<button type="button" class="btn btn-sm btn-outline-success doc-restore" title="' + t(panel, 'restore') + '" data-id="' + d.id + '" data-url="' + esc(d.restore_url) + '"><i class="bi bi-arrow-counterclockwise"></i></button>';
                        } else {
                            actions += '<button type="button" class="btn btn-sm btn-outline-warning doc-archive" title="' + t(panel, 'archive') + '" data-id="' + d.id + '" data-url="' + esc(d.archive_url) + '"><i class="bi bi-archive"></i></button>';
                        }
                        actions += '<button type="button" class="btn btn-sm btn-outline-danger doc-delete" title="' + t(panel, 'delete') + '" data-id="' + d.id + '" data-url="' + esc(d.delete_url) + '"><i class="bi bi-trash"></i></button>';
                    }
                    var title = d.title ? esc(d.title) : esc(d.original_filename);
                    return '<tr>'
                        + '<td><span class="fw-semibold">' + title + '</span>'
                        + (d.description ? '<small class="text-muted d-block">' + esc(d.description) + '</small>' : '')
                        + '<small class="text-muted d-block">' + esc(d.original_filename) + '</small></td>'
                        + '<td><span class="badge bg-light text-dark border">' + esc(d.category || t(panel, 'other')) + '</span></td>'
                        + '<td class="text-end text-muted">' + esc(d.size_label) + '</td>'
                        + '<td class="text-end"><span class="badge bg-info bg-opacity-25 text-info border border-info">v' + esc(d.version) + '</span></td>'
                        + '<td class="text-muted"><small>' + esc(d.uploaded_by) + '<br>' + esc(d.created_at) + '</small></td>'
                        + '<td class="text-end text-nowrap">' + actions + '</td>'
                        + '</tr>';
                }).join('');
            }

            function bindListeners() {
                document.addEventListener('submit', function (e) {
                    var form = e.target;
                    if (form.classList.contains('document-upload-form')) {
                    var panel = panelOf(form);
                    if (!panel) { return; }
                    e.preventDefault();
                    var file = form.querySelector('[name="file"]');
                    var cat = form.querySelector('[name="category_id"]');
                    if (!file || !file.files || !file.files.length) {
                        status(panel, t(panel, 'select_file'), false);
                        return;
                    }
                    if (!cat || !cat.value) {
                        status(panel, t(panel, 'select_doc_type'), false);
                        return;
                    }
                    var fd = new FormData(form);
                    fd.append('entity', panel.dataset.entity);
                    fd.append('entity_id', panel.dataset.entityId);
                    var btn = form.querySelector('[type="submit"]');
                    if (btn) { btn.disabled = true; }
                    request(panel, panel.dataset.storeUrl, { method: 'POST', body: fd }).then(function (res) {
                        if (btn) { btn.disabled = false; }
                        status(panel, res.message, res.success !== false);
                        if (res.success === false) { return; }
                        form.reset();
                        loadCategories(panel);
                        loadDocs(panel);
                    });
                    return;
                }

                if (form.classList.contains('document-edit-form')) {
                    e.preventDefault();
                    var modal = form.closest('.modal');
                    var panel = panelOf(modal) || document.querySelector('.document-panel');
                    if (!panel) { return; }
                    var fd = new FormData(form);
                    fd.append('_method', 'PATCH');
                    request(panel, form.querySelector('[name="id"]').dataset.url || form.action, { method: 'POST', body: fd }).then(function (res) {
                        status(panel, res.message, res.success !== false);
                        if (res.success === false) { return; }
                        if (window.bootstrap && modal) {
                            window.bootstrap.Modal.getInstance(modal) && window.bootstrap.Modal.getInstance(modal).hide();
                        }
                        loadDocs(panel);
                    });
                }
            });

            document.addEventListener('click', function (e) {
                var btn = e.target.closest ? e.target.closest('.doc-replace, .doc-edit, .doc-archive, .doc-restore, .doc-delete') : null;
                if (!btn) { return; }
                var panel = panelOf(btn);
                if (!panel) { return; }

                if (btn.classList.contains('doc-replace')) {
                    var input = document.createElement('input');
                    input.type = 'file';
                    input.addEventListener('change', function () {
                        if (!input.files || !input.files.length) { return; }
                        var fd = new FormData();
                        fd.append('file', input.files[0]);
                        request(panel, btn.dataset.url, { method: 'POST', body: fd }).then(function (res) {
                            status(panel, res.message, res.success !== false);
                            if (res.success === false) { return; }
                            loadDocs(panel);
                        });
                    });
                    input.click();
                    return;
                }

                if (btn.classList.contains('doc-edit')) {
                    var modal = document.getElementById('docEditModal');
                    var form = modal ? modal.querySelector('.document-edit-form') : null;
                    if (!form) { return; }
                    form.querySelector('[name="id"]').value = btn.dataset.id;
                    form.querySelector('[name="id"]').dataset.url = btn.dataset.url;
                    form.querySelector('[name="title"]').value = btn.dataset.title || '';
                    form.querySelector('[name="description"]').value = btn.dataset.description || '';
                    var sel = form.querySelector('[name="category_id"]');
                    sel.value = btn.dataset.category || '';
                    if (window.bootstrap && modal) { window.bootstrap.Modal.getOrCreateInstance(modal).show(); }
                    return;
                }

                if (btn.classList.contains('doc-archive') || btn.classList.contains('doc-restore') || btn.classList.contains('doc-delete')) {
                    var destructive = btn.classList.contains('doc-delete');
                    var msg = destructive
                        ? t(panel, 'confirm_delete')
                        : (btn.classList.contains('doc-archive') ? t(panel, 'confirm_archive') : t(panel, 'confirm_restore'));
                    if (!window.confirm(msg)) { return; }
                    var method = destructive ? 'DELETE' : 'POST';
                    request(panel, btn.dataset.url, { method: method }).then(function (res) {
                        status(panel, res.message, res.success !== false);
                        if (res.success === false) { return; }
                        loadDocs(panel);
                    });
                }
            });
            }

            document.querySelectorAll('.document-panel').forEach(function (panel) {
                loadCategories(panel);
                loadDocs(panel);
            });

            if (!window.__docPanelBound) {
                window.__docPanelBound = true;
                bindListeners();
            }
        })();
    </script>
@endpush