<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Token;
use App\Models\Group;
use App\Models\Expense;
use App\Services\ExpenseService;

class BalanceAndSettlementTest extends TestCase
{
    protected User $ali;
    protected string $aliToken;
    protected User $ahmed;
    protected string $ahmedToken;
    protected User $usman;
    protected string $usmanToken;
    protected Group $group;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ali = User::create(['name' => 'Ali', 'email' => 'ali_bal@example.com', 'password' => bcrypt('password123')]);
        $this->aliToken = bin2hex(random_bytes(32));
        Token::create(['user_id' => (string)$this->ali->_id, 'api_token' => $this->aliToken]);

        $this->ahmed = User::create(['name' => 'Ahmed', 'email' => 'ahmed_bal@example.com', 'password' => bcrypt('password123')]);
        $this->ahmedToken = bin2hex(random_bytes(32));
        Token::create(['user_id' => (string)$this->ahmed->_id, 'api_token' => $this->ahmedToken]);

        $this->usman = User::create(['name' => 'Usman', 'email' => 'usman_bal@example.com', 'password' => bcrypt('password123')]);
        $this->usmanToken = bin2hex(random_bytes(32));
        Token::create(['user_id' => (string)$this->usman->_id, 'api_token' => $this->usmanToken]);

        $this->group = Group::create([
            'name' => 'Balance Test Group',
            'owner_id' => (string)$this->ali->_id,
            'member_ids' => [(string)$this->ali->_id, (string)$this->ahmed->_id, (string)$this->usman->_id],
        ]);
    }

    protected function tearDown(): void
    {
        User::whereIn('email', ['ali_bal@example.com', 'ahmed_bal@example.com', 'usman_bal@example.com'])->delete();
        parent::tearDown();
    }

    public function test_group_balances_and_debt_simplification(): void
    {
        $expenseService = app(ExpenseService::class);

        // Ali pays 12,000 split equally among Ali, Ahmed, Usman (4,000 each)
        // Ali paid: 12000, share: 4000 => Net: +8000
        // Ahmed paid: 0, share: 4000 => Net: -4000
        // Usman paid: 0, share: 4000 => Net: -4000
        $expenseService->createExpense($this->group, $this->ali, [
            'description' => 'Group Dinner',
            'amount' => 12000,
            'split_type' => 'equal',
            'participants' => [
                ['user_id' => (string)$this->ali->_id],
                ['user_id' => (string)$this->ahmed->_id],
                ['user_id' => (string)$this->usman->_id],
            ],
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->aliToken)
            ->getJson("/api/groups/{$this->group->_id}/balances");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $balances = collect($response->json('data.balances'))->keyBy('user_id');
        $this->assertEquals(8000.0, $balances[(string)$this->ali->_id]['balance']);
        $this->assertEquals(-4000.0, $balances[(string)$this->ahmed->_id]['balance']);
        $this->assertEquals(-4000.0, $balances[(string)$this->usman->_id]['balance']);

        // Check simplified debts
        $debts = $response->json('data.simplified_debts');
        $this->assertCount(2, $debts);
    }

    public function test_settlement_updates_group_balances(): void
    {
        $expenseService = app(ExpenseService::class);

        // Ali pays 9000 split equally among 3 (3000 each)
        // Ali: +6000, Ahmed: -3000, Usman: -3000
        $expenseService->createExpense($this->group, $this->ali, [
            'description' => 'Cabin Rent',
            'amount' => 9000,
            'split_type' => 'equal',
            'participants' => [
                ['user_id' => (string)$this->ali->_id],
                ['user_id' => (string)$this->ahmed->_id],
                ['user_id' => (string)$this->usman->_id],
            ],
        ]);

        // Ahmed settles 3000 to Ali
        $settleResponse = $this->withHeader('Authorization', 'Bearer ' . $this->ahmedToken)
            ->postJson("/api/groups/{$this->group->_id}/settlements", [
                'paid_to' => (string)$this->ali->_id,
                'amount' => 3000,
                'note' => 'Paid via Bank Transfer',
            ]);

        $settleResponse->assertStatus(201)
            ->assertJsonPath('success', true);

        // Verify updated balances:
        // Ahmed paid his debt: balance becomes 0
        // Ali collected 3000: balance becomes +3000
        // Usman still owes 3000: balance is -3000
        $balResponse = $this->withHeader('Authorization', 'Bearer ' . $this->aliToken)
            ->getJson("/api/groups/{$this->group->_id}/balances");

        $balances = collect($balResponse->json('data.balances'))->keyBy('user_id');
        $this->assertEquals(3000.0, $balances[(string)$this->ali->_id]['balance']);
        $this->assertEquals(0.0, $balances[(string)$this->ahmed->_id]['balance']);
        $this->assertEquals(-3000.0, $balances[(string)$this->usman->_id]['balance']);
    }

    public function test_cannot_settle_more_than_outstanding_debt(): void
    {
        $expenseService = app(ExpenseService::class);

        // Ali pays 6000 split between Ali & Ahmed (3000 each) => Ahmed owes 3000
        $expenseService->createExpense($this->group, $this->ali, [
            'description' => 'Cab Ride',
            'amount' => 6000,
            'split_type' => 'equal',
            'participants' => [
                ['user_id' => (string)$this->ali->_id],
                ['user_id' => (string)$this->ahmed->_id],
            ],
        ]);

        // Ahmed tries to settle 5000 (more than 3000 debt)
        $settleResponse = $this->withHeader('Authorization', 'Bearer ' . $this->ahmedToken)
            ->postJson("/api/groups/{$this->group->_id}/settlements", [
                'paid_to' => (string)$this->ali->_id,
                'amount' => 5000,
            ]);

        $settleResponse->assertStatus(422)
            ->assertJsonPath('success', false);
    }
}
