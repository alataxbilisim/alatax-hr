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
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->string('name'); // Yıllık İzin, Mazeret İzni, Hastalık İzni vb.
            $table->string('code')->nullable(); // YI, MI, HI vb.
            $table->text('description')->nullable();
            $table->boolean('is_paid')->default(true); // Ücretli/Ücretsiz
            $table->integer('default_days')->default(0); // Yıllık varsayılan gün
            $table->boolean('requires_document')->default(false); // Belge gerekli mi?
            \App\Support\PortableEnum::column($table, 'gender_restriction', ['all', 'male', 'female'], 'all', false, 64, null);
            $table->boolean('is_active')->default(true);
            $table->integer('max_days_at_once')->nullable(); // Tek seferde max gün
            $table->integer('min_days_notice')->default(0); // Min kaç gün önceden talep
            $table->jsonb('approval_flow')->nullable(); // Onay akışı
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
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
        Schema::dropIfExists('leave_types');
    }
};
