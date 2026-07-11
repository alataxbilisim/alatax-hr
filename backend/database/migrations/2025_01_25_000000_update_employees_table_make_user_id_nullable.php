<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Baseline create_employees zaten user_id nullable.
     * Bu migration eski (NOT NULL) kurulumları taşır; fresh install'da no-op olabilir.
     * Driver-aware: SQLite table-rebuild; MySQL/pgsql Schema API (ham DATABASE()/SHOW yok).
     */
    public function up(): void
    {
        if (! Schema::hasTable('employees') || ! Schema::hasColumn('employees', 'user_id')) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        // Fresh baseline zaten nullable ise değişiklik yok
        $column = collect(Schema::getColumns('employees'))->firstWhere('name', 'user_id');
        if ($column && ($column['nullable'] ?? false) === true) {
            return;
        }

        if ($driver === 'sqlite') {
            $this->upSqlite();

            return;
        }

        $this->upRelational();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('employees') || ! Schema::hasColumn('employees', 'user_id')) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $this->downSqlite();

            return;
        }

        Schema::table('employees', function (Blueprint $table) {
            $this->dropForeignKeyIfExists('employees', 'user_id');
        });

        // Index adı ortama göre değişebilir; yoksa sessiz geç
        try {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropIndex(['company_id', 'user_id']);
            });
        } catch (\Throwable) {
            // index yok
        }

        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['company_id', 'user_id']);
        });
    }

    private function upRelational(): void
    {
        $this->dropForeignKeyIfExists('employees', 'user_id');

        // Eski unique (company_id, user_id) varsa kaldır
        try {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropUnique(['company_id', 'user_id']);
            });
        } catch (\Throwable) {
            // unique yok
        }

        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });

        $hasIndex = collect(Schema::getIndexes('employees'))->contains(
            fn (array $idx) => $idx['columns'] === ['company_id', 'user_id'] && ! ($idx['unique'] ?? false)
        );

        if (! $hasIndex) {
            Schema::table('employees', function (Blueprint $table) {
                $table->index(['company_id', 'user_id']);
            });
        }
    }

    private function dropForeignKeyIfExists(string $table, string $column): void
    {
        $foreign = collect(Schema::getForeignKeys($table))->first(
            fn (array $fk) => $fk['columns'] === [$column]
        );

        if ($foreign === null) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($foreign) {
            $blueprint->dropForeign($foreign['name']);
        });
    }

    private function upSqlite(): void
    {
        DB::statement('PRAGMA foreign_keys=OFF');

        Schema::create('employees_new', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('department_id')->nullable()->constrained()->onDelete('set null');

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
            $table->jsonb('custom_fields')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'employee_code']);
            $table->index(['company_id', 'user_id']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'department_id']);
        });

        DB::statement('INSERT INTO employees_new SELECT * FROM employees');
        Schema::dropIfExists('employees');
        Schema::rename('employees_new', 'employees');
        DB::statement('PRAGMA foreign_keys=ON');
    }

    private function downSqlite(): void
    {
        DB::statement('PRAGMA foreign_keys=OFF');

        Schema::create('employees_old', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('department_id')->nullable()->constrained()->onDelete('set null');

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
            $table->jsonb('custom_fields')->nullable();
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
    }
};
