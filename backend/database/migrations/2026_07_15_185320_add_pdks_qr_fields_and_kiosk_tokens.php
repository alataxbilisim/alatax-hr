<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PDKS-1: QR kiosk token tablosu + attendance kaynak/şube alanları.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->string('source', 32)->nullable()->after('clock_out_method');
            $table->foreignId('branch_id')->nullable()->after('company_id')
                ->constrained('branches')->nullOnDelete();
            $table->string('device_info', 255)->nullable()->after('clock_out_ip');
            $table->index(['company_id', 'source']);
            $table->index(['company_id', 'branch_id', 'date']);
        });

        Schema::create('attendance_kiosk_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->uuid('jti')->unique();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->foreignId('used_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['company_id', 'expires_at']);
            $table->index(['company_id', 'used_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_kiosk_tokens');

        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'source']);
            $table->dropIndex(['company_id', 'branch_id', 'date']);
            $table->dropConstrainedForeignId('branch_id');
            $table->dropColumn(['source', 'device_info']);
        });
    }
};
