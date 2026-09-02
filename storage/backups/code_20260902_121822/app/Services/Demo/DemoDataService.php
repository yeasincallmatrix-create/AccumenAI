<?php

namespace App\Services\Demo;

use App\Models\Branch;
use App\Models\CrmContactType;
use App\Models\CustomerGroup;
use App\Models\Guardian;
use App\Models\HrEmployee;
use App\Models\Institute;
use App\Models\InstituteSetting;
use App\Models\InstituteUser;
use App\Models\Membership;
use App\Models\Party;
use App\Models\Role;
use App\Models\Student;
use App\Models\TeacherProfile;
use App\Models\User;
use App\Services\MembershipService;
use App\Services\UserAccountService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use App\Services\Auth\PasswordService;
use Illuminate\Support\Facades\DB;

class DemoDataService
{
    private const PASSWORD = 'DemoPass123!';

    private const MALE_FIRST = ['Mohammad', 'Rahman', 'Karim', 'Hasan', 'Ali', 'Ahmed', 'Hossain', 'Uddin', 'Islam', 'Miah', 'Sheikh', 'Chowdhury', 'Mondal', 'Biswas', 'Haque'];

    private const FEMALE_FIRST = ['Fatima', 'Ayesha', 'Nusrat', 'Jahan', 'Akhter', 'Begum', 'Khatun', 'Mahmuda', 'Nasrin', 'Tahsin', 'Ruma', 'Shirin', 'Sumaiya', 'Rubina', 'Halima'];

    private const LAST = ['Rahman', 'Hossain', 'Islam', 'Ahmed', 'Ali', 'Uddin', 'Khan', 'Chowdhury', 'Miah', 'Biswas', 'Mondal', 'Sheikh', 'Haque', 'Sarker', 'Das'];

    public function __construct(
        private UserAccountService $userAccountService,
        private MembershipService $membershipService,
    ) {}

