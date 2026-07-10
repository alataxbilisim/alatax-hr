<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // SQLite için özel işlem gerekiyor
        if (DB::connection()->getDriverName() === 'sqlite') {
            // SQLite'da foreign key'leri geçici olarak devre dışı bırak
            DB::statement('PRAGMA foreign_keys=OFF');

            // Yeni tablo oluştur
            Schema::create('employees_new', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->onDelete('cascade');
                $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
                $table->foreignId('department_id')->nullable()->constrained()->onDelete('set null');

                // Personel bilgileri
                $table->string('employee_code')->nullable();
                $table->string('title')->nullable();
                $table->string('position')->nullable();
                $table->foreignId('manager_id')->nullable()->constrained('employees')->onDelete('set null');

                // Kişisel bilgiler
                $table->date('birth_date')->nullable();
                $table->string('national_id')->nullable();
                $table->string('gender')->nullable();
                $table->string('marital_status')->nullable();
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
                $table->string('contract_type')->nullable();
                $table->string('work_type')->nullable();

                // Maaş bilgileri
                $table->decimal('gross_salary', 12, 2)->nullable();
                $table->decimal('net_salary', 12, 2)->nullable();
                $table->string('currency')->default('TRY');
                $table->string('bank_name')->nullable();
                $table->string('iban')->nullable();

                // SGK bilgileri
                $table->string('sgk_number')->nullable();
                $table->date('sgk_start_date')->nullable();

                // Durum
                $table->string('status')->default('active');
                $table->date('termination_date')->nullable();
                $table->string('termination_reason')->nullable();

                // Ek bilgiler
                $table->text('notes')->nullable();
                $table->json('custom_fields')->nullable();

                $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
                $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['company_id', 'employee_code']);
                $table->index(['company_id', 'user_id']);
                $table->index(['company_id', 'status']);
                $table->index(['company_id', 'department_id']);
            });

            // Verileri kopyala
            DB::statement('INSERT INTO employees_new SELECT * FROM employees');

            // Eski tabloyu sil
            Schema::dropIfExists('employees');

            // Yeni tabloyu yeniden adlandır
            Schema::rename('employees_new', 'employees');

            // Foreign key'leri tekrar aç
            DB::statement('PRAGMA foreign_keys=ON');
        } else {
            // MySQL/PostgreSQL için
            Schema::table('employees', function (Blueprint $table) {
                // Foreign key varsa sil
                $foreignKeys = DB::select("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employees' AND COLUMN_NAME = 'user_id' AND CONSTRAINT_NAME != 'PRIMARY'");
                if (! empty($foreignKeys)) {
                    $table->dropForeign(['user_id']);
                }
                // Unique index varsa sil
                $indexes = DB::select("SHOW INDEXES FROM employees WHERE Key_name = 'employees_company_id_user_id_unique'");
                if (! empty($indexes)) {
                    $table->dropUnique(['company_id', 'user_id']);
                }
            });

            Schema::table('employees', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->change();
                $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
                // Index zaten varsa ekleme
                $indexes = DB::select("SHOW INDEXES FROM employees WHERE Key_name = 'employees_company_id_user_id_index'");
                if (empty($indexes)) {
                    $table->index(['company_id', 'user_id']);
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Geri alma işlemi - user_id'yi tekrar zorunlu yap
        if (DB::connection()->getDriverName() === 'sqlite') {
            // SQLite için benzer işlem
            DB::statement('PRAGMA foreign_keys=OFF');

            Schema::create('employees_old', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->onDelete('cascade');
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->foreignId('department_id')->nullable()->constrained()->onDelete('set null');

                // ... diğer alanlar aynı
                $table->string('employee_code')->nullable();
                $table->string('title')->nullable();
                $table->string('position')->nullable();
                $table->foreignId('manager_id')->nullable()->constrained('employees')->onDelete('set null');
                $table->date('birth_date')->nullable();
                $table->string('national_id')->nullable();
                $table->string('gender')->nullable();
                $table->string('marital_status')->nullable();
                $table->string('blood_type')->nullable();
                $table->string('education_level')->nullable();
                $table->string('personal_email')->nullable();
                $table->string('personal_phone')->nullable();
                $table->text('address')->nullable();
                $table->string('city')->nullable();
                $table->string('district')->nullable();
                $table->string('postal_code')->nullable();
                $table->string('emergency_contact_name')->nullable();
                $table->string('emergency_contact_phone')->nullable();
                $table->string('emergency_contact_relation')->nullable();
                $table->date('hire_date')->nullable();
                $table->date('contract_start_date')->nullable();
                $table->date('contract_end_date')->nullable();
                $table->string('contract_type')->nullable();
                $table->string('work_type')->nullable();
                $table->decimal('gross_salary', 12, 2)->nullable();
                $table->decimal('net_salary', 12, 2)->nullable();
                $table->string('currency')->default('TRY');
                $table->string('bank_name')->nullable();
                $table->string('iban')->nullable();
                $table->string('sgk_number')->nullable();
                $table->date('sgk_start_date')->nullable();
                $table->string('status')->default('active');
                $table->date('termination_date')->nullable();
                $table->string('termination_reason')->nullable();
                $table->text('notes')->nullable();
                $table->json('custom_fields')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
                $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['company_id', 'employee_code']);
                $table->unique(['company_id', 'user_id']);
                $table->index(['company_id', 'status']);
                $table->index(['company_id', 'department_id']);
            });

            DB::statement('INSERT INTO employees_old SELECT * FROM employees WHERE user_id IS NOT NULL');
            Schema::dropIfExists('employees');
            Schema::rename('employees_old', 'employees');
            DB::statement('PRAGMA foreign_keys=ON');
        } else {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
                $table->dropIndex(['company_id', 'user_id']);
            });

            Schema::table('employees', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable(false)->change();
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->unique(['company_id', 'user_id']);
            });
        }
    }
};
