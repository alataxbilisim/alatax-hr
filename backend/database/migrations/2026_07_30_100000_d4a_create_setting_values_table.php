<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * D4a — Settings Registry değer depolama.
 * system satırlarında company_id null; unique COALESCE ile (PG null ≠ null).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('setting_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('scope_type', 32); // system|company|branch|department|user
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->string('key', 191);
            $table->jsonb('value');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'key']);
            $table->index(['scope_type', 'scope_id']);
            $table->index(['company_id', 'scope_type', 'key']);
        });

        DB::statement('
            CREATE UNIQUE INDEX setting_values_scope_key_unique
            ON setting_values (
                COALESCE(company_id, 0),
                scope_type,
                COALESCE(scope_id, 0),
                key
            )
            WHERE deleted_at IS NULL
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('setting_values');
    }
};
