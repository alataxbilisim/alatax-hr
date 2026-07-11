<?php

namespace App\Models;

use App\Enums\CompanyLedgerType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyLedger extends Model
{
    use HasFactory;

    protected $table = 'company_ledger';

    protected $fillable = [
        'company_id',
        'type',
        'amount',
        'balance_after',
        'description',
        'reference_type',
        'reference_id',
        'payment_method',
        'payment_reference',
        'payment_date',
        'invoice_number',
        'due_date',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'payment_date' => 'date',
        'due_date' => 'date',
        'type' => CompanyLedgerType::class,
    ];

    /**
     * İşlem tipleri
     */
    const TYPE_DEBIT = 'debit';   // Borç (firmaya)

    const TYPE_CREDIT = 'credit'; // Alacak (firmadan ödeme)

    /**
     * Ödeme yöntemleri
     */
    const PAYMENT_METHOD_BANK = 'bank_transfer';

    const PAYMENT_METHOD_CARD = 'credit_card';

    const PAYMENT_METHOD_CASH = 'cash';

    const PAYMENT_METHOD_EFT = 'eft';

    /**
     * Referans tipleri
     */
    const REF_LICENSE = 'license';     // Lisans satışı

    const REF_RENEWAL = 'renewal';     // Lisans yenileme

    const REF_MODULE = 'module';       // Modül satışı

    const REF_PAYMENT = 'payment';     // Ödeme

    const REF_ADJUSTMENT = 'adjustment'; // Manuel düzeltme

    /**
     * Firma ilişkisi
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * İşlemi oluşturan kullanıcı
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Borç işlemi mi?
     */
    public function isDebit(): bool
    {
        return $this->type === CompanyLedgerType::Debit;
    }

    /**
     * Alacak işlemi mi?
     */
    public function isCredit(): bool
    {
        return $this->type === CompanyLedgerType::Credit;
    }

    /**
     * Tip etiketi (UI için)
     */
    public function getTypeLabel(): string
    {
        return $this->isDebit() ? 'Borç' : 'Alacak';
    }

    /**
     * Ödeme yöntemi etiketi
     */
    public function getPaymentMethodLabel(): string
    {
        $methods = [
            self::PAYMENT_METHOD_BANK => 'Banka Havalesi',
            self::PAYMENT_METHOD_CARD => 'Kredi Kartı',
            self::PAYMENT_METHOD_CASH => 'Nakit',
            self::PAYMENT_METHOD_EFT => 'EFT',
        ];

        return $methods[$this->payment_method] ?? $this->payment_method;
    }

    /**
     * Referans tipi etiketi
     */
    public function getReferenceTypeLabel(): string
    {
        $types = [
            self::REF_LICENSE => 'Lisans Satışı',
            self::REF_RENEWAL => 'Lisans Yenileme',
            self::REF_MODULE => 'Modül Satışı',
            self::REF_PAYMENT => 'Ödeme',
            self::REF_ADJUSTMENT => 'Manuel Düzeltme',
        ];

        return $types[$this->reference_type] ?? $this->reference_type;
    }

    /**
     * Borç işlemleri
     */
    public function scopeDebits($query)
    {
        return $query->where('type', self::TYPE_DEBIT);
    }

    /**
     * Alacak işlemleri
     */
    public function scopeCredits($query)
    {
        return $query->where('type', self::TYPE_CREDIT);
    }

    /**
     * Tarih aralığı filtresi
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Firmaya yeni işlem ekle ve bakiyeyi güncelle
     */
    public static function addTransaction(
        int $companyId,
        string $type,
        float $amount,
        string $description,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?array $additionalData = []
    ): self {
        $company = Company::findOrFail($companyId);

        // Mevcut bakiye
        $currentBalance = $company->current_balance;

        // Yeni bakiye hesapla
        if ($type === self::TYPE_DEBIT) {
            $newBalance = $currentBalance + $amount; // Borç artırır
        } else {
            $newBalance = $currentBalance - $amount; // Alacak azaltır
        }

        // İşlem kaydı oluştur
        $transaction = self::create(array_merge([
            'company_id' => $companyId,
            'type' => $type,
            'amount' => $amount,
            'balance_after' => $newBalance,
            'description' => $description,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'created_by' => auth()->id(),
        ], $additionalData));

        // Firma bakiyesini güncelle
        $company->update(['current_balance' => $newBalance]);

        return $transaction;
    }
}
