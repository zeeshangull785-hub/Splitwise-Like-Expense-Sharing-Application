<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Token;
use App\Models\Group;

class GroupTest extends TestCase
{
    protected User $owner;
    protected string $ownerToken;
    protected User $member;
    protected string $memberToken;
    protected User $outsider;
    protected string $outsiderToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::create([
            'name' => 'Group Owner',
            'email' => 'group_owner@example.com',
            'password' => bcrypt('password123'),
        ]);
        $this->ownerToken = bin2hex(random_bytes(32));
        Token::create(['user_id' => (string)$this->owner->_id, 'api_token' => $this->ownerToken]);

        $this->member = User::create([
            'name' => 'Group Member',
            'email' => 'group_member@example.com',
            'password' => bcrypt('password123'),
        ]);
        $this->memberToken = bin2hex(random_bytes(32));
        Token::create(['user_id' => (string)$this->member->_id, 'api_token' => $this->memberToken]);

        $this->outsider = User::create([
            'name' => 'Outsider',
            'email' => 'outsider@example.com',
            'password' => bcrypt('password123'),
        ]);
        $this->outsiderToken = bin2hex(random_bytes(32));
        Token::create(['user_id' => (string)$this->outsider->_id, 'api_token' => $this->outsiderToken]);
    }

    protected function tearDown(): void
    {
        User::whereIn('email', ['group_owner@example.com', 'group_member@example.com', 'outsider@example.com'])->delete();
        parent::tearDown();
    }

    public function test_user_can_create_group(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->ownerToken)
            ->postJson('/api/groups', [
                'name' => 'Dubai Trip 2026',
                'description' => 'Holiday group',
                'initial_members' => [(string)$this->member->_id],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Dubai Trip 2026')
            ->assertJsonPath('data.owner_id', (string)$this->owner->_id);

        $groupId = $response->json('data.id');
        $groupInDb = Group::find($groupId);
        $this->assertNotNull($groupInDb);
        $this->assertTrue($groupInDb->hasMember((string)$this->member->_id));
    }

    public function test_owner_can_add_and_remove_member(): void
    {
        $group = Group::create([
            'name' => 'Office Lunch',
            'owner_id' => (string)$this->owner->_id,
            'member_ids' => [(string)$this->owner->_id],
        ]);

        // Owner adds member
        $addResponse = $this->withHeader('Authorization', 'Bearer ' . $this->ownerToken)
            ->postJson("/api/groups/{$group->_id}/members", [
                'user_id' => (string)$this->member->_id,
            ]);

        $addResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertTrue($group->fresh()->hasMember((string)$this->member->_id));

        // Owner removes member
        $removeResponse = $this->withHeader('Authorization', 'Bearer ' . $this->ownerToken)
            ->deleteJson("/api/groups/{$group->_id}/members/{$this->member->_id}");

        $removeResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertFalse($group->fresh()->hasMember((string)$this->member->_id));
    }

    public function test_non_owner_cannot_add_or_remove_members(): void
    {
        $group = Group::create([
            'name' => 'Protected Group',
            'owner_id' => (string)$this->owner->_id,
            'member_ids' => [(string)$this->owner->_id, (string)$this->member->_id],
        ]);

        // Member attempts to add outsider
        $addResponse = $this->withHeader('Authorization', 'Bearer ' . $this->memberToken)
            ->postJson("/api/groups/{$group->_id}/members", [
                'user_id' => (string)$this->outsider->_id,
            ]);

        $addResponse->assertStatus(403)
            ->assertJsonPath('success', false);

        // Member attempts to remove owner
        $removeResponse = $this->withHeader('Authorization', 'Bearer ' . $this->memberToken)
            ->deleteJson("/api/groups/{$group->_id}/members/{$this->owner->_id}");

        $removeResponse->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_outsider_cannot_access_group_data(): void
    {
        $group = Group::create([
            'name' => 'Secret Group',
            'owner_id' => (string)$this->owner->_id,
            'member_ids' => [(string)$this->owner->_id],
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->outsiderToken)
            ->getJson("/api/groups/{$group->_id}");

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_owner_can_update_and_delete_group(): void
    {
        $group = Group::create([
            'name' => 'Old Group Name',
            'owner_id' => (string)$this->owner->_id,
            'member_ids' => [(string)$this->owner->_id],
        ]);

        // Update
        $updateResponse = $this->withHeader('Authorization', 'Bearer ' . $this->ownerToken)
            ->putJson("/api/groups/{$group->_id}", [
                'name' => 'Updated Group Name',
                'description' => 'Updated Description',
            ]);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Updated Group Name');

        // Delete
        $deleteResponse = $this->withHeader('Authorization', 'Bearer ' . $this->ownerToken)
            ->deleteJson("/api/groups/{$group->_id}");

        $deleteResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertNull(Group::find((string)$group->_id));
    }

    public function test_non_owner_cannot_update_or_delete_group(): void
    {
        $group = Group::create([
            'name' => 'Group for Member',
            'owner_id' => (string)$this->owner->_id,
            'member_ids' => [(string)$this->owner->_id, (string)$this->member->_id],
        ]);

        $updateResponse = $this->withHeader('Authorization', 'Bearer ' . $this->memberToken)
            ->putJson("/api/groups/{$group->_id}", [
                'name' => 'Hacked Name',
            ]);

        $updateResponse->assertStatus(403);

        $deleteResponse = $this->withHeader('Authorization', 'Bearer ' . $this->memberToken)
            ->deleteJson("/api/groups/{$group->_id}");

        $deleteResponse->assertStatus(403);
    }
}
