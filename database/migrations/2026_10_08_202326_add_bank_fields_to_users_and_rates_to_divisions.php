<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('bank_name')->nullable()->after('phone');
            // Encrypted at the application layer (User::$casts), same as
            // employee_payroll_profiles.bank_account_number used to be.
            $table->text('bank_account_number')->nullable()->after('bank_name');
            $table->text('bank_account_holder')->nullable()->after('bank_account_number');
        });

        Schema::table('divisions', function (Blueprint $table) {
            $table->decimal('default_base_salary', 18, 2)->default(0)->after('is_active');
            $table->decimal('daily_attendance_allowance', 18, 2)->default(0)->after('default_base_salary');
        });

        // Bank destination moves from the payroll profile to the user
        // account itself — carry forward whatever was already on each
        // employee's most recent profile before the old columns go away.
        // The ciphertext is copied as-is (same APP_KEY, same `encrypted`
        // cast on both sides), never decrypted in PHP during the migration.
        $seenUserIds = [];
        DB::table('employee_payroll_profiles')
            ->orderByDesc('effective_from')
            ->select('user_id', 'bank_name', 'bank_account_number', 'bank_account_holder')
            ->whereNotNull('bank_account_number')
            ->orWhereNotNull('bank_name')
            ->get()
            ->each(function (object $profile) use (&$seenUserIds) {
                if (isset($seenUserIds[$profile->user_id])) {
                    return;
                }
                $seenUserIds[$profile->user_id] = true;

                DB::table('users')->where('id', $profile->user_id)->update([
                    'bank_name' => $profile->bank_name,
                    'bank_account_number' => $profile->bank_account_number,
                    'bank_account_holder' => $profile->bank_account_holder,
                ]);
            });

        Schema::table('employee_payroll_profiles', function (Blueprint $table) {
            $table->dropColumn(['bank_name', 'bank_account_number', 'bank_account_holder']);
        });
    }

    public function down(): void
    {
        Schema::table('employee_payroll_profiles', function (Blueprint $table) {
            $table->string('bank_name')->nullable();
            $table->text('bank_account_number')->nullable();
            $table->text('bank_account_holder')->nullable();
        });

        Schema::table('divisions', function (Blueprint $table) {
            $table->dropColumn(['default_base_salary', 'daily_attendance_allowance']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['bank_name', 'bank_account_number', 'bank_account_holder']);
        });
    }
};
