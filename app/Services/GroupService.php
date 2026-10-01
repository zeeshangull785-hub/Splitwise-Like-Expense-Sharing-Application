<?php

namespace App\Services;

use App\Models\User;
use App\Models\Group;
use App\Exceptions\GroupNotFoundException;
use App\Exceptions\UserNotFoundException;
use App\Exceptions\UnauthorizedGroupAccessException;
use App\Exceptions\ApiException;
use Illuminate\Support\Facades\Log;

class GroupService
{
    /**
     * Create a new group with the authenticated user as owner and first member.
     */
    public function createGroup(User $creator, array $data): Group
    {
        $memberIds = [(string)$creator->_id];

        if (!empty($data['initial_members']) && is_array($data['initial_members'])) {
            foreach ($data['initial_members'] as $candidateId) {
                $candidateUser = User::find($candidateId);
                if ($candidateUser && !in_array((string)$candidateUser->_id, $memberIds, true)) {
                    $memberIds[] = (string)$candidateUser->_id;
                }
            }
        }

        $group = Group::create([
            'name' => trim($data['name']),
            'description' => $data['description'] ?? null,
            'owner_id' => (string)$creator->_id,
            'member_ids' => $memberIds,
        ]);

        Log::info('Group created', ['group_id' => (string)$group->_id, 'owner_id' => (string)$creator->_id]);

        return $group;
    }

    /**
     * Get a group ensuring the user has access.
     */
    public function getGroupForUser(string $groupId, User $user): Group
    {
        $group = Group::find($groupId);
        if (!$group) {
            throw new GroupNotFoundException("Group with ID {$groupId} was not found.");
        }

        if (!$group->hasMember((string)$user->_id)) {
            Log::warning('Unauthorized group access attempt', ['group_id' => $groupId, 'user_id' => (string)$user->_id]);
            throw new UnauthorizedGroupAccessException('You are not a member of this group.');
        }

        return $group;
    }

    /**
     * List all groups where the user is a member.
     */
    public function getUserGroups(User $user)
    {
        return Group::where('member_ids', (string)$user->_id)->latest()->get();
    }

    /**
     * Add a member to a group (owner only).
     */
    public function addMember(Group $group, User $actor, string $newUserId): Group
    {
        if (!$group->isOwner((string)$actor->_id)) {
            Log::warning('Non-owner tried to add member', ['group_id' => (string)$group->_id, 'user_id' => (string)$actor->_id]);
            throw new UnauthorizedGroupAccessException('Only the group owner can add new members.');
        }

        $userToAdd = User::find($newUserId);
        if (!$userToAdd) {
            throw new UserNotFoundException("User with ID {$newUserId} was not found.");
        }

        $members = array_map('strval', $group->member_ids ?? []);
        if (in_array((string)$userToAdd->_id, $members, true)) {
            throw new ApiException('User is already a member of this group.', 422);
        }

        $members[] = (string)$userToAdd->_id;
        $group->member_ids = $members;
        $group->save();

        Log::info('Member added to group', ['group_id' => (string)$group->_id, 'user_id' => (string)$userToAdd->_id]);

        return $group;
    }

    /**
     * Remove a member from a group (owner only, cannot remove owner).
     */
    public function removeMember(Group $group, User $actor, string $userIdToRemove): Group
    {
        if (!$group->isOwner((string)$actor->_id)) {
            Log::warning('Non-owner tried to remove member', ['group_id' => (string)$group->_id, 'user_id' => (string)$actor->_id]);
            throw new UnauthorizedGroupAccessException('Only the group owner can remove members.');
        }

        if ((string)$group->owner_id === (string)$userIdToRemove) {
            throw new ApiException('Cannot remove the group owner.', 422);
        }

        $members = array_map('strval', $group->member_ids ?? []);
        if (!in_array((string)$userIdToRemove, $members, true)) {
            throw new ApiException('User is not a member of this group.', 404);
        }

        $group->member_ids = array_values(array_filter($members, fn($id) => (string)$id !== (string)$userIdToRemove));
        $group->save();

        Log::info('Member removed from group', ['group_id' => (string)$group->_id, 'user_id' => $userIdToRemove]);

        return $group;
    }
}
