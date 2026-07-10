<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\ExpenseCategory;
use App\Models\ExpenseClaim;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExpenseTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Company $company;

    private ExpenseCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create(['is_active' => true]);
        $this->user = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => 'user',
        ]);
        $this->category = ExpenseCategory::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Yemek',
            'is_active' => true,
        ]);
    }

    /** @test */
    public function user_can_list_expense_categories(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/v1/portal/expenses/categories');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name'],
                ],
            ]);
    }

    /** @test */
    public function user_can_create_expense_claim(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/portal/expenses', [
            'title' => 'Test Masraf',
            'description' => 'Test açıklama',
            'expense_date' => now()->toDateString(),
            'items' => [
                [
                    'expense_category_id' => $this->category->id,
                    'description' => 'Öğle yemeği',
                    'item_date' => now()->toDateString(),
                    'amount' => 150.50,
                    'vendor_name' => 'Test Restaurant',
                ],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('expense_claims', [
            'user_id' => $this->user->id,
            'title' => 'Test Masraf',
            'status' => 'draft',
        ]);
    }

    /** @test */
    public function user_can_list_their_expenses(): void
    {
        Sanctum::actingAs($this->user);

        ExpenseClaim::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'title' => 'My Expense',
        ]);

        $response = $this->getJson('/api/v1/portal/expenses');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'data' => [
                        '*' => ['id', 'title', 'status', 'total_amount'],
                    ],
                ],
            ]);
    }

    /** @test */
    public function user_can_submit_draft_expense(): void
    {
        Sanctum::actingAs($this->user);

        $claim = ExpenseClaim::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'status' => 'draft',
        ]);

        // Add an item
        $claim->items()->create([
            'expense_category_id' => $this->category->id,
            'description' => 'Test item',
            'item_date' => now(),
            'amount' => 100,
        ]);

        $response = $this->postJson("/api/v1/portal/expenses/{$claim->id}/submit");

        $response->assertOk();

        $this->assertDatabaseHas('expense_claims', [
            'id' => $claim->id,
            'status' => 'submitted',
        ]);
    }

    /** @test */
    public function user_can_cancel_their_expense(): void
    {
        Sanctum::actingAs($this->user);

        $claim = ExpenseClaim::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'status' => 'draft',
        ]);

        $response = $this->deleteJson("/api/v1/portal/expenses/{$claim->id}");

        $response->assertOk();

        $this->assertDatabaseMissing('expense_claims', [
            'id' => $claim->id,
        ]);
    }

    /** @test */
    public function user_cannot_cancel_approved_expense(): void
    {
        Sanctum::actingAs($this->user);

        $claim = ExpenseClaim::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'status' => 'approved',
        ]);

        $response = $this->deleteJson("/api/v1/portal/expenses/{$claim->id}");

        $response->assertStatus(404); // Won't find because of query filter
    }

    /** @test */
    public function user_can_get_expense_summary(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/v1/portal/expenses/summary');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'pending_count',
                    'pending_amount',
                    'approved_this_month',
                    'paid_this_month',
                ],
            ]);
    }
}
