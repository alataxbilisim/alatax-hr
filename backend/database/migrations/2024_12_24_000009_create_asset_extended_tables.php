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
        // Yazılım Lisansları
        Schema::create('software_licenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');

            $table->string('name'); // Microsoft 365, Adobe CC
            $table->string('vendor'); // Microsoft, Adobe
            $table->string('version')->nullable();

            $table->enum('license_type', [
                'perpetual',      // Kalıcı
                'subscription',   // Abonelik
                'per_seat',       // Kullanıcı başı
                'concurrent',     // Eşzamanlı kullanıcı
                'site',           // Site lisansı
                'open_source',     // Açık kaynak
            ])->default('subscription');

            $table->integer('total_seats')->nullable();
            $table->integer('used_seats')->default(0);

            $table->date('purchase_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->decimal('purchase_cost', 15, 2)->nullable();
            $table->decimal('annual_cost', 15, 2)->nullable();
            $table->string('currency', 3)->default('TRY');

            $table->string('license_key')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);

            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'expiry_date']);
        });

        // Lisans Atamaları
        Schema::create('software_license_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('software_license_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            $table->date('assigned_at');
            $table->date('revoked_at')->nullable();
            $table->boolean('is_active')->default(true);

            $table->foreignId('assigned_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index(['software_license_id', 'is_active']);
        });

        // Varlık Talepleri
        Schema::create('asset_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('asset_category_id')->nullable()->constrained()->onDelete('set null');

            $table->string('item_name');
            $table->text('description')->nullable();
            $table->text('justification'); // Neden gerekli
            $table->enum('urgency', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->date('needed_by')->nullable();

            $table->enum('status', ['pending', 'approved', 'rejected', 'fulfilled', 'cancelled'])->default('pending');
            $table->text('approval_notes')->nullable();

            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->datetime('approved_at')->nullable();
            $table->foreignId('fulfilled_with_asset_id')->nullable()->constrained('assets')->onDelete('set null');

            // Workflow entegrasyonu
            $table->foreignId('approval_workflow_id')->nullable()->constrained()->onDelete('set null');
            $table->integer('current_step')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
        });

        // Assets tablosuna yeni alanlar ekle (Amortisman vb.)
        Schema::table('assets', function (Blueprint $table) {
            $table->enum('depreciation_method', ['none', 'straight_line', 'declining_balance'])->default('none')->after('warranty_end_date');
            $table->integer('useful_life_years')->nullable()->after('depreciation_method');
            $table->decimal('residual_value', 15, 2)->nullable()->after('useful_life_years');
            $table->decimal('current_value', 15, 2)->nullable()->after('residual_value');
            $table->date('last_depreciation_date')->nullable()->after('current_value');
            $table->string('qr_code')->nullable()->after('last_depreciation_date');
            $table->string('barcode')->nullable()->after('qr_code');

            // Lifecycle
            $table->enum('lifecycle_stage', ['new', 'active', 'maintenance', 'retired', 'disposed'])->default('new')->after('barcode');
            $table->date('disposed_at')->nullable()->after('lifecycle_stage');
            $table->text('disposal_notes')->nullable()->after('disposed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn([
                'depreciation_method', 'useful_life_years', 'residual_value',
                'current_value', 'last_depreciation_date', 'qr_code', 'barcode',
                'lifecycle_stage', 'disposed_at', 'disposal_notes',
            ]);
        });

        Schema::dropIfExists('asset_requests');
        Schema::dropIfExists('software_license_assignments');
        Schema::dropIfExists('software_licenses');
    }
};
