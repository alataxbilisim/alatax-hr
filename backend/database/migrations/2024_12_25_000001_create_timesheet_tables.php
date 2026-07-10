<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Work Schedules (Çalışma Takvimi Şablonları)
        Schema::create('work_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name'); // e.g. "Standart Mesai", "Vardiyalı"
            $table->string('type')->default('fixed'); // fixed, flexible, shift
            $table->json('working_days')->nullable(); // [1,2,3,4,5] = Mon-Fri
            $table->time('default_start_time')->nullable();
            $table->time('default_end_time')->nullable();
            $table->time('break_start')->nullable();
            $table->time('break_end')->nullable();
            $table->integer('break_duration_minutes')->default(60);
            $table->decimal('daily_hours', 4, 2)->default(8);
            $table->decimal('weekly_hours', 5, 2)->default(40);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Shifts (Vardiyalar)
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name'); // e.g. "Sabah Vardiyası", "Gece Vardiyası"
            $table->string('code')->nullable(); // SV, GV, etc.
            $table->time('start_time');
            $table->time('end_time');
            $table->time('break_start')->nullable();
            $table->time('break_end')->nullable();
            $table->integer('break_duration_minutes')->default(0);
            $table->string('color')->default('#3b82f6');
            $table->boolean('is_night_shift')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Employee Shift Assignments (Çalışan Vardiya Atamaları)
        Schema::create('employee_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shift_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'date']);
        });

        // Attendance Records (Giriş/Çıkış Kayıtları)
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->time('clock_in')->nullable();
            $table->time('clock_out')->nullable();
            $table->time('break_start')->nullable();
            $table->time('break_end')->nullable();
            $table->decimal('total_hours', 5, 2)->nullable();
            $table->decimal('overtime_hours', 5, 2)->default(0);
            $table->string('clock_in_method')->default('manual'); // manual, qr, nfc, gps, face
            $table->string('clock_out_method')->nullable();
            $table->decimal('clock_in_latitude', 10, 7)->nullable();
            $table->decimal('clock_in_longitude', 10, 7)->nullable();
            $table->decimal('clock_out_latitude', 10, 7)->nullable();
            $table->decimal('clock_out_longitude', 10, 7)->nullable();
            $table->string('clock_in_ip')->nullable();
            $table->string('clock_out_ip')->nullable();
            $table->string('status')->default('present'); // present, absent, late, early_leave, holiday, leave
            $table->text('notes')->nullable();
            $table->boolean('is_approved')->default(false);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'date']);
        });

        // Timesheets (Haftalık/Aylık Puantaj)
        Schema::create('timesheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('period_type')->default('weekly'); // weekly, biweekly, monthly
            $table->decimal('total_hours', 6, 2)->default(0);
            $table->decimal('regular_hours', 6, 2)->default(0);
            $table->decimal('overtime_hours', 6, 2)->default(0);
            $table->integer('working_days')->default(0);
            $table->integer('absent_days')->default(0);
            $table->integer('late_days')->default(0);
            $table->integer('leave_days')->default(0);
            $table->string('status')->default('draft'); // draft, submitted, approved, rejected
            $table->text('employee_notes')->nullable();
            $table->text('manager_notes')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'period_start', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timesheets');
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('employee_shifts');
        Schema::dropIfExists('shifts');
        Schema::dropIfExists('work_schedules');
    }
};
