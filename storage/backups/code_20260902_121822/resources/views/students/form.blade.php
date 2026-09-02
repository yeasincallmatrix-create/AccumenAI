@section('title', ($student->exists ? mawa_lang('actions.edit') : mawa_lang('actions.add')) . ' ' . mawa_lang('students.student') . ' — AccumenAI')

@push('styles')
<style>
    /* ===== Add / Edit Student form design (matches demo/addstudent.html) ===== */
    .student-form-card {
        background: #fff;
        border: 1px solid #e3e8ef;
        border-radius: 10px;
        padding: 24px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, .06);
    }

    .section-title {
        font-size: 14px;
        font-weight: 700;
        color: #0d6efd;
        text-transform: uppercase;
        letter-spacing: .4px;
        margin: 24px 0 12px;
        padding-bottom: 6px;
        border-bottom: 1px solid #eef1f6;
    }
    .section-title:first-child {
        margin-top: 0;
    }

    .grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px;
    }
    .grid-basic {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    @media (min-width: 768px) {
        .grid {
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        }
        .grid-basic {
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        }
    }
    @media (min-width: 992px) {
        .grid-basic {
            grid-template-columns: repeat(6, 1fr);
        }
        .field-fill {
            grid-column: span 2;
        }
    }

    .field {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }
    .field label {
        font-size: 13px;
        font-weight: 500;
        color: #495057;
    }
    .field label .req {
        color: #dc3545;
    }

    .field input,
    .field select,
    .field textarea {
        padding: 9px 12px;
        border: 1px solid #ced4da;
        border-radius: 6px;
        font-size: 14px;
        width: 100%;
        background: #fff;
        transition: border-color .15s, box-shadow .15s;
    }
    .field input:focus,
    .field select:focus,
    .field textarea:focus {
        outline: none;
        border-color: #86b7fe;
        box-shadow: 0 0 0 3px rgba(13, 110, 253, .15);
    }
    .field input[readonly] {
        background: #e9ecef;
        color: #6c757d;
        cursor: not-allowed;
    }

    .hint {
        font-size: 12px;
        color: #6c757d;
    }
    .field .form-text {
        font-size: 12px;
    }

    .form-footer {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 24px;
        padding-top: 16px;
        border-top: 1px solid #eef1f6;
    }
    .btn-save {
        background: #0d6efd;
        color: #fff;
        border: none;
        padding: 9px 20px;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
    }
    .btn-save:hover {
        background: #0b5ed7;
    }

    .two-col {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px;
    }
</style>
@endpush

@section('content')
@php
    $genderNames = ['male' => mawa_lang('options.gender_male'), 'female' => mawa_lang('options.gender_female'), 'other' => mawa_lang('options.gender_other')];
    $statusNames = [mawa_lang('status.active'), mawa_lang('status.completed'), mawa_lang('status.dropped'), mawa_lang('status.suspended')];
    $isProfessional = \App\Support\InstituteDomain::isProfessional($institute ?? null);
@endphp
<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">{{ $student->exists ? ($isProfessional ? 'Edit Trainee' : mawa_lang('students.edit')) : ($isProfessional ? 'Add Trainee' : mawa_lang('students.add_new')) }}</h4>
        <p class="page-header-desc">{{ $student->exists ? ($isProfessional ? 'Update trainee details for '.$student->full_name : mawa_lang('students.edit_desc', ['name' => $student->full_name])) : ($isProfessional ? 'Register a new trainee' : mawa_lang('students.add_desc')) }}</p>
    </div>
    <div class="page-header-actions d-flex gap-2">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#scanModal">
            <i class="bi bi-scanner"></i> Scan Document
        </button>
        <a class="btn btn-outline-secondary" href="{{ route('students.index') }}">
            <i class="bi bi-arrow-left"></i> {{ mawa_e('actions.back') }}
        </a>
    </div>
</div>

<!-- Scan Document Modal -->
<div class="modal fade" id="scanModal" tabindex="-1" aria-labelledby="scanModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="scanModalLabel"><i class="bi bi-scanner me-2"></i>Scan Document — Auto-fill</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-info small mb-3">
          <i class="bi bi-info-circle me-1"></i> Upload a clear photo or PDF of NID / Passport / Birth Certificate. Supported: JPEG, PNG, JPG, PDF, WEBP (max 20MB).
        </div>
        <div class="mb-3">
          <label for="scanDocument" class="form-label">Document (image/pdf)</label>
          <input class="form-control" type="file" id="scanDocument" accept=".jpeg,.jpg,.png,.pdf,.webp">
          <div class="form-text">Tip: Ensure text is readable. For Bengali documents, both Eng/Ben are attempted.</div>
        </div>
        <div id="scanStatus" class="small text-muted" style="min-height:1.2em;"></div>
        <div id="scanRawPreview" class="mt-2 small bg-light border rounded p-2 d-none" style="max-height:120px; overflow:auto; white-space:pre-wrap;"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="scanUploadBtn">
          <span class="scan-btn-text"><i class="bi bi-cloud-upload me-1"></i> Upload &amp; Scan</span>
          <span class="scan-btn-spinner d-none"><span class="spinner-border spinner-border-sm me-1"></span> Scanning…</span>
        </button>
      </div>
    </div>
  </div>
</div>

<form method="POST" action="{{ $student->exists ? route('students.update', $student) : route('students.store') }}" enctype="multipart/form-data">
    @csrf
    @if($student->exists)
        @method('PUT')
    @endif

    <div class="student-form-card">

        <!-- ============ 1. BASIC INFORMATION ============ -->
        <h3 class="section-title">1. Basic Information</h3>
        <div class="grid grid-basic">
            <div class="field">
                <label for="first_name">First Name <span class="req">*</span></label>
                <input id="first_name" name="first_name" value="{{ old('first_name', $student->first_name) }}" required maxlength="60" placeholder="e.g. Rahima">
                @error('first_name') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label for="last_name">Last Name</label>
                <input id="last_name" name="last_name" value="{{ old('last_name', $student->last_name) }}" maxlength="60" placeholder="e.g. Akter">
                @error('last_name') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label for="student_id_number">{{ $isProfessional ? 'Trainee ID' : 'Student ID' }}</label>
                @if($student->exists)
                    <input id="student_id_number" value="{{ $student->student_id }}" readonly>
                    <div class="hint">{{ $isProfessional ? 'Trainee numbers are assigned on creation and cannot be changed.' : 'Student numbers are assigned on creation and cannot be changed.' }}</div>
                @else
                    <input id="student_id_number" value="{{ $nextNumber }}" readonly>
                    <div class="hint">Auto-generated: {{ $nextNumber }}</div>
                @endif
            </div>
            <div class="field">
                <label for="roll_number">Roll Number</label>
                <input id="roll_number" name="roll_number" value="{{ old('roll_number', $student->enrollments()->latest('id')->first()?->roll_no ?? $student->roll_number) }}" maxlength="20" placeholder="e.g. R-1042">
                @error('roll_number') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label for="reg_no">Reg No.</label>
                <input id="reg_no" name="reg_no" value="{{ old('reg_no', $student->reg_no) }}" maxlength="10" placeholder="Auto-generated" readonly>
                <div class="hint">Auto-generated on creation. Can be edited later.</div>
                @error('reg_no') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label for="gender">Gender</label>
                <select id="gender" name="gender">
                    <option value="">-- Select --</option>
                    @foreach(['male', 'female', 'other'] as $g)
                        <option value="{{ $g }}" @selected(old('gender', $student->gender) == $g)>{{ $genderNames[$g] ?? ucfirst($g) }}</option>
                    @endforeach
                </select>
                @error('gender') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label for="dob">Date of Birth</label>
                <input id="dob" type="date" name="dob" value="{{ old('dob', $student->dob?->format('Y-m-d')) }}">
                <div class="hint">Used for age calculation &amp; reports.</div>
                @error('dob') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label for="admission_date">Admission Date <span class="req">*</span></label>
                <input id="admission_date" type="date" name="admission_date" value="{{ old('admission_date', $student->admission_date?->format('Y-m-d')) }}" required>
                <div class="hint">Date the student was admitted.</div>
                @error('admission_date') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label for="status">Status</label>
                <select id="status" name="status" required>
                    @foreach(['active', 'completed', 'dropped', 'suspended'] as $st)
                        <option value="{{ $st }}" @selected(old('status', $student->status) == $st)>{{ $statusNames[$st] ?? ucfirst($st) }}</option>
                    @endforeach
                </select>
                <div class="hint">"Completed" is only allowed once a course is finished.</div>
                @error('status') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label for="phone">Phone</label>
                @include('partials.phone', ['name' => 'phone', 'id' => 'phone', 'value' => old('phone', $student->phone), 'country' => $country ?? null])
                @error('phone') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="field field-fill">
                <label for="email">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email', $student->email) }}" maxlength="150" placeholder="student@example.com">
                @error('email') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="field field-fill">
                <label for="country">Country</label>
                <select id="country" name="country">
                    <option value="">-- Select --</option>
                    @foreach ($countries ?? [] as $countryCode => $countryName)
                        <option value="{{ $countryName }}" @selected(old('country', $student->country ?? $country) == $countryName)>{{ $countryName }}</option>
                    @endforeach
                </select>
                <div class="hint">Defaults to your institution's country.</div>
                @error('country') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
        </div>

        <!-- ============ 2. GUARDIAN INFORMATION ============ -->
        <h3 class="section-title">2. Guardian Information</h3>
        <div class="grid">
            <div class="field">
                <label for="father_name">Father's Name</label>
                <input id="father_name" name="father_name" value="{{ old('father_name', $student->father_name) }}" placeholder="e.g. Md. Karim Uddin">
                @error('father_name') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label for="mother_name">Mother's Name</label>
                <input id="mother_name" name="mother_name" value="{{ old('mother_name', $student->mother_name) }}" placeholder="e.g. Salma Begum">
                @error('mother_name') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label for="guardian_phone">Guardian Phone</label>
                @include('partials.phone', ['name' => 'guardian_phone', 'id' => 'guardian_phone', 'value' => old('guardian_phone', $student->guardian_phone), 'country' => $country ?? null])
                @error('guardian_phone') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label for="nationality">Nationality</label>
                @php
                    $nationalityDefault = old('nationality', $student->nationality) ?: ($country ?? 'Bangladesh');
                @endphp
                <select id="nationality" name="nationality">
                    <option value="">-- Select --</option>
                    @foreach(\App\Support\CountryCodes::all() as $countryName => $dial)
                        <option value="{{ $countryName }}" @selected($nationalityDefault === $countryName)>{{ $countryName }}</option>
                    @endforeach
                </select>
                <div class="hint">Defaults to your institute's country.</div>
                @error('nationality') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
        </div>

        <!-- ============ 3. DOCUMENTS ============ -->
        <h3 class="section-title">3. Documents</h3>
        <div class="grid">
            <div class="field">
                <label for="nid_number">NID Number</label>
                <input id="nid_number" name="nid_number" value="{{ old('nid_number', $student->nid_number) }}" maxlength="30" placeholder="e.g. 1234567890">
                @error('nid_number') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label for="birth_cert_number">Birth Certificate No.</label>
                <input id="birth_cert_number" name="birth_cert_number" value="{{ old('birth_cert_number', $student->birth_cert_number) }}" maxlength="30" placeholder="e.g. 9876543210">
                @error('birth_cert_number') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label for="passport_number">Passport Number</label>
                <input id="passport_number" name="passport_number" value="{{ old('passport_number', $student->passport_number) }}" maxlength="40" placeholder="e.g. AB1234567">
                @error('passport_number') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label for="blood_group">Blood Group</label>
                <select id="blood_group" name="blood_group">
                    <option value="">-- Select --</option>
                    @foreach(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'Unknown'] as $bg)
                        <option value="{{ $bg }}" @selected(old('blood_group', $student->blood_group) == $bg)>{{ $bg }}</option>
                    @endforeach
                </select>
                @error('blood_group') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label for="religion">Religion</label>
                <select id="religion" name="religion">
                    <option value="">-- Select --</option>
                    @foreach(['Islam', 'Hindu', 'Buddhist', 'Christian', 'Other'] as $rel)
                        <option value="{{ $rel }}" @selected(old('religion', $student->religion) == $rel)>{{ $rel }}</option>
                    @endforeach
                </select>
                @error('religion') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="field">
