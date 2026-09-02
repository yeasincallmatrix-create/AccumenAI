@php $keys = $keys ?? \App\Models\AiApiKey::orderBy('provider')->orderBy('name')->get(); @endphp
@php
$providerLabels = [
    'openai' => 'OpenAI',
    'anthropic' => 'Anthropic',
    'gemini' => 'Gemini',
    'groq' => 'Groq',
    'custom' => 'Custom',
];
@endphp
<div class="card mt-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0"><i class="bi bi-key me-1"></i> API Keys Management</h5>
        <span class="badge text-bg-secondary">{{ $keys->count() }} keys</span>
    </div>
    <div class="card-body">
        <p class="text-muted small">Manage multiple API keys per provider. Only the <strong>active</strong> key for the selected provider will be used by the AI service. Keys are encrypted at rest.</p>

        @if ($errors->any())
            <div class="alert alert-danger py-2 small">
                @foreach ($errors->all() as $e) <div>{{ $e }}</div> @endforeach
            </div>
        @endif
        @if (session('status'))
            <div class="alert alert-success py-2 small">{{ session('status') }}</div>
        @endif

        <!-- Add New Key Form -->
        <form action="{{ route('admin.ai-api-keys.store') }}" method="POST" class="row g-3 mb-4 p-3 border rounded bg-light">
            @csrf
            <div class="col-md-2">
                <label for="ak_provider" class="form-label">Provider *</label>
                <select name="provider" id="ak_provider" class="form-select" required>
                    <option value="openai">OpenAI</option>
                    <option value="anthropic">Anthropic</option>
                    <option value="gemini">Gemini</option>
                    <option value="groq">Groq</option>
                    <option value="custom">Custom</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="ak_capability" class="form-label">Capability *</label>
                <select name="capability" id="ak_capability" class="form-select" required>
                    <option value="text">Text</option>
                    <option value="image">Image</option>
                    <option value="vision">Vision</option>
                    <option value="embeddings">Embeddings</option>
                    <option value="speech">Speech</option>
                    <option value="audio">Audio</option>
                    <option value="moderation">Moderation</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="ak_name" class="form-label">Name</label>
                <input type="text" name="name" id="ak_name" class="form-control" placeholder="e.g., Production Key">
            </div>
            <div class="col-md-3">
                <label for="ak_api_key" class="form-label">API Key *</label>
                <input type="password" name="api_key" id="ak_api_key" class="form-control" required placeholder="sk-...">
            </div>
            <div class="col-md-2">
                <label for="ak_base_url" class="form-label">Base URL</label>
                <input type="url" name="base_url" id="ak_base_url" class="form-control" placeholder="Optional">
            </div>
            <div class="col-md-2">
                <label for="ak_model" class="form-label">Model</label>
                <input type="text" name="model" id="ak_model" class="form-control" placeholder="Optional">
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <div class="form-check">
                    <input type="checkbox" name="is_active" id="ak_is_active" class="form-check-input" value="1" checked>
                    <label for="ak_is_active" class="form-check-label">Active</label>
                </div>
            </div>
            <div class="col-12 d-flex justify-content-end">
                <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Add Key</button>
            </div>
        </form>

        <!-- Existing Keys List -->
        <div class="table-responsive">
            <table class="table table-bordered table-striped align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Provider</th>
                        <th>Capability</th>
                        <th>Name</th>
                        <th>Model</th>
                        <th>Base URL</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($keys as $key)
                    <tr>
                        <td class="text-muted small">{{ $loop->iteration }}</td>
                        <td><span class="badge text-bg-primary">{{ $key->provider }}</span></td>
                        <td><span class="badge text-bg-info">{{ ucfirst($key->capability ?? 'text') }}</span></td>
                        <td>{{ $key->name ?? '—' }}</td>
                        <td class="small">{{ $key->model ?? '—' }}</td>
                        <td class="small text-muted" style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $key->base_url ?? '—' }}</td>
                        <td>
                            <span class="badge {{ $key->is_active ? 'bg-success' : 'bg-secondary' }}">
                                {{ $key->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="text-end text-nowrap">
                            <form action="{{ route('admin.ai-api-keys.configure', $key) }}" method="POST" class="d-inline" onsubmit="return confirm('Configure platform AI with this key? This will overwrite the current provider, model, base URL and API key and enable AI.')">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-success" title="Set this key as the platform-wide AI configuration">
                                    <i class="bi bi-gear"></i> Configure
                                </button>
                            </form>
                            <button class="btn btn-sm btn-primary edit-key-btn"
                                    data-bs-toggle="modal"
                                    data-bs-target="#editApiKeyModal"
                                    data-id="{{ $key->id }}"
                                    data-provider="{{ $key->provider }}"
                                    data-capability="{{ $key->capability ?? 'text' }}"
                                    data-name="{{ $key->name }}"
                                    data-base_url="{{ $key->base_url }}"
                                    data-model="{{ $key->model }}"
                                    data-is_active="{{ $key->is_active ? 1 : 0 }}">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form action="{{ route('admin.ai-api-keys.toggle', $key) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm {{ $key->is_active ? 'btn-warning' : 'btn-success' }}">
                                    {{ $key->is_active ? 'Deactivate' : 'Activate' }}
                                </button>
                            </form>
                            <form action="{{ route('admin.ai-api-keys.destroy', $key) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this key? This cannot be undone.')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-3">No API keys configured yet. Add one above.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="form-text mt-2">Tip: Keep only one active key per provider. The active key is used first; fallback is the provider-specific setting <code>ai.api_key_{provider}</code> then generic <code>ai.api_key</code>.</div>
    </div>
</div>

<!-- Edit API Key Modal -->
<div class="modal fade" id="editApiKeyModal" tabindex="-1" aria-labelledby="editApiKeyModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="editApiKeyForm" method="POST" action="">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title" id="editApiKeyModalLabel">Edit API Key</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="edit-key-id">

                    <div class="mb-3">
                        <label class="form-label">Provider</label>
                        <input type="text" class="form-control" id="edit-provider" disabled>
                    </div>

                    <div class="mb-3">
                        <label for="edit-capability" class="form-label">Capability</label>
                        <select name="capability" id="edit-capability" class="form-select" required>
                            <option value="text">Text</option>
                            <option value="image">Image</option>
                            <option value="vision">Vision</option>
                            <option value="embeddings">Embeddings</option>
                            <option value="speech">Speech</option>
                            <option value="audio">Audio</option>
                            <option value="moderation">Moderation</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="edit-name" class="form-label">Name <span class="text-muted">(optional)</span></label>
                        <input type="text" name="name" id="edit-name" class="form-control" placeholder="e.g., Production Key">
                    </div>

                    <div class="mb-3">
                        <label for="edit-api_key" class="form-label">API Key</label>
                        <input type="password" name="api_key" id="edit-api_key" class="form-control" placeholder="Leave blank to keep current">
                        <small class="text-muted">Leave blank to keep the existing key. Enter a new key to update it.</small>
                    </div>

                    <div class="mb-3">
                        <label for="edit-base_url" class="form-label">Base URL <span class="text-muted">(optional)</span></label>
                        <input type="url" name="base_url" id="edit-base_url" class="form-control" placeholder="https://api.openai.com/v1">
                    </div>

                    <div class="mb-3">
                        <label for="edit-model" class="form-label">Model <span class="text-muted">(optional)</span></label>
                        <input type="text" name="model" id="edit-model" class="form-control" placeholder="gpt-4o-mini">
                    </div>

                    <div class="form-check">
                        <input type="checkbox" name="is_active" id="edit-is_active" class="form-check-input" value="1">
                        <label for="edit-is_active" class="form-check-label">Active</label>
                        <small class="d-block text-muted">Only active keys will be used by the AI service.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Key</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.edit-key-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const id = this.dataset.id;
                const provider = this.dataset.provider;
                const capability = this.dataset.capability || 'text';
                const name = this.dataset.name || '';
                const baseUrl = this.dataset.base_url || '';
                const model = this.dataset.model || '';
                const isActive = this.dataset.is_active == '1';

                document.getElementById('edit-key-id').value = id;
                document.getElementById('edit-provider').value = provider;
                document.getElementById('edit-capability').value = capability;
                document.getElementById('edit-name').value = name;
                document.getElementById('edit-base_url').value = baseUrl;
                document.getElementById('edit-model').value = model;
                document.getElementById('edit-is_active').checked = isActive;

                document.getElementById('editApiKeyForm').action = '{{ url("admin/ai-api-keys") }}/' + id;
            });
        });
    });
</script>
