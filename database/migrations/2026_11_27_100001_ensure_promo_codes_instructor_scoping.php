<?php

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('promo_codes', 'created_by')) {
            Schema::table('promo_codes', function (Blueprint $table): void {
                $table->foreignId('created_by')->nullable()->after('is_active')->constrained('users')->restrictOnDelete();
            });
        }

        // Safely backfill any legacy promo codes missing created_by to super_admin_1
        $superAdmin1Id = DB::table('users')
            ->where('role', UserRole::SuperAdmin1->value)
            ->value('id');

        if ($superAdmin1Id) {
            DB::table('promo_codes')
                ->whereNull('created_by')
                ->update(['created_by' => $superAdmin1Id]);
        }

        // Add composite index on (created_by, is_active) if not already present
        $indexes = Schema::getIndexListing('promo_codes');
        if (! in_array('promo_codes_created_by_is_active_index', $indexes, true)) {
            Schema::table('promo_codes', function (Blueprint $table): void {
                $table->index(['created_by', 'is_active'], 'promo_codes_created_by_is_active_index');
            });
        }
    }

    public function down(): void
    {
        $indexes = Schema::getIndexListing('promo_codes');
        if (in_array('promo_codes_created_by_is_active_index', $indexes, true)) {
            Schema::table('promo_codes', function (Blueprint $table): void {
                $table->dropIndex('promo_codes_created_by_is_active_index');
            });
        }
    }
};
