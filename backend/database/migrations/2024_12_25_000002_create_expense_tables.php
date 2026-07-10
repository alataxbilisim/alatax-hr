<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Expense Categories (Masraf Kategorileri)
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->text('description')->nullable();
            $table->decimal('max_amount', 12, 2)->nullable();
            $table->boolean('requires_receipt')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Expense Claims (Masraf Talepleri)
        Schema::create('expense_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('claim_number')->unique();
            $table->date('expense_date');
            $table->decimal('total_amount', 12, 2);
            $table->string('currency')->default('TRY');
            $table->string('status')->default('draft'); // draft, submitted, approved, rejected, paid
            $table->text('rejection_reason')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('payment_reference')->nullable();
            $table->timestamps();
        });

        // Expense Items (Masraf Kalemleri)
        Schema::create('expense_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_claim_id')->constrained()->cascadeOnDelete();
            $table->foreignId('expense_category_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->date('item_date');
            $table->decimal('amount', 12, 2);
            $table->string('currency')->default('TRY');
            $table->string('receipt_path')->nullable();
            $table->string('receipt_number')->nullable();
            $table->string('vendor_name')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_items');
        Schema::dropIfExists('expense_claims');
        Schema::dropIfExists('expense_categories');
    }
};
