<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateSettlementRequest;
use App\Http\Resources\SettlementResource;
use App\Services\BalanceService;
use App\Services\SettlementService;
use App\Services\GroupService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SettlementController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected BalanceService $balanceService,
        protected SettlementService $settlementService,
        protected GroupService $groupService
    ) {}

    /**
     * Get net balances and who-owes-whom debt simplification for a group.
     */
    public function balances(string $groupId, Request $request): JsonResponse
    {
        $group = $this->groupService->getGroupForUser($groupId, $request->user());

        $balances = $this->balanceService->getGroupBalances($group);
        $simplifiedDebts = $this->balanceService->getSimplifiedDebts($group);

        return $this->successResponse([
            'group_id' => (string)$group->_id,
            'balances' => $balances,
            'simplified_debts' => $simplifiedDebts,
        ], 'Group balances retrieved successfully.');
    }

    /**
     * List settlements for a group.
     */
    public function index(string $groupId, Request $request): JsonResponse
    {
        $group = $this->groupService->getGroupForUser($groupId, $request->user());
        $settlements = $this->settlementService->getGroupSettlements($group);

        return $this->successResponse(
            SettlementResource::collection($settlements),
            'Group settlements retrieved successfully.'
        );
    }

    /**
     * Create a settlement payment between group members.
     */
    public function store(string $groupId, CreateSettlementRequest $request): JsonResponse
    {
        $group = $this->groupService->getGroupForUser($groupId, $request->user());
        $settlement = $this->settlementService->createSettlement($group, $request->user(), $request->validated());

        return $this->successResponse(
            new SettlementResource($settlement),
            'Settlement recorded successfully.',
            201
        );
    }
}
