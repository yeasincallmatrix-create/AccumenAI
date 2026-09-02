@extends('guardian.layout')

@section('title', mawa_e('guardian.profile_title'))

@section('content')
<h1 class="h4 mb-3">{{ mawa_e('guardian.profile_title') }}</h1>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card mb-3">
            <div class="card-header bg-transparent fw-semibold">
                <i class="bi bi-person-gear me-1"></i>{{ mawa_e('guardian.account_details') }}
            </div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr>
                            <th class="text-body-secondary" style="width:40%">{{ mawa_e('guardian.full_name') }}</th>
                            <td>{{ $guardian->name }}</td>
                        </tr>
                        <tr>
                            <th class="text-body-secondary">{{ mawa_e('guardian.phone') }}</th>
                            <td>{{ $guardian->phone }}</td>
                        </tr>
                        <tr>
                            <th class="text-body-secondary">{{ mawa_e('guardian.email') }}</th>
                            <td>{{ $guardian->email ?? mawa_e('guardian.na') }}</td>
                        </tr>
                        <tr>
                            <th class="text-body-secondary">{{ mawa_e('guardian.linked_students') }}</th>
                            <td>{{ $students->count() }}</td>
                        </tr>
                        <tr>
                            <th class="text-body-secondary">{{ mawa_e('guardian.member_since') }}</th>
                            <td>{{ $guardian->created_at?->format('d M Y') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-transparent fw-semibold">
                <i class="bi bi-people me-1"></i>{{ mawa_e('guardian.linked_students') }}
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    @foreach ($students as $linked)
                        <li class="list-group-item d-flex align-items-center justify-content-between">
                            <div>
                                <div class="fw-semibold">{{ $linked->full_name }}</div>
                                <div class="small text-body-secondary">{{ $linked->student_id }}</div>
                            </div>
                            <a class="btn btn-sm btn-outline-secondary rounded-pill" href="{{ route('guardian.students.show', $linked->id) }}"><i class="bi bi-person-vcard me-1"></i>{{ mawa_e('guardian.view_profile') }}</a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card">
            <div class="card-header bg-transparent fw-semibold">
                <i class="bi bi-key me-1"></i>{{ mawa_e('guardian.change_password') }}
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('guardian.profile.password') }}">
                    @csrf
                    @method('PUT')
                    <div class="mb-3">
                        <label class="form-label" for="current_password">{{ mawa_e('guardian.current_password') }}</label>
                        <input id="current_password" type="password" class="form-control" name="current_password" required autocomplete="current-password">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password">{{ mawa_e('guardian.new_password') }}</label>
                        <input id="password" type="password" class="form-control" name="password" required autocomplete="new-password">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password_confirmation">{{ mawa_e('guardian.confirm_password') }}</label>
                        <input id="password_confirmation" type="password" class="form-control" name="password_confirmation" required autocomplete="new-password">
                    </div>
                    <x-password-policy field="password" confirm-field="password_confirmation" />
                    <button class="btn btn-primary rounded-pill px-4 mt-2" type="submit">
                        <i class="bi bi-key me-1"></i>{{ mawa_e('guardian.update_password') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection