<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateGroupRequest;
use App\Http\Requests\AddGroupMemberRequest;
use App\Http\Resources\GroupResource;
use App\Http\Resources\UserResource;
use App\Services\GroupService;
use App\Traits\ApiResponseTrait;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class GroupController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected GroupService $groupService
    ) {}

    /**
     * List user's groups.
     */
    public function index(Request $request): JsonResponse
    {
        $groups = $this->groupService->getUserGroups($request->user());

        return $this->successResponse(
            GroupResource::collection($groups),
            'Groups retrieved successfully.'
        );
    }

    /**
     * Create a new group.
     */
    public function store(CreateGroupRequest $request): JsonResponse
    {
        $group = $this->groupService->createGroup($request->user(), $request->validated());

        return $this->successResponse(
            new GroupResource($group),
            'Group created successfully.',
            201
        );
    }

    /**
     * Show a group with its members.
     */
    public function show(string $groupId, Request $request): JsonResponse
    {
        $group = $this->groupService->getGroupForUser($groupId, $request->user());

        return $this->successResponse(
            new GroupResource($group),
            'Group retrieved successfully.'
        );
    }

    /**
     * List members of a group.
     */
    public function members(string $groupId, Request $request): JsonResponse
    {
        $group = $this->groupService->getGroupForUser($groupId, $request->user());
        $memberIds = array_map('strval', $group->member_ids ?? []);
        $members = User::whereIn('_id', $memberIds)->get();

        return $this->successResponse(
            UserResource::collection($members),
            'Group members retrieved successfully.'
        );
    }

    /**
     * Add a member to a group.
     */
    public function addMember(string $groupId, AddGroupMemberRequest $request): JsonResponse
    {
        $group = $this->groupService->getGroupForUser($groupId, $request->user());
        $updatedGroup = $this->groupService->addMember($group, $request->user(), $request->validated('user_id'));

        return $this->successResponse(
            new GroupResource($updatedGroup),
            'Member added successfully.'
        );
    }

    /**
     * Remove a member from a group.
     */
    public function removeMember(string $groupId, string $userId, Request $request): JsonResponse
    {
        $group = $this->groupService->getGroupForUser($groupId, $request->user());
        $updatedGroup = $this->groupService->removeMember($group, $request->user(), $userId);

        return $this->successResponse(
            new GroupResource($updatedGroup),
            'Member removed successfully.'
        );
    }
}
