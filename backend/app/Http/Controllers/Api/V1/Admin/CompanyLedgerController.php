<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\CompanyLedger;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CompanyLedgerController extends BaseController
{
    /**
     * Firma cari hesap hareketleri
     */
    public function index(Request $request, Company $company): JsonResponse
    {
        $query = $company->ledger()->with('createdBy:id,name');

        // Tarih filtresi
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('created_at', [
                $request->start_date . ' 00:00:00',
                $request->end_date . ' 23:59:59'
            ]);
        }

        // Tip filtresi
        if ($request->has('type') && in_array($request->type, ['debit', 'credit'])) {
            $query->where('type', $request->type);
        }

        $ledger = $query->paginate($request->get('per_page', 20));

        // Özet bilgiler
        $summary = [
            'current_balance' => $company->current_balance,
            'total_debit' => $company->ledger()->where('type', 'debit')->sum('amount'),
            'total_credit' => $company->ledger()->where('type', 'credit')->sum('amount'),
        ];

        return $this->success([
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'current_balance' => $company->current_balance,
            ],
            'summary' => $summary,
            'transactions' => $ledger->items(),
            'meta' => [
                'current_page' => $ledger->currentPage(),
                'last_page' => $ledger->lastPage(),
                'per_page' => $ledger->perPage(),
                'total' => $ledger->total(),
            ],
        ], 'Cari hesap hareketleri');
    }

    /**
     * Borç ekle (Lisans satışı, fatura vb.)
     */
    public function addDebit(Request $request, Company $company): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string|max:255',
            'reference_type' => 'nullable|string|in:license,renewal,module,adjustment',
            'reference_id' => 'nullable|integer',
            'invoice_number' => 'nullable|string|max:50',
            'due_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $transaction = CompanyLedger::addTransaction(
            $company->id,
            CompanyLedger::TYPE_DEBIT,
            $validated['amount'],
            $validated['description'],
            $validated['reference_type'] ?? null,
            $validated['reference_id'] ?? null,
            [
                'invoice_number' => $validated['invoice_number'] ?? null,
                'due_date' => $validated['due_date'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]
        );

        $company->refresh();

        ActivityLog::log('create', $transaction, 'Borç kaydı eklendi: ' . $company->name . ' - ' . number_format($validated['amount'], 2) . ' TL');

        return $this->success([
            'transaction' => $transaction,
            'new_balance' => $company->current_balance,
        ], 'Borç kaydı eklendi', 201);
    }

    /**
     * Alacak/Ödeme ekle
     */
    public function addCredit(Request $request, Company $company): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string|max:255',
            'payment_method' => 'required|string|in:bank_transfer,credit_card,cash,eft',
            'payment_reference' => 'nullable|string|max:100',
            'payment_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $transaction = CompanyLedger::addTransaction(
            $company->id,
            CompanyLedger::TYPE_CREDIT,
            $validated['amount'],
            $validated['description'],
            CompanyLedger::REF_PAYMENT,
            null,
            [
                'payment_method' => $validated['payment_method'],
                'payment_reference' => $validated['payment_reference'] ?? null,
                'payment_date' => $validated['payment_date'] ?? now()->toDateString(),
                'notes' => $validated['notes'] ?? null,
            ]
        );

        $company->refresh();

        ActivityLog::log('create', $transaction, 'Ödeme kaydı eklendi: ' . $company->name . ' - ' . number_format($validated['amount'], 2) . ' TL');

        return $this->success([
            'transaction' => $transaction,
            'new_balance' => $company->current_balance,
        ], 'Ödeme kaydı eklendi', 201);
    }

    /**
     * Cari hesap özeti (tüm firmalar için)
     */
    public function summary(Request $request): JsonResponse
    {
        $query = Company::query();

        // Sadece borçlu firmalar
        if ($request->boolean('only_debt')) {
            $query->where('current_balance', '>', 0);
        }

        // Sadece alacaklı firmalar
        if ($request->boolean('only_credit')) {
            $query->where('current_balance', '<', 0);
        }

        $companies = $query->orderBy('current_balance', 'desc')
            ->get(['id', 'name', 'email', 'current_balance', 'status', 'license_end_date']);

        $totalDebt = Company::where('current_balance', '>', 0)->sum('current_balance');
        $totalCredit = Company::where('current_balance', '<', 0)->sum('current_balance');
        $netBalance = $totalDebt + $totalCredit;

        return $this->success([
            'summary' => [
                'total_debt' => $totalDebt,
                'total_credit' => abs($totalCredit),
                'net_balance' => $netBalance,
                'companies_with_debt' => Company::where('current_balance', '>', 0)->count(),
                'companies_with_credit' => Company::where('current_balance', '<', 0)->count(),
            ],
            'companies' => $companies,
        ], 'Cari hesap özeti');
    }

    /**
     * Tekil işlem detayı
     */
    public function showTransaction(Company $company, CompanyLedger $transaction): JsonResponse
    {
        // İşlemin bu firmaya ait olduğunu kontrol et
        if ($transaction->company_id !== $company->id) {
            return $this->error('İşlem bu firmaya ait değil', null, 404);
        }

        $transaction->load('createdBy:id,name');

        return $this->success($transaction, 'İşlem detayı');
    }
}

