<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('branch_id')
                ->nullable()
                ->after('department_id')
                ->constrained('branches')
                ->nullOnDelete();

            $table->index(['company_id', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'branch_id']);
            $table->dropConstrainedForeignId('branch_id');
        });
    }
};
