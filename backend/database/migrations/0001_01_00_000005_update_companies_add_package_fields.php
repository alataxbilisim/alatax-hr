<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // Lisans paketi ilişkisi - sadece mevcut olmayan kolonları ekle
            if (!Schema::hasColumn('companies', 'license_package_id')) {
                $table->foreignId('license_package_id')->nullable()->after('status')->constrained('license_packages')->onDelete('set null');
            }
            
            // Ek limitler (paket dışı özelleştirme için)
            if (!Schema::hasColumn('companies', 'location_count')) {
                $table->integer('location_count')->default(1)->after('user_limit');
            }
            if (!Schema::hasColumn('companies', 'location_limit')) {
                $table->integer('location_limit')->default(1)->after('location_count');
            }
            if (!Schema::hasColumn('companies', 'employee_limit')) {
                $table->integer('employee_limit')->default(50)->after('employee_count');
            }
            
            // Cari hesap bakiyesi (cache - performans için)
            if (!Schema::hasColumn('companies', 'current_balance')) {
                $table->decimal('current_balance', 12, 2)->default(0)->after('employee_limit');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (Schema::hasColumn('companies', 'license_package_id')) {
                $table->dropForeign(['license_package_id']);
                $table->dropColumn('license_package_id');
            }
            if (Schema::hasColumn('companies', 'location_count')) {
                $table->dropColumn('location_count');
            }
            if (Schema::hasColumn('companies', 'location_limit')) {
                $table->dropColumn('location_limit');
            }
            if (Schema::hasColumn('companies', 'employee_limit')) {
                $table->dropColumn('employee_limit');
            }
            if (Schema::hasColumn('companies', 'current_balance')) {
                $table->dropColumn('current_balance');
            }
        });
    }
};

