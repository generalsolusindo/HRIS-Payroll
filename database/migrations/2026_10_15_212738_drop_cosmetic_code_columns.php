<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `code` on companies/divisions/payroll_periods was never actually
     * queried anywhere in the app (unlike PayrollComponent/PayrollPolicy's
     * codes, which real lookups depend on — those stay) — just a free-text
     * label an HRD had to invent and keep unique for no functional reason.
     * `name` takes over the per-company uniqueness guard where one existed.
     *
     * Each step is guarded so this migration is safe to re-run after a
     * partial failure (MySQL DDL isn't transactional, so a mid-migration
     * error can leave some ALTER TABLEs applied and others not).
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (Schema::hasColumn('companies', 'code')) {
                $table->dropUnique(['code']);
                $table->dropColumn('code');
            }
        });

        Schema::table('divisions', function (Blueprint $table) {
            if (Schema::hasColumn('divisions', 'code')) {
                // divisions.company_id's foreign key needs *some* index to
                // lean on — the (company_id, code) unique being dropped is
                // currently the only one covering it, so MySQL refuses to
                // drop it (error 1553) unless this replacement exists first.
                if (! $this->hasIndex('divisions', 'divisions_company_id_index')) {
                    $table->index('company_id');
                }
                $table->dropUnique(['company_id', 'code']);
                $table->dropColumn('code');
            }
            if (! $this->hasIndex('divisions', 'divisions_company_id_name_unique')) {
                $table->unique(['company_id', 'name']);
            }
        });

        Schema::table('payroll_periods', function (Blueprint $table) {
            if (Schema::hasColumn('payroll_periods', 'code')) {
                $table->dropUnique(['company_id', 'code']);
                $table->dropColumn('code');
            }
            if (! $this->hasIndex('payroll_periods', 'payroll_periods_company_id_name_unique')) {
                $table->unique(['company_id', 'name']);
            }
        });
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        return collect(Schema::getIndexes($table))->contains(fn ($index) => $index['name'] === $indexName);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_periods', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'name']);
            $table->string('code', 40)->nullable()->after('company_id');
        });

        Schema::table('divisions', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'name']);
            $table->string('code', 30)->nullable()->after('company_id');
            $table->dropIndex('divisions_company_id_index');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->string('code')->nullable()->after('name');
        });
    }
};
