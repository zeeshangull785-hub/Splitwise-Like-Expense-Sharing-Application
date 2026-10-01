<?php

namespace App\Services;

use App\Models\User;
use App\Models\Group;
use App\Models\Expense;
use App\Exceptions\ApiException;
use App\Exceptions\InvalidExpenseSplitException;
use App\Exceptions\UnauthorizedGroupAccessException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExpenseService
{
    /**
     * Validate split rules and calculate participant shares.
     */
    public function calculateParticipants(float $totalAmount, string $splitType, array $rawParticipants, Group $group): array
    {
        $groupMemberIds = array_map('strval', $group->member_ids ?? []);

        if (empty($rawParticipants)) {
            throw new InvalidExpenseSplitException('At least one participant is required.');
        }

        // Verify each participant is a member of the group and avoid duplicates
        $seen = [];
        foreach ($rawParticipants as $p) {
            $uId = (string)($p['user_id'] ?? '');
            if (!$uId || !in_array($uId, $groupMemberIds, true)) {
                throw new InvalidExpenseSplitException("Participant {$uId} is not a member of the group.");
            }
            if (isset($seen[$uId])) {
                throw new InvalidExpenseSplitException("Duplicate participant {$uId} specified.");
            }
            $seen[$uId] = true;
        }

        $count = count($rawParticipants);
        $calculated = [];

        switch ($splitType) {
            case 'equal':
                // Equal split with proper remainder distribution in cents
                $cents = (int)round($totalAmount * 100);
                $baseShareCents = intdiv($cents, $count);
                $remainderCents = $cents % $count;

                for ($i = 0; $i < $count; $i++) {
                    $uId = (string)$rawParticipants[$i]['user_id'];
                    $shareCents = $baseShareCents + ($i < $remainderCents ? 1 : 0);
                    $calculated[] = [
                        'user_id' => $uId,
                        'amount' => round($shareCents / 100, 2),
                    ];
                }
                break;

            case 'exact':
                $sum = 0.0;
                foreach ($rawParticipants as $p) {
                    if (!isset($p['amount']) || !is_numeric($p['amount']) || (float)$p['amount'] < 0) {
                        throw new InvalidExpenseSplitException("Exact split requires non-negative amount for participant {$p['user_id']}.");
                    }
                    $amount = round((float)$p['amount'], 2);
                    $sum += $amount;
                    $calculated[] = [
                        'user_id' => (string)$p['user_id'],
                        'amount' => $amount,
                    ];
                }

                // Verify exact sum equals total amount (within 0.01 tolerance)
                if (abs($sum - $totalAmount) > 0.01) {
                    throw new InvalidExpenseSplitException("The sum of participant amounts ({$sum}) does not match the total expense amount ({$totalAmount}).");
                }
                break;

            case 'percentage':
                $totalPercentage = 0.0;
                foreach ($rawParticipants as $p) {
                    if (!isset($p['percentage']) || !is_numeric($p['percentage']) || (float)$p['percentage'] < 0) {
                        throw new InvalidExpenseSplitException("Percentage split requires percentage for participant {$p['user_id']}.");
                    }
                    $pct = round((float)$p['percentage'], 2);
                    $totalPercentage += $pct;
                }

                // Verify total percentage equals 100% (within 0.01 tolerance)
                if (abs($totalPercentage - 100.0) > 0.01) {
                    throw new InvalidExpenseSplitException("The sum of participant percentages ({$totalPercentage}%) must equal 100%.");
                }

                $runningSum = 0.0;
                for ($i = 0; $i < $count; $i++) {
                    $p = $rawParticipants[$i];
                    $pct = round((float)$p['percentage'], 2);
                    if ($i === $count - 1) {
                        $share = round($totalAmount - $runningSum, 2);
                    } else {
                        $share = round(($totalAmount * $pct) / 100, 2);
                        $runningSum += $share;
                    }

                    $calculated[] = [
                        'user_id' => (string)$p['user_id'],
                        'percentage' => $pct,
                        'amount' => $share,
                    ];
                }
                break;

            default:
                throw new InvalidExpenseSplitException("Unsupported split type: {$splitType}");
        }

        return $calculated;
    }

    /**
     * Create an expense atomically inside a MongoDB transaction (or standalone fallback).
     */
    public function createExpense(Group $group, User $actor, array $data, ?string $receiptPath = null): Expense
    {
        if (!$group->hasMember((string)$actor->_id)) {
            throw new UnauthorizedGroupAccessException('You must be a group member to create an expense.');
        }

        $payerId = !empty($data['paid_by']) ? (string)$data['paid_by'] : (string)$actor->_id;
        if (!$group->hasMember($payerId)) {
            throw new ApiException('The payer must be a member of the group.', 422);
        }

        $amount = round((float)$data['amount'], 2);
        $splitType = $data['split_type'];
        $participants = $this->calculateParticipants($amount, $splitType, $data['participants'], $group);

        $expenseData = [
            'group_id' => (string)$group->_id,
            'description' => trim($data['description']),
            'amount' => $amount,
            'paid_by' => $payerId,
            'split_type' => $splitType,
            'participants' => $participants,
            'category' => $data['category'] ?? 'Other',
            'receipt_path' => $receiptPath,
        ];

        return $this->executeAtomically(function () use ($expenseData) {
            $expense = Expense::create($expenseData);
            Log::info('Expense created', ['expense_id' => (string)$expense->_id, 'group_id' => $expenseData['group_id']]);
            return $expense;
        });
    }

    /**
     * Update an existing expense.
     */
    public function updateExpense(Expense $expense, User $actor, array $data, ?string $receiptPath = null): Expense
    {
        $group = $expense->group;
        if (!$group || !$group->hasMember((string)$actor->_id)) {
            throw new UnauthorizedGroupAccessException('You must be a group member to update this expense.');
        }

        $amount = isset($data['amount']) ? round((float)$data['amount'], 2) : (float)$expense->amount;
        $splitType = $data['split_type'] ?? $expense->split_type;
        $payerId = isset($data['paid_by']) ? (string)$data['paid_by'] : (string)$expense->paid_by;

        if (!$group->hasMember($payerId)) {
            throw new ApiException('The payer must be a member of the group.', 422);
        }

        $rawParticipants = $data['participants'] ?? $expense->participants;
        $participants = $this->calculateParticipants($amount, $splitType, $rawParticipants, $group);

        return $this->executeAtomically(function () use ($expense, $data, $amount, $splitType, $payerId, $participants, $receiptPath) {
            $expense->description = $data['description'] ?? $expense->description;
            $expense->amount = $amount;
            $expense->paid_by = $payerId;
            $expense->split_type = $splitType;
            $expense->participants = $participants;
            if (isset($data['category'])) {
                $expense->category = $data['category'];
            }
            if ($receiptPath) {
                $expense->receipt_path = $receiptPath;
            }
            $expense->save();

            Log::info('Expense updated', ['expense_id' => (string)$expense->_id]);
            return $expense;
        });
    }

    /**
     * Delete an expense.
     */
    public function deleteExpense(Expense $expense, User $actor): void
    {
        $group = $expense->group;
        if (!$group || !$group->hasMember((string)$actor->_id)) {
            throw new UnauthorizedGroupAccessException('You must be a group member to delete this expense.');
        }

        $this->executeAtomically(function () use ($expense) {
            $id = (string)$expense->_id;
            $expense->delete();
            Log::info('Expense deleted', ['expense_id' => $id]);
        });
    }

    /**
     * Execute callback in transaction if replica set is active, else execute directly.
     */
    protected function executeAtomically(callable $callback)
    {
        try {
            return DB::connection('mongodb')->transaction($callback);
        } catch (\Throwable $e) {
            // If replica set is not configured (standalone dev instance), execute directly
            if (str_contains($e->getMessage(), 'replica set') || str_contains($e->getMessage(), 'standalone')) {
                return $callback();
            }
            throw $e;
        }
    }
}
