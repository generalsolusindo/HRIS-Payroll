<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_number_sequences', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->unsignedBigInteger('last_number')->default(0);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->string('employment_type', 40)->nullable();
        });
        $last = 0;
        foreach (DB::table('users')->whereNotNull('employee_number')->pluck('employee_number') as $number) {
            if (ctype_digit((string) $number)) {
                $last = max($last, (int) $number);
            }
        }
        DB::table('users')->where('role', 'karyawan')->orderBy('id')->each(function ($user) use (&$last) {
            if ($user->employee_number === null || trim($user->employee_number) === '') {
                DB::table('users')->where('id', $user->id)->update(['employee_number' => str_pad((string) ++$last, 4, '0', STR_PAD_LEFT)]);
            }
        });
        DB::table('employee_number_sequences')->insert(['id' => 1, 'last_number' => $last]);
        // Preserve the most recent known classification; unknown remains unset.
        $seen = [];
        foreach (DB::table('employee_payroll_profiles')->orderByDesc('effective_from')->orderByDesc('id')->get() as $profile) {
            if (! isset($seen[$profile->user_id])) {
                DB::table('users')->where('id', $profile->user_id)->update(['employment_type' => $profile->employment_type]);
                $seen[$profile->user_id] = true;
            }
        }
    }

    public function down(): void
    {
        // Keep issued employee numbers intact.
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('employment_type'));
        Schema::dropIfExists('employee_number_sequences');
    }
};
