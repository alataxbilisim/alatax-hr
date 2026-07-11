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
        // Learning Path (Öğrenme Yolları)
        Schema::create('learning_paths', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');

            $table->string('name');
            $table->text('description')->nullable();
            $table->string('thumbnail_path')->nullable();
            \App\Support\PortableEnum::column($table, 'level', ['beginner', 'intermediate', 'advanced'], 'beginner', false, 64, null);
            $table->integer('estimated_hours')->default(0);

            $table->boolean('is_mandatory')->default(false);
            $table->boolean('is_active')->default(true);

            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();
        });

        // Learning Path Items (Yoldaki Eğitimler)
        Schema::create('learning_path_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_path_id')->constrained()->onDelete('cascade');
            $table->foreignId('training_id')->constrained()->onDelete('cascade');

            $table->integer('order_number');
            $table->boolean('is_required')->default(true);
            $table->foreignId('prerequisite_item_id')->nullable()->constrained('learning_path_items')->nullOnDelete();

            $table->timestamps();

            $table->unique(['learning_path_id', 'training_id']);
        });

        // Kullanıcı Learning Path İlerlemesi
        Schema::create('user_learning_paths', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('learning_path_id')->constrained()->onDelete('cascade');

            \App\Support\PortableEnum::column($table, 'status', ['not_started', 'in_progress', 'completed'], 'not_started', false, 64, null);
            $table->decimal('progress', 5, 2)->default(0);
            $table->datetime('started_at')->nullable();
            $table->datetime('completed_at')->nullable();
            $table->date('due_date')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'learning_path_id']);
        });

        // Zorunlu Eğitimler (Compliance)
        Schema::create('mandatory_trainings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('training_id')->constrained()->onDelete('cascade');

            \App\Support\PortableEnum::column($table, 'scope', ['all', 'department', 'position', 'new_hires'], 'all', false, 64, null);
            $table->string('scope_value')->nullable();

            $table->integer('completion_days')->default(30); // Kaç gün içinde tamamlanmalı
            $table->integer('recertification_months')->nullable(); // Yenileme periyodu
            $table->boolean('is_active')->default(true);

            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index(['company_id', 'scope']);
        });

        // Sertifika Yönetimi (Genişletilmiş)
        Schema::table('training_certificates', function (Blueprint $table) {
            // expiry_date zaten var, sadece yeni kolonları ekle
            if (! Schema::hasColumn('training_certificates', 'is_valid')) {
                $table->boolean('is_valid')->default(true)->after('expiry_date');
            }
            if (! Schema::hasColumn('training_certificates', 'last_reminded_at')) {
                $table->date('last_reminded_at')->nullable()->after('is_valid');
            }
            if (! Schema::hasColumn('training_certificates', 'external_certificate_id')) {
                $table->string('external_certificate_id')->nullable()->after('last_reminded_at'); // Dış sertifika numarası
            }
            if (! Schema::hasColumn('training_certificates', 'issuing_organization')) {
                $table->string('issuing_organization')->nullable()->after('external_certificate_id');
            }
        });

        // Eğitim Talepleri
        Schema::create('training_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            $table->string('training_name');
            $table->text('description')->nullable();
            $table->text('justification'); // Neden gerekli
            $table->string('provider')->nullable(); // Eğitim sağlayıcı
            $table->decimal('estimated_cost', 10, 2)->nullable();
            $table->string('currency', 3)->default('TRY');

            \App\Support\PortableEnum::column($table, 'status', ['pending', 'approved', 'rejected', 'completed'], 'pending', false, 64, null);
            $table->text('approval_notes')->nullable();

            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->datetime('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
            \App\Support\PortableEnum::flushChecks();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('training_requests');

        Schema::table('training_certificates', function (Blueprint $table) {
            $table->dropColumn(['expiry_date', 'is_valid', 'last_reminded_at', 'external_certificate_id', 'issuing_organization']);
        });

        Schema::dropIfExists('mandatory_trainings');
        Schema::dropIfExists('user_learning_paths');
        Schema::dropIfExists('learning_path_items');
        Schema::dropIfExists('learning_paths');
    }
};
