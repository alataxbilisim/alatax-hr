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
        Schema::create('custom_field_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');

            // Alan tanımları
            $table->string('entity_type'); // 'employee', 'leave_request', 'training', 'performance' vb.
            $table->string('field_key'); // Unique key for the field (e.g., 'blood_type', 'driver_license')
            $table->string('field_label'); // Display label
            $table->string('field_type'); // text, number, date, select, checkbox, radio, textarea, file, email, phone, url
            $table->jsonb('field_options')->nullable(); // For select/radio/checkbox: [{value: 'A+', label: 'A Pozitif'}]

            // Validation
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->jsonb('validation_rules')->nullable(); // ['min:3', 'max:100', 'regex:/pattern/']

            // Metadata
            $table->string('placeholder')->nullable();
            $table->text('help_text')->nullable();
            $table->string('default_value')->nullable();

            // Audit
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->unique(['company_id', 'entity_type', 'field_key']);
            $table->index(['company_id', 'entity_type', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custom_field_definitions');
    }
};
