<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Token;
use App\Models\Group;
use App\Models\Expense;

class ExpenseTest extends TestCase
{
    protected User $user1;
    protected string $token1;
    protected User $user2;
    protected string $token2;
    protected User $user3;
    protected User $outsider;
    protected string $outsiderToken;
    protected Group $group;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user1 = User::create(['name' => 'Ali', 'email' => 'ali_exp@example.com', 'password' => bcrypt('password123')]);
        $this->token1 = bin2hex(random_bytes(32));
        Token::create(['user_id' => (string)$this->user1->_id, 'api_token' => $this->token1]);

        $this->user2 = User::create(['name' => 'Ahmed', 'email' => 'ahmed_exp@example.com', 'password' => bcrypt('password123')]);
        $this->token2 = bin2hex(random_bytes(32));
        Token::create(['user_id' => (string)$this->user2->_id, 'api_token' => $this->token2]);

        $this->user3 = User::create(['name' => 'Usman', 'email' => 'usman_exp@example.com', 'password' => bcrypt('password123')]);

        $this->outsider = User::create(['name' => 'Outsider', 'email' => 'outsider_exp@example.com', 'password' => bcrypt('password123')]);
        $this->outsiderToken = bin2hex(random_bytes(32));
        Token::create(['user_id' => (string)$this->outsider->_id, 'api_token' => $this->outsiderToken]);

        $this->group = Group::create([
            'name' => 'Test Expense Group',
            'owner_id' => (string)$this->user1->_id,
            'member_ids' => [(string)$this->user1->_id, (string)$this->user2->_id, (string)$this->user3->_id],
        ]);
    }

    protected function tearDown(): void
    {
        User::whereIn('email', ['ali_exp@example.com', 'ahmed_exp@example.com', 'usman_exp@example.com', 'outsider_exp@example.com'])->delete();
        parent::tearDown();
    }

    public function test_can_create_equal_split_expense(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token1)
            ->postJson("/api/groups/{$this->group->_id}/expenses", [
                'description' => 'Team Lunch',
                'amount' => 12000,
                'split_type' => 'equal',
                'participants' => [
                    ['user_id' => (string)$this->user1->_id],
                    ['user_id' => (string)$this->user2->_id],
                    ['user_id' => (string)$this->user3->_id],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.amount', 12000)
            ->assertJsonPath('data.split_type', 'equal');

        $participants = $response->json('data.participants');
        $this->assertCount(3, $participants);
        foreach ($participants as $p) {
            $this->assertEquals(4000.0, $p['amount']);
        }
    }

    public function test_can_create_exact_split_expense(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token1)
            ->postJson("/api/groups/{$this->group->_id}/expenses", [
                'description' => 'Dinner',
                'amount' => 10000,
                'split_type' => 'exact',
                'participants' => [
                    ['user_id' => (string)$this->user1->_id, 'amount' => 5000],
                    ['user_id' => (string)$this->user2->_id, 'amount' => 3000],
                    ['user_id' => (string)$this->user3->_id, 'amount' => 2000],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_exact_split_fails_if_sum_does_not_match_total(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token1)
            ->postJson("/api/groups/{$this->group->_id}/expenses", [
                'description' => 'Mismatch Dinner',
                'amount' => 10000,
                'split_type' => 'exact',
                'participants' => [
                    ['user_id' => (string)$this->user1->_id, 'amount' => 5000],
                    ['user_id' => (string)$this->user2->_id, 'amount' => 3000],
                    // Total is 8000, not 10000
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_can_create_percentage_split_expense(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token1)
            ->postJson("/api/groups/{$this->group->_id}/expenses", [
                'description' => 'Concert Tickets',
                'amount' => 10000,
                'split_type' => 'percentage',
                'participants' => [
                    ['user_id' => (string)$this->user1->_id, 'percentage' => 50],
                    ['user_id' => (string)$this->user2->_id, 'percentage' => 30],
                    ['user_id' => (string)$this->user3->_id, 'percentage' => 20],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $participants = $response->json('data.participants');
        $this->assertEquals(5000.0, $participants[0]['amount']);
        $this->assertEquals(3000.0, $participants[1]['amount']);
        $this->assertEquals(2000.0, $participants[2]['amount']);
    }

    public function test_percentage_split_fails_if_total_not_100(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token1)
            ->postJson("/api/groups/{$this->group->_id}/expenses", [
                'description' => 'Invalid Percentage',
                'amount' => 10000,
                'split_type' => 'percentage',
                'participants' => [
                    ['user_id' => (string)$this->user1->_id, 'percentage' => 50],
                    ['user_id' => (string)$this->user2->_id, 'percentage' => 30],
                    // Total is 80%
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_cannot_add_non_member_as_participant(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token1)
            ->postJson("/api/groups/{$this->group->_id}/expenses", [
                'description' => 'Outsider Participant',
                'amount' => 6000,
                'split_type' => 'equal',
                'participants' => [
                    ['user_id' => (string)$this->user1->_id],
                    ['user_id' => (string)$this->outsider->_id], // Not a member
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }
}
