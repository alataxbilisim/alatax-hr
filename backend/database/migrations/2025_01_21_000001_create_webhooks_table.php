<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('url');
            $table->string('secret')->nullable();
            $table->jsonb('events')->nullable(); // ['user.created', 'leave.approved', etc.]
            $table->boolean('is_active')->default(true);
            $table->integer('timeout')->default(30); // seconds
            $table->integer('retry_count')->default(3);
            $table->timestamp('last_triggered_at')->nullable();
            $table->integer('success_count')->default(0);
            $table->integer('failure_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('company_id');
            $table->index('is_active');
        });

        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_id')->constrained()->cascadeOnDelete();
            $table->string('event');
            $table->text('payload');
            $table->integer('status_code')->nullable();
            $table->text('response')->nullable();
            $table->text('error_message')->nullable();
            $table->boolean('is_successful')->default(false);
            $table->timestamp('triggered_at');
            $table->timestamps();

            $table->index('webhook_id');
            $table->index('is_successful');
            $table->index('triggered_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_logs');
        Schema::dropIfExists('webhooks');
    }
};
