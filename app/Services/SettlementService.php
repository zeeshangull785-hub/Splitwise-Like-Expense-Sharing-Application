<?php

namespace App\Services;

use App\Models\User;
use App\Models\Group;
use App\Models\Settlement;
use App\Exceptions\ApiException;
use App\Exceptions\InvalidSettlementException;
use App\Exceptions\UnauthorizedGroupAccessException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SettlementService
{
    public function __construct(
        protected BalanceService $balanceService
    ) {}

    /**
     * Create a settlement between two group members.
     * Validates:
     * - Actor belongs to group (and is payer)
     * - Receiver belongs to group
     * - Payer != Receiver
     * - Amount > 0
     * - User cannot settle more than outstanding debt (optional soft/hard boundary)
     */
    public function createSettlement(Group $group, User $payer, array $data): Settlement
    {
        $payerId = (string)$payer->_id;
        $receiverId = (string)$data['paid_to'];
        $amount = round((float)$data['amount'], 2);

        if (!$group->hasMember($payerId)) {
            throw new UnauthorizedGroupAccessException('Payer must be a member of the group.');
        }

        if (!$group->hasMember($receiverId)) {
            throw new ApiException('Receiver must be a member of the group.', 422);
        }

        if ($payerId === $receiverId) {
            throw new InvalidSettlementException('Payer and receiver cannot be the same user.');
        }

        if ($amount <= 0) {
            throw new InvalidSettlementException('Settlement amount must be greater than zero.');
        }

        // Check outstanding balance to prevent overpaying
        $balances = $this->balanceService->getGroupBalances($group);
        $userBalanceMap = collect($balances)->keyBy('user_id');

        $payerBalance = $userBalanceMap->get($payerId)['balance'] ?? 0.0;
        // If payer has balance >= 0, they don't owe money to the group overall
        if ($payerBalance > 0.009) {
            throw new InvalidSettlementException('You do not have any outstanding debts in this group.');
        }

        $outstandingDebt = abs($payerBalance);
        if ($amount > ($outstandingDebt + 0.01)) {
            throw new InvalidSettlementException("Settlement amount ({$amount}) exceeds your total outstanding debt ({$outstandingDebt}).");
        }

        $settlementData = [
            'group_id' => (string)$group->_id,
            'paid_by' => $payerId,
            'paid_to' => $receiverId,
            'amount' => $amount,
            'note' => $data['note'] ?? null,
        ];

        return $this->executeAtomically(function () use ($settlementData) {
            $settlement = Settlement::create($settlementData);
            Log::info('Settlement created', [
                'settlement_id' => (string)$settlement->_id,
                'group_id' => $settlementData['group_id'],
                'amount' => $settlementData['amount'],
            ]);
            return $settlement;
        });
    }

    /**
     * List settlements for a group.
     */
    public function getGroupSettlements(Group $group)
    {
        return Settlement::where('group_id', (string)$group->_id)->latest()->get();
    }

    /**
     * Atomic transaction wrapper with standalone fallback.
     */
    protected function executeAtomically(callable $callback)
    {
        try {
            return DB::connection('mongodb')->transaction($callback);
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'replica set') || str_contains($e->getMessage(), 'standalone')) {
                return $callback();
            }
            throw $e;
        }
    }
}
