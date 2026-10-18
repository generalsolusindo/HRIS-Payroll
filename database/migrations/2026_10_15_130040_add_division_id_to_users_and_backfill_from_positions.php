<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Stage 1 of retiring Position in favor of Division (they were
     * duplicate org-unit concepts — Position was a bare label shared
     * across companies via a pivot, Division is company-owned and
     * already carries salary defaults). This only adds the new column
     * and backfills it; position_id stays in place as a safety net
     * until the rest of the app is migrated over in a later stage.
     */
    public function up(): void
    {
        // SQLite doesn't wrap schema changes in a transaction (unlike
        // MySQL's DDL-is-non-transactional-anyway, or Postgres — see
        // Grammar::$transactions, false by default and never overridden
        // for SQLite), so if a later step in this same migration throws,
        // this ALTER TABLE has already committed and won't be rolled
        // back. Guarding it makes a retry after a prior partial failure
        // safe instead of erroring on "duplicate column".
        if (! Schema::hasColumn('users', 'division_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('division_id')->nullable()->after('position_id')
                    ->constrained()->nullOnDelete();
            });
        }

        foreach (DB::table('companies')->get() as $company) {
            $positionIds = DB::table('company_position')
                ->where('company_id', $company->id)
                ->pluck('position_id');

            $positions = DB::table('positions')->whereIn('id', $positionIds)->get();

            // Reuse a division that already has the same name instead of
            // creating a duplicate — some companies (General Solusindo,
            // in production) already have hand-created divisions whose
            // names happen to match an existing position.
            $divisions = DB::table('divisions')->where('company_id', $company->id)->get()->keyBy('id');
            $divisionIdByName = $divisions->mapWithKeys(fn ($d) => [Str::lower($d->name) => $d->id]);

            $divisionIdByPositionId = [];

            foreach ($positions as $position) {
                $key = Str::lower($position->name);

                if ($divisionIdByName->has($key)) {
                    $divisionIdByPositionId[$position->id] = $divisionIdByName[$key];

                    continue;
                }

                $newDivision = [
                    'company_id' => $company->id,
                    'name' => $position->name,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                // daily_attendance_allowance was dropped (replaced by a
                // flat company-wide rate) by a later migration — on a
                // database that has already moved past that point, this
                // column no longer exists at all.
                if (Schema::hasColumn('divisions', 'daily_attendance_allowance')) {
                    $newDivision['daily_attendance_allowance'] = 0;
                }

                // divisions.code is dropped later by the
                // 2026_10_15_212738 migration, but on a database that has
                // never run either migration before (a fresh install, or
                // one restoring from a pre-this-batch backup), this one
                // still runs while the column is NOT NULL with no
                // default — MySQL silently coerces a missing NOT NULL
                // string column to '', but SQLite enforces it strictly
                // and this insert fails outright. The value is never read
                // by anything (the column is dropped moments later in the
                // same deploy), so any unique placeholder is fine.
                if (Schema::hasColumn('divisions', 'code')) {
                    $newDivision['code'] = 'POS'.$position->id;
                }

                $divisionId = DB::table('divisions')->insertGetId($newDivision);

                $divisionIdByName[$key] = $divisionId;
                $divisionIdByPositionId[$position->id] = $divisionId;
            }

            DB::table('users')->where('company_id', $company->id)->orderBy('id')
                ->each(function ($user) use ($divisionIdByPositionId) {
                    if (isset($divisionIdByPositionId[$user->position_id])) {
                        DB::table('users')->where('id', $user->id)
                            ->update(['division_id' => $divisionIdByPositionId[$user->position_id]]);
                    }
                });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('division_id');
        });
    }
};