    public function seed(Institute $institute, User $owner, array $options = []): array
    {
        if (app()->environment('production') && ! config('app.demo_seed_enabled', env('DEMO_SEED_ENABLED', false))) {
            return ['skipped' => true, 'message' => 'Demo seeding disabled in production (DEMO_SEED_ENABLED=false).'];
        }
        $force = $options['force'] ?? false;

        // Undistructive: auto-seeding disabled. Only manual force:true seeds.
        // Existing demo data (Ali Ali etc.) is kept until explicitly cleaned.
        if (! $force) {
            return ['skipped' => true, 'message' => 'Auto seeding disabled (undistructive mode). Use force:true to seed manually.'];
        }

        if (! $force && $this->hasDemoData($institute)) {
            return ['skipped' => true, 'message' => 'Demo data already exists.'];
        }

        if ($force && $this->hasDemoData($institute)) {
            $this->clearDemoData($institute);
        }

        $industry = $institute->industry ?? 'education';
        $subIndustry = $institute->sub_industry;

        $counts = ['students' => 0, 'teachers' => 0, 'staff' => 0, 'guardians' => 0, 'customers' => 0, 'suppliers' => 0, 'employees' => 0, 'contacts' => 0, 'items' => 0];

        DB::transaction(function () use ($institute, $owner, $industry, $subIndustry, &$counts, $force) {
            TenantContext::set($institute->id);

            // DATA SAFETY: Mark institute as explicit test if not already (demo seeding is explicit test creation)
            try {
                if (isset($institute->is_test) && ! $institute->is_test) {
                    $institute->forceFill(['is_test' => true])->save();
                } elseif (! isset($institute->is_test)) {
                    // column may not exist yet during migration phase — ignore
                }
                // Also ensure DB level flag
                \Illuminate\Support\Facades\DB::table('institutes')->where('id', $institute->id)->update(['is_test' => true]);
            } catch (\Throwable $e) {}
            // Ensure owner is also marked test if created via demo flow
            try {
                if ($owner && isset($owner->is_test) && ! $owner->is_test) {
                    $owner->forceFill(['is_test' => true])->save();
                    \Illuminate\Support\Facades\DB::table('users')->where('id', $owner->id)->update(['is_test' => true]);
                    \Illuminate\Support\Facades\DB::table('institution_user')->where('user_id', $owner->id)->where('institution_id', $institute->id)->update(['is_test' => true]);
                }
            } catch (\Throwable $e) {}

            $this->ensureOwnerMembership($owner, $institute);

            $staffRoles = $this->staffRolesForIndustry($industry);
            $staffUsers = $this->createStaffAccounts($institute, $staffRoles);
            $counts['staff'] = count($staffUsers);

            $teacherRoleSlug = match ($industry) {
                'education' => 'teacher',
                default => null,
            };
            if ($teacherRoleSlug) {
                // Demo teacher auto-creation disabled per requirement: new institutes start with 0 teachers
                $teacherCount = 0;
                for ($i = 1; $i <= $teacherCount; $i++) {
                    $this->createTeacher($institute, $i, null);
                }
                $counts['teachers'] = $teacherCount;
            }

            if ($industry === 'education') {
                // Demo student/guardian auto-creation disabled per requirement: new institutes start with 0 students
                $studentCount = 0;
                $guardianCount = 0;
                $guardians = $guardianCount > 0 ? $this->createDemoGuardians($institute, $guardianCount) : [];
                if ($studentCount > 0) {
                    $this->createDemoStudents($institute, $studentCount, null, $guardians);
                }
                $counts['students'] = $studentCount;
                $counts['guardians'] = $guardianCount;
            }

            $this->createDemoCustomers($institute, $industry, null);
            $counts['customers'] = Party::where('institute_id', $institute->id)->where('type', 'customer')->count();

            $needsSuppliers = in_array($industry, ['education', 'healthcare', 'retail', 'manufacturing', 'transport', 'restaurant', 'hotels']);
            if ($needsSuppliers) {
                $this->createDemoSuppliers($institute, $industry, null);
                $counts['suppliers'] = Party::where('institute_id', $institute->id)->where('type', 'supplier')->count();
            }

            $needsContacts = in_array($industry, ['healthcare', 'information_technology', 'finance', 'personal_finance']);
            if ($needsContacts) {
                $this->createDemoContacts($institute, null);
                $counts['contacts'] = $this->countDemoContacts($institute);
            }

            $this->createDemoEmployees($institute, $industry, null);
            $counts['employees'] = HrEmployee::where('institute_id', $institute->id)->count();

            $needsInventory = in_array($industry, ['education', 'healthcare', 'retail', 'manufacturing', 'real_estate', 'restaurant', 'hotels']);
            if ($needsInventory) {
                $this->createDemoInventoryItems($institute, $industry, null);
                $counts['items'] = $this->countDemoItems($institute);
            }

            $this->ensureInstituteSettings($institute);

            TenantContext::clear();
        });

        $counts['demo_owner_email'] = $owner->email;

        return $counts;
    }

    public function hasDemoData(Institute $institute): bool
    {
        return Party::where('institute_id', $institute->id)->exists()
            || Student::where('institute_id', $institute->id)->exists()
            || HrEmployee::where('institute_id', $institute->id)->exists();
    }

    private function clearDemoData(Institute $institute): void
    {
        DB::table('student_guardians')->where('institute_id', $institute->id)->delete();
        DB::table('students')->where('institute_id', $institute->id)->delete();
        DB::table('guardians')->where('institute_id', $institute->id)->delete();
        DB::table('teacher_profiles')->where('institute_id', $institute->id)->delete();
        DB::table('parties')->where('institute_id', $institute->id)->delete();
        DB::table('hr_employees')->where('institute_id', $institute->id)->delete();
        DB::table('crm_contacts')->where('institute_id', $institute->id)->delete();
        DB::table('inventory_items')->where('institute_id', $institute->id)->delete();
    }

    // --- Owner ---

