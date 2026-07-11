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
        Schema::create('company_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');

            // İşlem tipi: debit = borç (firmaya), credit = alacak (firmadan ödeme)
            \App\Support\PortableEnum::column($table, 'type', ['debit', 'credit'], null, false, 64, null);
            $table->decimal('amount', 12, 2); // İşlem tutarı
            $table->decimal('balance_after', 12, 2); // İşlem sonrası bakiye

            // Açıklama ve referans
            $table->string('description'); // İşlem açıklaması
            $table->string('reference_type')->nullable(); // İlişkili kayıt tipi (license, payment, invoice vb.)
            $table->unsignedBigInteger('reference_id')->nullable(); // İlişkili kayıt ID

            // Ödeme detayları (credit için)
            $table->string('payment_method')->nullable(); // bank_transfer, credit_card, cash
            $table->string('payment_reference')->nullable(); // Dekont no, işlem no vb.
            $table->date('payment_date')->nullable(); // Ödeme tarihi

            // Fatura bilgisi (debit için)
            $table->string('invoice_number')->nullable(); // Fatura numarası
            $table->date('due_date')->nullable(); // Vade tarihi

            // Notlar
            $table->text('notes')->nullable();

            // Audit (foreign key users tablosu oluşturulduktan sonra eklenecek)
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            // İndeksler
            $table->index('type');
            $table->index('payment_date');
            $table->index('due_date');
            $table->index(['reference_type', 'reference_id']);
        });
            \App\Support\PortableEnum::flushChecks();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_ledger');
    }
};
