<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // All nullable/optional — every field here is cosmetic for the
            // printed payslip letterhead, never required to run payroll.
            if (! Schema::hasColumn('companies', 'legal_name')) {
                $table->string('legal_name')->nullable()->after('name');
            }
            if (! Schema::hasColumn('companies', 'code')) {
                $table->string('code', 20)->nullable()->after('legal_name');
            }
            if (! Schema::hasColumn('companies', 'address')) {
                $table->text('address')->nullable()->after('code');
            }
            if (! Schema::hasColumn('companies', 'city')) {
                $table->string('city')->nullable()->after('address');
            }
            if (! Schema::hasColumn('companies', 'director_name')) {
                $table->string('director_name')->nullable()->after('city');
            }
            if (! Schema::hasColumn('companies', 'director_signature_path')) {
                $table->string('director_signature_path')->nullable()->after('director_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            foreach (['legal_name', 'code', 'address', 'city', 'director_name', 'director_signature_path'] as $column) {
                if (Schema::hasColumn('companies', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
