<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
    public function up(): void {
        $now = now();
        // Users: trusted demo/development accounts — only where active and unverified
        // Canonical list from inspect_demo.php (users table)
        $trustedUserEmails = [
            "admin@mawa.com",
            "Institution@gmail.com",
            "accountant100-38@demo.local",
            "receptionist101-38@demo.local",
            "teacher1-38@demo.local",
            "teacher2-38@demo.local",
        ];
        DB::table("users")
            ->whereIn("email", $trustedUserEmails)
            ->where("status","active")
            ->whereNull("email_verified_at")
            ->whereNull("deleted_at")
            ->update(["email_verified_at"=>$now]);

        // Also cover any other active demo users matching @demo.local that may have been created via demo:seed with different institute ids
        DB::table("users")
            ->where("status","active")
            ->whereNull("email_verified_at")
            ->whereNull("deleted_at")
            ->where("email","like","%@demo.local")
            ->update(["email_verified_at"=>$now]);

        // Platform admin trusted local dev
        DB::table("platform_admins")
            ->where("email","admin@mawa.com")
            ->where("status","active")
            ->whereNull("email_verified_at")
            ->update(["email_verified_at"=>$now]);

        // Institute users: trusted demo institute users (active, unverified, demo pattern)
        DB::table("institute_users")
            ->where("status","active")
            ->whereNull("email_verified_at")
            ->whereNull("deleted_at")
            ->where(function($q){
                $q->where("email","like","%@demo.local")
                  ->orWhere("email","like","%@institution.demo")
                  ->orWhere("email","like","%@demo.test")
                  ->orWhereIn("email", ["admin@mawa.com","Institution@gmail.com"]);
            })
            ->update(["email_verified_at"=>$now]);
    }
    public function down(): void {
        // No down — verification is non-destructive; revert would re-nullify but we keep as is for safety
    }
};