    public function createOwnerAccount(Institute $institute, string $industry, ?string $subIndustry, string $password): User
    {
        if (app()->environment('production') && ! config('app.demo_seed_enabled', env('DEMO_SEED_ENABLED', false))) {
            throw new \RuntimeException('Demo owner creation blocked in production (DEMO_SEED_ENABLED=false).');
        }
        $email = $this->ownerEmail($industry, $subIndustry);

        $existing = User::where('email', $email)->first();
        if ($existing) {
            return $existing;
        }

        TenantContext::set($institute->id);

        $ownerName = ucwords(str_replace('_', ' ', $subIndustry ?? $industry)).' Owner';

        $user = $this->userAccountService->registerOwner([
            'name' => $ownerName,
            'first_name' => ucwords(str_replace('_', ' ', $subIndustry ?? $industry)),
            'last_name' => 'Owner',
            'email' => $email,
            'password_hash' => app(PasswordService::class)->hash($password),
            'email_verified_at' => now(),
            'status' => 'active',
            'is_test' => true,
        ]);

        $ownerRoleId = $this->roleSlugToId('institute-owner');
        if ($ownerRoleId) {
            $this->membershipService->assign($user, $institute->id, $ownerRoleId, [
                'branch_id' => null,
                'status' => 'active',
            ]);
        }

        InstituteUser::create([
            'institute_id' => $institute->id,
            'branch_id' => null,
            'role_id' => $ownerRoleId,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $this->uniquePhone($institute, $institute->id * 1000 + 1),
            'password_hash' => $user->password_hash,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        TenantContext::clear();

        return $user;
    }

    public function ownerEmail(string $industry, ?string $subIndustry): string
    {
        $slug = $subIndustry && $subIndustry !== '' ? $subIndustry : $industry;

        return str_replace(' ', '', ucwords(str_replace('_', ' ', $slug))).'@gmail.com';
    }

    // --- Owner Membership Guard ---

    private function ensureOwnerMembership(User $owner, Institute $institute): void
    {
        $ownerRoleId = $this->roleSlugToId('institute-owner');
        if (! $ownerRoleId) {
            return;
        }

        $hasMembership = Membership::where('user_id', $owner->id)
            ->where('institution_id', $institute->id)
            ->where('role_id', $ownerRoleId)
            ->exists();

        if (! $hasMembership) {
            $this->membershipService->assign($owner, $institute->id, $ownerRoleId, [
                'branch_id' => null,
                'status' => 'active',
            ]);
        }
    }

    // --- Staff ---

    public function createStaffAccounts(Institute $institute, array $staffRoles): array
    {
        $users = [];
        $industry = $institute->industry ?? 'education';
        $globalIndex = 100;

        foreach ($staffRoles as $roleSlug => $count) {
            for ($i = 1; $i <= $count; $i++) {
                $users[] = $this->createStaffUser($institute, $roleSlug, $globalIndex, $industry);
                $globalIndex++;
            }
        }

        return $users;
    }

    private function createStaffUser(Institute $institute, string $roleSlug, int $index, string $industry): User
    {
        $name = $this->deterministicName($index + 100);
        $email = $this->staffInstEmail($institute, $roleSlug, $index);

        $existing = User::where('email', $email)->first();
        if ($existing) {
            return $existing;
        }

        TenantContext::set($institute->id);

        $user = $this->userAccountService->createStaffFromInvitation([
            'name' => $name['first'].' '.$name['last'],
            'first_name' => $name['first'],
            'last_name' => $name['last'],
            'email' => $email,
            'password_hash' => app(PasswordService::class)->hash(self::PASSWORD),
            'email_verified_at' => now(),
            'status' => 'active',
            'is_test' => true,
        ]);
        // Ensure is_test flag at DB level even if fillable is blocked
        try { \Illuminate\Support\Facades\DB::table('users')->where('id', $user->id)->update(['is_test' => true]); } catch (\Throwable $e) {}

        $roleId = $this->roleSlugToId($roleSlug);
        if ($roleId) {
            $this->membershipService->assign($user, $institute->id, $roleId, [
                'branch_id' => null,
                'status' => 'active',
            ]);
            try { \Illuminate\Support\Facades\DB::table('institution_user')->where('user_id', $user->id)->where('institution_id', $institute->id)->update(['is_test' => true]); } catch (\Throwable $e) {}

            InstituteUser::create([
                'institute_id' => $institute->id,
                'branch_id' => null,
                'role_id' => $roleId,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'phone' => $this->uniquePhone($institute, $index + 100),
                'password_hash' => $user->password_hash,
                'status' => 'active',
                'email_verified_at' => now(),
            ]);
        }

        TenantContext::clear();

        return $user;
    }

    private function staffEmail(string $roleSlug, int $index, string $industry): string
    {
        return $roleSlug.$index.'@demo.local';
    }

    private function instEmail(Institute $institute, string $local): string
    {
        return $local.'-'.$institute->id.'@demo.local';
    }

    private function staffInstEmail(Institute $institute, string $roleSlug, int $index): string
    {
        return $this->instEmail($institute, $roleSlug.$index);
    }

    // --- Teachers (Education) ---

    public function createTeacher(Institute $institute, int $index, ?int $branchId = null): void
    {
        $name = $this->deterministicName($index + 50);
        $email = $this->instEmail($institute, 'teacher'.$index);

        $existing = User::where('email', $email)->first();
        if ($existing) {
            return;
        }

        TenantContext::set($institute->id);

        $user = $this->userAccountService->createStaffFromInvitation([
            'name' => $name['first'].' '.$name['last'],
            'first_name' => $name['first'],
            'last_name' => $name['last'],
            'email' => $email,
            'password_hash' => app(PasswordService::class)->hash(self::PASSWORD),
            'email_verified_at' => now(),
            'status' => 'active',
            'is_test' => true,
        ]);
        try { \Illuminate\Support\Facades\DB::table('users')->where('id', $user->id)->update(['is_test' => true]); } catch (\Throwable $e) {}

        $teacherRoleId = $this->roleSlugToId('teacher');
        if ($teacherRoleId) {
            $this->membershipService->assign($user, $institute->id, $teacherRoleId, [
                'branch_id' => $branchId,
                'status' => 'active',
            ]);
            try { \Illuminate\Support\Facades\DB::table('institution_user')->where('user_id', $user->id)->where('institution_id', $institute->id)->update(['is_test' => true]); } catch (\Throwable $e) {}

            $iu = InstituteUser::create([
                'institute_id' => $institute->id,
                'branch_id' => $branchId,
                'role_id' => $teacherRoleId,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'phone' => $this->uniquePhone($institute, $index + 50),
                'gender' => $index % 2 === 0 ? 'male' : 'female',
                'password_hash' => $user->password_hash,
                'status' => 'active',
                'email_verified_at' => now(),
            ]);

            TeacherProfile::create([
                'institute_id' => $institute->id,
                'institute_user_id' => $iu->id,
                'employment_status' => 'active',
                'employment_type' => 'full_time',
                'experience_years' => rand(1, 15),
                'date_of_birth' => $this->dob(rand(28, 55)),
                'skills' => ['Mathematics', 'Physics'],
                'languages' => ['Bangla', 'English'],
            ]);
        }

        TenantContext::clear();
    }

    // --- Guardians (Education) ---

    public function createDemoGuardians(Institute $institute, int $count, ?int $branchId = null): array
    {
        $guardians = [];
        TenantContext::set($institute->id);

        for ($i = 1; $i <= $count; $i++) {
            $name = $this->deterministicName($i + 200);

            $guardians[] = Guardian::create([
                'institute_id' => $institute->id,
                'name' => $name['first'].' '.$name['last'],
                'phone' => $this->uniquePhone($institute, $i + 200),
                'email' => $this->instEmail($institute, 'guardian'.$i),
                'password_hash' => app(PasswordService::class)->hash(self::PASSWORD),
                'status' => 'active',
                'email_verified_at' => now(),
                'preferred_language' => 'en',
            ]);
        }

        TenantContext::clear();

        return $guardians;
    }

    // --- Students (Education) ---

    public function createDemoStudents(Institute $institute, int $count, ?int $branchId = null, array $guardians = []): void
    {
        TenantContext::set($institute->id);

        for ($i = 1; $i <= $count; $i++) {
            $name = $this->deterministicName($i);
            $gender = $i % 2 === 0 ? 'male' : 'female';

            $student = Student::create([
                'institute_id' => $institute->id,
                'branch_id' => $branchId,
                'student_id_number' => $this->studentNumber($institute->id, $i),
                'full_name' => $name['first'].' '.$name['last'],
                'first_name' => $name['first'],
                'last_name' => $name['last'],
                'gender' => $gender,
                'dob' => $this->dob(rand(6, 18)),
                'phone' => $this->uniquePhone($institute, $i),
                'guardian_phone' => $this->uniquePhone($institute, $i + 300),
                'email' => $this->instEmail($institute, strtolower($name['first']).$i),
                'religion' => 'Islam',
                'nationality' => 'Bangladeshi',
                'admission_status' => Student::ADMISSION_STATUS_ENROLLED,
                'admission_date' => now()->subMonths(rand(1, 12)),
                'reg_no' => 'S'.str_pad((string) $institute->id, 4, '0', STR_PAD_LEFT).'-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'status' => Student::STATUS_ACTIVE,
            ]);

            if (isset($guardians[$i % count($guardians)])) {
                DB::table('student_guardians')->insert([
                    'institute_id' => $institute->id,
                    'student_id' => $student->id,
                    'guardian_id' => $guardians[$i % count($guardians)]->id,
                    'relationship' => $gender === 'male' ? 'father' : 'mother',
                    'is_primary' => $i <= 5,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        TenantContext::clear();
    }

    // --- Customers (Party) ---

    public function createDemoCustomers(Institute $institute, string $industry, ?int $branchId = null): void
    {
        TenantContext::set($institute->id);

        $count = 3;

        for ($i = 1; $i <= $count; $i++) {
            $name = $this->deterministicName($i + 300);

            Party::create([
                'institute_id' => $institute->id,
                'branch_id' => $branchId,
                'type' => 'customer',
                'name' => $name['first'].' '.$name['last'],
                'phone' => $this->uniquePhone($institute, $i + 300),
                'email' => $this->instEmail($institute, strtolower($name['first']).$i),
                'is_active' => true,
                'party_meta' => ['source' => 'demo', 'industry' => $industry],
            ]);
        }

        TenantContext::clear();
    }

    // --- Suppliers (Party) ---

    public function createDemoSuppliers(Institute $institute, string $industry, ?int $branchId = null): void
    {
        TenantContext::set($institute->id);

        $count = 2;

        for ($i = 1; $i <= $count; $i++) {
            $name = $this->deterministicName($i + 400);

            Party::create([
                'institute_id' => $institute->id,
                'branch_id' => $branchId,
                'type' => 'supplier',
                'name' => $name['first'].' '.$name['last'].' Supplies',
                'phone' => $this->uniquePhone($institute, $i + 400),
                'email' => $this->instEmail($institute, 'supplier'.$i),
                'is_active' => true,
                'party_meta' => ['source' => 'demo', 'industry' => $industry],
            ]);
        }

        TenantContext::clear();
    }

    // --- CRM Contacts (Healthcare) ---

    public function createDemoContacts(Institute $institute, ?int $branchId = null): void
    {
        TenantContext::set($institute->id);

        $contactType = CrmContactType::where('slug', 'patient')->first();
        if (! $contactType) {
            TenantContext::clear();

            return;
        }

        $count = 3;
        for ($i = 1; $i <= $count; $i++) {
            $name = $this->deterministicName($i + 500);

            DB::table('crm_contacts')->insert([
                'institute_id' => $institute->id,
                'branch_id' => $branchId,
                'contact_type_id' => $contactType->id,
                'first_name' => $name['first'],
                'last_name' => $name['last'],
                'email' => $this->instEmail($institute, strtolower($name['first']).$i),
                'phone' => $this->uniquePhone($institute, $i + 500),
                'is_customer' => true,
                'is_prospect' => false,
                'status' => 'active',
                'source_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        TenantContext::clear();
    }

    // --- HR Employees ---

    public function createDemoEmployees(Institute $institute, string $industry, ?int $branchId = null): void
    {
        TenantContext::set($institute->id);

        $employees = $this->employeesForIndustry($industry);

        $seq = 0;
        foreach ($employees as $roleLabel => $count) {
            for ($i = 1; $i <= $count; $i++) {
                $seq++;
                $name = $this->deterministicName($seq + 600);

                HrEmployee::create([
                    'institute_id' => $institute->id,
                    'branch_id' => $branchId,
                    'employee_code' => 'EMP-'.str_pad($institute->id, 3, '0', STR_PAD_LEFT).'-'.str_pad($seq, 5, '0', STR_PAD_LEFT),
                    'first_name' => $name['first'],
                    'last_name' => $name['last'],
                    'display_name' => $name['first'].' '.$name['last'],
                    'gender' => $seq % 2 === 0 ? 'male' : 'female',
                    'phone' => $this->uniquePhone($institute, $seq + 600),
                    'email' => $this->instEmail($institute, strtolower($name['first']).$seq),
                    'employment_status' => 'active',
                    'employment_type' => 'full_time',
                    'date_of_birth' => $this->dob(rand(22, 50)),
                    'joining_date' => now()->subMonths(rand(1, 24)),
                ]);
            }
        }

        TenantContext::clear();
    }

    // --- Inventory Items ---

    public function createDemoInventoryItems(Institute $institute, string $industry, ?int $branchId = null): void
    {
        TenantContext::set($institute->id);

        $items = match ($industry) {
            'education' => [
                ['name' => 'Notebook', 'sku' => 'EDU-NB-001', 'unit' => 'pcs', 'type' => 'consumable', 'price' => 50, 'cost' => 35, 'qty' => 200],
                ['name' => 'Pen', 'sku' => 'EDU-PN-001', 'unit' => 'pcs', 'type' => 'consumable', 'price' => 25, 'cost' => 15, 'qty' => 500],
                ['name' => 'Whiteboard', 'sku' => 'EDU-WB-001', 'unit' => 'pcs', 'type' => 'asset', 'price' => 3500, 'cost' => 2800, 'qty' => 10],
            ],
            'healthcare' => [
                ['name' => 'Paracetamol 500mg', 'sku' => 'MED-PCM-500', 'unit' => 'strip', 'type' => 'medicine', 'price' => 60, 'cost' => 40, 'qty' => 100],
                ['name' => 'Surgical Gloves', 'sku' => 'MED-SG-001', 'unit' => 'box', 'type' => 'consumable', 'price' => 350, 'cost' => 250, 'qty' => 50],
                ['name' => 'Stethoscope', 'sku' => 'MED-STH-001', 'unit' => 'pcs', 'type' => 'asset', 'price' => 2500, 'cost' => 1800, 'qty' => 5],
            ],
            'retail' => [
                ['name' => 'Rice 5kg', 'sku' => 'RTL-RC-5KG', 'unit' => 'bag', 'type' => 'stock_item', 'price' => 650, 'cost' => 550, 'qty' => 100],
                ['name' => 'Cooking Oil 1L', 'sku' => 'RTL-OIL-1L', 'unit' => 'bottle', 'type' => 'stock_item', 'price' => 180, 'cost' => 150, 'qty' => 200],
                ['name' => 'Sugar 1kg', 'sku' => 'RTL-SUG-1KG', 'unit' => 'kg', 'type' => 'stock_item', 'price' => 120, 'cost' => 100, 'qty' => 150],
            ],
            'manufacturing' => [
                ['name' => 'Cotton Fabric', 'sku' => 'MFG-COT-001', 'unit' => 'meter', 'type' => 'raw_material', 'price' => 200, 'cost' => 150, 'qty' => 500],
                ['name' => 'Button Pack', 'sku' => 'MFG-BTN-001', 'unit' => 'pack', 'type' => 'raw_material', 'price' => 80, 'cost' => 50, 'qty' => 300],
                ['name' => 'Thread Spool', 'sku' => 'MFG-THR-001', 'unit' => 'pcs', 'type' => 'raw_material', 'price' => 45, 'cost' => 30, 'qty' => 400],
            ],
            default => [
                ['name' => 'General Item A', 'sku' => 'GEN-A-001', 'unit' => 'pcs', 'type' => 'stock_item', 'price' => 100, 'cost' => 75, 'qty' => 100],
                ['name' => 'General Item B', 'sku' => 'GEN-B-001', 'unit' => 'pcs', 'type' => 'consumable', 'price' => 250, 'cost' => 180, 'qty' => 50],
            ],
        };

        foreach ($items as $item) {
            DB::table('inventory_items')->insert([
                'institute_id' => $institute->id,
                'branch_id' => $branchId,
                'sku' => $item['sku'],
                'name' => $item['name'],
                'unit' => $item['unit'],
                'item_type' => $item['type'],
                'selling_price' => $item['price'],
                'purchase_price' => $item['cost'],
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        TenantContext::clear();
    }

    // --- Helpers ---

    private function ensureInstituteSettings(Institute $institute): void
    {
        if (InstituteSetting::where('institute_id', $institute->id)->exists()) {
            return;
        }

        InstituteSetting::create([
            'institute_id' => $institute->id,
        ]);
    }

    private function countDemoContacts(Institute $institute): int
    {
        return (int) DB::table('crm_contacts')->where('institute_id', $institute->id)->count();
    }

    private function countDemoItems(Institute $institute): int
    {
        return (int) DB::table('inventory_items')->where('institute_id', $institute->id)->count();
    }

    private function roleSlugToId(string $slug): ?int
    {
        return (int) Role::where('slug', $slug)->value('id') ?: null;
    }

    private function staffRolesForIndustry(string $industry): array
    {
        return match ($industry) {
            'education' => ['accountant' => 1, 'receptionist' => 1],
            'healthcare' => ['accountant' => 1, 'receptionist' => 1],
            'retail' => ['accountant' => 1, 'receptionist' => 1],
            'manufacturing' => ['accountant' => 1, 'receptionist' => 1],
            default => ['accountant' => 1, 'receptionist' => 1],
        };
    }

    private function employeesForIndustry(string $industry): array
    {
        return match ($industry) {
            'education' => ['Teacher' => 0],
            'healthcare' => ['Doctor' => 1, 'Nurse' => 1],
            'retail' => ['Sales Associate' => 1, 'Store Manager' => 1],
            'manufacturing' => ['Supervisor' => 1, 'Operator' => 1],
            'real_estate' => ['Agent' => 1, 'Office Admin' => 1],
            'restaurant' => ['Chef' => 1, 'Waiter' => 1],
            'hotels' => ['Receptionist' => 1, 'Housekeeping' => 1],
            'information_technology' => ['Developer' => 1, 'Designer' => 1],
            'transport' => ['Driver' => 1, 'Coordinator' => 1],
            default => ['Staff' => 1, 'Manager' => 1],
        };
    }

    private function deterministicName(int $index): array
    {
        $firstIndex = ($index - 1) % count(self::MALE_FIRST);
        $lastIndex = ($index - 1) % count(self::LAST);

        return [
            'first' => self::MALE_FIRST[$firstIndex],
            'last' => self::LAST[$lastIndex],
        ];
    }

    private function phone(int $index): string
    {
        return '017'.str_pad((string) ($index % 10000000), 8, '0', STR_PAD_LEFT);
    }

    private function uniquePhone(Institute $institute, int $index): string
    {
        $base = ($institute->id * 10000 + $index) % 100000000;

        return '017'.str_pad((string) $base, 8, '0', STR_PAD_LEFT);
    }

    private function dob(int $age): string
    {
        return now()->subYears($age)->subDays(rand(0, 364))->format('Y-m-d');
    }

    private function studentNumber(int $instituteId, int $seq): string
    {
        return (string) ((int) '1'.str_pad((string) $instituteId, 3, '0', STR_PAD_LEFT).str_pad((string) $seq, 4, '0', STR_PAD_LEFT));
    }
}