<label for="photo">Profile Picture</label>
                <input id="photo" type="file" name="photo" accept=".jpg,.jpeg,.png,.webp" onchange="openPhotoCropper(this)">
                <div class="hint">Passport-size portrait photo recommended. Ratio 7:9 · 350 × 450 px · below 50 KB (max 100 KB) · JPG, PNG or WebP</div>
                @if ($student->exists && $student->photo)
                    <img src="{{ $student->photo_url }}" class="student-id-photo mt-2" data-photo-preview alt="{{ $student->full_name }}">
                @else
                    <img src="" class="student-id-photo mt-2 d-none" data-photo-preview alt="">
                @endif
                @error('photo') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label for="document">Documents</label>
                <input id="document" type="file" name="document" accept=".pdf,.csv,.svg">
                <div class="hint">Document: PDF, CSV, SVG (max 10MB)</div>
                @if($student->exists && $student->document)
                    <a class="d-inline-flex align-items-center gap-1 mt-2" href="{{ asset('storage/' . $student->document) }}" target="_blank" rel="noopener">
                        <i class="bi bi-file-earmark-text"></i> {{ basename($student->document) }}

                    </a>
                @endif
                @error('document') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
        </div>

        <!-- ============ 4. ADDRESS ============ -->
        <h3 class="section-title">4. Address</h3>

        <div class="address-box">
            <div class="box-title">Present Address</div>
            <x-address :prefix="'present_'"
                       :country-id="old('present_country_id', $student->present_country_id ?? $defaultCountryId ?? null)"
                       :level-1-id="old('present_admin_1_id', $student->present_admin_1_id)"
                       :level-2-id="old('present_admin_2_id', $student->present_admin_2_id)"
                       :level-3-id="old('present_admin_3_id', $student->present_admin_3_id)"
                       :level-labels="$presentAddress['level_labels']"
                       :level-1-options="$presentAddress['level_options'][1]"
                       :level-2-options="$presentAddress['level_options'][2]"
                       :level-3-options="$presentAddress['level_options'][3]"
                       :postal-code="old('present_zip_code', $student->present_zip_code)"
                       :address="old('present_address', $student->present_address)" />
        </div>

        <div class="address-box">
            <div class="box-title">
                Permanent Address
                <label class="check-row">
                    <input type="checkbox" id="same_as_present" name="same_as_present" value="1">
                    Same as present address
                </label>
            </div>
            <x-address :prefix="'permanent_'"
                       :country-id="old('permanent_country_id', $student->permanent_country_id ?? $defaultCountryId ?? null)"
                       :level-1-id="old('permanent_admin_1_id', $student->permanent_admin_1_id)"
                       :level-2-id="old('permanent_admin_2_id', $student->permanent_admin_2_id)"
                       :level-3-id="old('permanent_admin_3_id', $student->permanent_admin_3_id)"
                       :level-labels="$permanentAddress['level_labels']"
                       :level-1-options="$permanentAddress['level_options'][1]"
                       :level-2-options="$permanentAddress['level_options'][2]"
                       :level-3-options="$permanentAddress['level_options'][3]"
                       :postal-code="old('permanent_zip_code', $student->permanent_zip_code)"
                       :address="old('permanent_address', $student->permanent_address)" />
        </div>

        <!-- ============ 5. EMERGENCY CONTACT ============ -->
        <h3 class="section-title">5. Emergency Contact</h3>
        <div class="two-col">
            <div class="field">
                <label for="emergency_contact_name">Emergency Contact Name</label>
                <input id="emergency_contact_name" name="emergency_contact_name" value="{{ old('emergency_contact_name', $student->emergency_contact_name) }}" maxlength="120" placeholder="e.g. Abdul Kalam">
                @error('emergency_contact_name') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label for="emergency_contact_phone">Emergency Contact Phone</label>
                @include('partials.phone', ['name' => 'emergency_contact_phone', 'id' => 'emergency_contact_phone', 'value' => old('emergency_contact_phone', $student->emergency_contact_phone), 'country' => $country ?? null])
                @error('emergency_contact_phone') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="form-footer">
            <a class="btn btn-outline-secondary" href="{{ route('students.index') }}">Cancel</a>
            <button type="submit" class="btn-save">
                <i class="bi bi-check-lg"></i> {{ $student->exists ? 'Save changes' : ($isProfessional ? 'Save Trainee' : 'Save Student') }}

            </button>
        </div>
    </div>
