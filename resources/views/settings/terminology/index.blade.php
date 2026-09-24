@extends('layouts.institute')

@section('title', 'Terminology Settings - AccumenAI')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-11">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-translate me-2"></i>Terminology</h5>
                    <span class="badge bg-primary">{{ count($terms) }} terms</span>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-4">
                        Rename module wording for <strong>{{ $institute->name }}</strong> only — e.g. change
                        <em>Customer</em> to <em>Client</em>. Your wording wins over the country/global defaults for
                        every screen in this tenant. Leave a field <strong>empty</strong> to fall back to the default.
                    </p>

                    @if (session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif
                    @if ($errors->any())
                        <div class="alert alert-danger py-2">
                            @foreach ($errors->all() as $error)
                                <div class="small">{{ $error }}</div>
                            @endforeach
                        </div>
                    @endif

                    <form method="POST" action="{{ route('settings.terminology.update') }}">
                        @csrf
                        @method('PUT')

                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th style="min-width:170px">Term</th>
                                        <th class="text-center" style="width:170px">Country default ({{ $institute->country_code ?? 'BD' }})</th>
                                        <th class="text-center" style="width:170px">Global default</th>
                                        <th style="min-width:200px">Your wording</th>
                                        <th class="text-center" style="width:150px">Effective</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($terms as $key => $term)
                                        <tr>
                                            <td>
                                                <strong>{{ $term['label'] }}</strong>
                                                <br><small class="text-muted"><code>{{ $key }}</code></small>
                                            </td>
                                            <td class="text-center">
                                                @if ($term['country'] !== null)
                                                    <span class="badge bg-info-subtle text-info border">{{ $term['country'] }}</span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-light text-dark border">{{ $term['global'] }}</span>
                                            </td>
                                            <td>
                                                <input type="text"
                                                       name="terms[{{ $key }}]"
                                                       class="form-control form-control-sm"
                                                       maxlength="150"
                                                       placeholder="Leave empty to use default"
                                                       value="{{ old('terms.' . $key, $term['override'] ?? '') }}">
                                                @if (($term['override'] ?? null) !== null)
                                                    <small class="text-warning"><i class="bi bi-star-fill me-1"></i>override active</small>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-success-subtle text-success border">{{ $term['effective'] }}</span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-4">No terminology seeded yet — run <code>php artisan db:seed --class=ModuleTerminologySeeder</code>.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex gap-2 mt-3">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Terminology</button>
                            <a href="{{ route('settings.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to Settings</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
