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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('department_id')->nullable()->constrained()->onDelete('set null');
            
            // Personel bilgileri
            $table->string('employee_code')->nullable(); // Sicil numarası
            $table->string('title')->nullable(); // Unvan
            $table->string('position')->nullable(); // Pozisyon
            $table->foreignId('manager_id')->nullable()->constrained('employees')->onDelete('set null');
            
            // Kişisel bilgiler
            $table->date('birth_date')->nullable();
            $table->string('national_id')->nullable(); // TC Kimlik No (encrypted)
            $table->string('gender')->nullable(); // male, female
            $table->string('marital_status')->nullable(); // single, married, divorced
            $table->string('blood_type')->nullable();
            $table->string('education_level')->nullable();
            
            // İletişim
            $table->string('personal_email')->nullable();
            $table->string('personal_phone')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('district')->nullable();
            $table->string('postal_code')->nullable();
            
            // Acil durum
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->string('emergency_contact_relation')->nullable();
            
            // İş bilgileri
            $table->date('hire_date')->nullable();
            $table->date('contract_start_date')->nullable();
            $table->date('contract_end_date')->nullable();
            $table->string('contract_type')->nullable(); // permanent, temporary, intern, contract
            $table->string('work_type')->nullable(); // full_time, part_time, remote, hybrid
            
            // Maaş bilgileri (encrypted)
            $table->decimal('gross_salary', 12, 2)->nullable();
            $table->decimal('net_salary', 12, 2)->nullable();
            $table->string('currency')->default('TRY');
            $table->string('bank_name')->nullable();
            $table->string('iban')->nullable();
            
            // SGK bilgileri
            $table->string('sgk_number')->nullable();
            $table->date('sgk_start_date')->nullable();
            
            // Durum
            $table->string('status')->default('active'); // active, on_leave, suspended, terminated
            $table->date('termination_date')->nullable();
            $table->string('termination_reason')->nullable();
            
            // Ek bilgiler
            $table->text('notes')->nullable();
            $table->json('custom_fields')->nullable(); // Firmaların özel alanları için
            
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'employee_code']);
            $table->index(['company_id', 'user_id']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'department_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};

