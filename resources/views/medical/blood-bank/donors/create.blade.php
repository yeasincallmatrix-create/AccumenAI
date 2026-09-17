@extends('layouts.institute')

@section('title', 'Register Blood Donor — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="mb-0"><i class="bi bi-person-plus"></i> Register Blood Donor</h4>
        <a href="{{ route('medical.blood-bank.donors.index') }}" class="btn btn-outline-secondary btn-sm">Back to List</a>
    </div>

    <form method="POST" action="{{ route('medical.blood-bank.donors.store') }}">
        @csrf

        <div class="row g-3">
            <div class="col-md-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <i class="bi bi-person-heart"></i> Donor # {{ $donorNumber }}
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header"><i class="bi bi-person"></i> Personal Information</div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label">First Name *</label>
                                <input type="text" name="first_name" class="form-control @error('first_name') is-invalid @enderror" value="{{ old('first_name') }}" required>
                                @error('first_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Last Name</label>
                                <input type="text" name="last_name" class="form-control @error('last_name') is-invalid @enderror" value="{{ old('last_name') }}">
                                @error('last_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone</label>
                                <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone') }}">
                                @error('phone')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}">
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Date of Birth</label>
                                <x-tdate-input name="date_of_birth" :value="old('date_of_birth')" />
                                @error('date_of_birth')
                                    <div class="text-danger small">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Gender *</label>
                                <select name="gender" class="form-select @error('gender') is-invalid @enderror" required>
                                    <option value="">Select Gender</option>
                                    @foreach($genders as $key => $label)
                                        <option value="{{ $key }}" @selected(old('gender') === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('gender')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header"><i class="bi bi-droplet"></i> Medical Details</div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label">Blood Group *</label>
                                <select name="blood_group" class="form-select @error('blood_group') is-invalid @enderror" required>
                                    <option value="">Select Blood Group</option>
                                    @foreach($bloodGroups as $key => $label)
                                        <option value="{{ $key }}" @selected(old('blood_group') === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('blood_group')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Weight (kg)</label>
                                <input type="number" name="weight_kg" class="form-control @error('weight_kg') is-invalid @enderror" value="{{ old('weight_kg') }}" min="30" step="0.1">
                                @error('weight_kg')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Hemoglobin (g/dL)</label>
                                <input type="number" name="hemoglobin" class="form-control @error('hemoglobin') is-invalid @enderror" value="{{ old('hemoglobin') }}" min="0" max="20" step="0.1">
                                @error('hemoglobin')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <div class="form-check mt-4">
                                    <input type="checkbox" name="is_eligible" value="1" class="form-check-input" id="is_eligible" @checked(old('is_eligible', true))>
                                    <label class="form-check-label" for="is_eligible">Eligible for Donation</label>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Medical History</label>
                                <textarea name="medical_history" class="form-control @error('medical_history') is-invalid @enderror" rows="3" placeholder="Any relevant medical history...">{{ old('medical_history') }}</textarea>
                                @error('medical_history')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Notes</label>
                                <textarea name="notes" class="form-control @error('notes') is-invalid @enderror" rows="2">{{ old('notes') }}</textarea>
                                @error('notes')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-check-lg"></i> Register Donor
                </button>
                <a href="{{ route('medical.blood-bank.donors.index') }}" class="btn btn-outline-secondary btn-lg ms-2">Cancel</a>
            </div>
        </div>
    </form>
</div>
@endsection