</form>

@include('components.photo-crop-modal')
@endsection

@push('scripts')
<script src="{{ asset('js/photo-crop.js') }}?v={{ \Illuminate\Support\Facades\File::lastModified(public_path('js/photo-crop.js')) }}"></script>
<script>
(function () {
    var checkbox = document.getElementById('same_as_present');
    if (!checkbox) { return; }
    checkbox.addEventListener('change', function () {
        if (!this.checked) { return; }
        var presentRoot = document.querySelector('[data-address-component][data-prefix="present_"]');
        var permRoot = document.querySelector('[data-address-component][data-prefix="permanent_"]');
        if (!presentRoot || !permRoot) { return; }
        var copySelect = function (fromName, toName) {
            var src = presentRoot.querySelector('[name="' + fromName + '"]');
            var dst = permRoot.querySelector('[name="' + toName + '"]');
            if (src && dst) {
                dst.innerHTML = src.innerHTML;
                dst.value = src.value;
            }
        };
        var copyInput = function (fromName, toName) {
            var src = presentRoot.querySelector('[name="' + fromName + '"]');
            var dst = permRoot.querySelector('[name="' + toName + '"]');
            if (src && dst) { dst.value = src.value; }
        };
        copySelect('present_country_id', 'permanent_country_id');
        copySelect('present_admin_1_id', 'permanent_admin_1_id');
        copySelect('present_admin_2_id', 'permanent_admin_2_id');
        copySelect('present_admin_3_id', 'permanent_admin_3_id');
        copyInput('present_zip_code', 'permanent_zip_code');
        copyInput('present_address', 'permanent_address');
        if (permRoot.refresh) { permRoot.refresh(); }
    });
})();
</script>
<script>
(function(){
    var btn = document.getElementById('scanUploadBtn');
    var input = document.getElementById('scanDocument');
    var status = document.getElementById('scanStatus');
    var preview = document.getElementById('scanRawPreview');
    if(!btn || !input) return;
    function setLoading(loading){
        var txt = btn.querySelector('.scan-btn-text');
        var sp = btn.querySelector('.scan-btn-spinner');
        if(txt) txt.classList.toggle('d-none', loading);
        if(sp) sp.classList.toggle('d-none', !loading);
        btn.disabled = loading;
    }
    function setField(name, value){
        if(!value) return false;
        var el = document.querySelector('[name="'+name+'"]');
        if(!el) return false;
        el.value = value;
        el.dispatchEvent(new Event('input', {bubbles:true}));
        el.dispatchEvent(new Event('change', {bubbles:true}));
        // highlight
        el.style.borderColor = '#86b7fe';
        el.style.boxShadow = '0 0 0 3px rgba(13,110,253,.15)';
        setTimeout(function(){ el.style.borderColor=''; el.style.boxShadow=''; }, 2000);
        return true;
    }
    function setPhoneField(names, value){
        if(!value) return;
        names.forEach(function(n){
            var el = document.querySelector('[name="'+n+'"]');
            if(el){
                // Handle phone partials that may use intl-tel-input
                el.value = value;
                el.dispatchEvent(new Event('input',{bubbles:true}));
                el.dispatchEvent(new Event('change',{bubbles:true}));
            }
        });
    }
    btn.addEventListener('click', function(){
        var file = input.files[0];
        if(!file){
            status.textContent = 'Please select a file first.';
            status.className = 'small text-danger';
            return;
        }
        // Frontend check matches backend max:20MB (config services.ocr.max_file_size)
        if(file.size > 20*1024*1024){
            status.textContent = 'File too large (max 20MB).';
            status.className = 'small text-danger';
            return;
        }
        var allowed = ['image/jpeg','image/png','image/jpg','image/webp','application/pdf'];
        // allow via extension fallback (mime may be empty for some)
        var ext = file.name.split('.').pop().toLowerCase();
        if(['jpeg','jpg','png','pdf','webp'].indexOf(ext)===-1 && allowed.indexOf(file.type)===-1){
            status.textContent = 'Invalid file type. Use JPEG, PNG, JPG, PDF or WEBP.';
            status.className = 'small text-danger';
            return;
        }
        var fd = new FormData();
        fd.append('document', file);
        fd.append('_token', document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}');
        setLoading(true);
        status.textContent = 'Uploading and scanning...';
        status.className = 'small text-muted';
        if(preview) { preview.classList.add('d-none'); preview.textContent=''; }

        fetch('{{ route('document.scan') }}', {
            method: 'POST',
            body: fd,
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}', 'Accept': 'application/json' }
        })
        .then(function(res){
            if(!res.ok){
                return res.json().then(function(j){ throw new Error(j.message || 'Scan failed ('+res.status+')'); });
            }
            return res.json();
        })
        .then(function(json){
            var d = json.data || {};
            var filled = 0;
            if(setField('first_name', d.first_name)) filled++;
            if(setField('last_name', d.last_name)) filled++;
            if(d.dob || d.date_of_birth){
                if(setField('dob', d.dob || d.date_of_birth)) filled++;
            }
            if(setField('email', d.email)) filled++;
            if(d.phone){
                setPhoneField(['phone','guardian_phone','emergency_contact_phone'], d.phone);
                filled++;
            }
            if(setField('nid_number', d.nid_number)) filled++;
            if(setField('father_name', d.father_name)) filled++;
            if(setField('mother_name', d.mother_name)) filled++;
            if(setField('blood_group', d.blood_group)) filled++;
            // Address -> present_address textual field
            if(d.address){
                var addrFields = ['present_address','permanent_address'];
                var anyAddr=false;
                addrFields.forEach(function(n){
                    var el=document.querySelector('[name="'+n+'"]');
                    if(el && !el.value){
                        el.value=d.address;
                        el.dispatchEvent(new Event('change',{bubbles:true}));
                        anyAddr=true;
                    }
                });
                if(anyAddr) filled++;
            }
            // Show raw preview if available
            if(preview && d.raw_text){
                preview.textContent = d.raw_text.substring(0,1200);
                preview.classList.remove('d-none');
            }
            if(d._meta && d._meta.raw_text_preview && preview){
                preview.textContent = d._meta.raw_text_preview;
                preview.classList.remove('d-none');
            }
            if(filled>0){
                status.textContent = 'Auto-filled '+filled+' field(s). Please verify and save.';
                status.className = 'small text-success';
                // Close modal after short delay if at least one field filled
                setTimeout(function(){
                    var modalEl = document.getElementById('scanModal');
                    if(modalEl && window.bootstrap){
                        var m = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
                        m.hide();
                    }
                }, 1400);
            } else {
                status.textContent = (json.message || 'No fields detected.') + ' You can still copy from raw text below.';
                status.className = 'small text-warning';
            }
        })
        .catch(function(err){
            status.textContent = err.message || 'Scan failed. Try a clearer image.';
            status.className = 'small text-danger';
            if(preview) preview.classList.add('d-none');
        })
        .finally(function(){ setLoading(false); });
    });
    // Reset status when file changes
    input.addEventListener('change', function(){
        status.textContent = '';
        status.className = 'small text-muted';
        if(preview){ preview.classList.add('d-none'); preview.textContent=''; }
    });
})();
</script>
@endpush
@extends('layouts.institute')