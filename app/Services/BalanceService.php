<?php

namespace App\Services;

use App\Models\Group;
use App\Models\Expense;
use App\Models\Settlement;
use App\Models\User;

class BalanceService
{
    /**
     * Calculate net balance for every member in the group.
     * Balance > 0 : User should receive money.
     * Balance < 0 : User owes money.
     * Balance = 0 : User is settled.
     */
    public function getGroupBalances(Group $group): array
    {
        $memberIds = array_map('strval', $group->member_ids ?? []);
        $balances = [];

        foreach ($memberIds as $id) {
            $balances[$id] = 0.0;
        }

        // 1. Calculate from expenses
        $expenses = Expense::where('group_id', (string)$group->_id)->get();
        foreach ($expenses as $expense) {
            $payerId = (string)$expense->paid_by;
            $amount = (float)$expense->amount;

            // Payer gets credit for what they paid
            if (isset($balances[$payerId])) {
                $balances[$payerId] += $amount;
            } else {
                $balances[$payerId] = $amount;
            }

            // Each participant owes their share
            foreach ($expense->participants ?? [] as $participant) {
                $uId = (string)($participant['user_id'] ?? '');
                $share = (float)($participant['amount'] ?? 0);
                if (isset($balances[$uId])) {
                    $balances[$uId] -= $share;
                } else {
                    $balances[$uId] = -$share;
                }
            }
        }

        // 2. Adjust for settlements
        // Settlement: payer gave money to receiver.
        // Payer's balance increases (they paid their debt)
        // Receiver's balance decreases (they collected what they were owed)
        $settlements = Settlement::where('group_id', (string)$group->_id)->get();
        foreach ($settlements as $settlement) {
            $payerId = (string)$settlement->paid_by;
            $receiverId = (string)$settlement->paid_to;
            $amount = (float)$settlement->amount;

            if (isset($balances[$payerId])) {
                $balances[$payerId] += $amount;
            }
            if (isset($balances[$receiverId])) {
                $balances[$receiverId] -= $amount;
            }
        }

        // Load user models for names
        $users = User::whereIn('_id', array_keys($balances))->get()->keyBy(fn($u) => (string)$u->_id);

        $result = [];
        foreach ($balances as $userId => $balance) {
            $user = $users->get($userId);
            $result[] = [
                'user_id' => $userId,
                'name' => $user?->name ?? 'Unknown',
                'balance' => round($balance, 2),
            ];
        }

        return $result;
    }

    /**
     * Compute simplified debts (who owes whom) using a greedy min-cash-flow algorithm.
     * This minimizes the total number of transactions required to settle all debts.
     */
    public function getSimplifiedDebts(Group $group): array
    {
        $rawBalances = $this->getGroupBalances($group);

        // Separate debtors (negative balance) and creditors (positive balance)
        $debtors = [];
        $creditors = [];

        foreach ($rawBalances as $item) {
            $val = round((float)$item['balance'], 2);
            if ($val < -0.009) {
                $debtors[] = [
                    'user_id' => $item['user_id'],
                    'name' => $item['name'],
                    'amount' => abs($val),
                ];
            } elseif ($val > 0.009) {
                $creditors[] = [
                    'user_id' => $item['user_id'],
                    'name' => $item['name'],
                    'amount' => $val,
                ];
            }
        }

        // Sort descending by amount to optimize matches
        usort($debtors, fn($a, $b) => $b['amount'] <=> $a['amount']);
        usort($creditors, fn($a, $b) => $b['amount'] <=> $a['amount']);

        $transactions = [];
        $d = 0;
        $c = 0;

        while ($d < count($debtors) && $c < count($creditors)) {
            $debtorAmount = $debtors[$d]['amount'];
            $creditorAmount = $creditors[$c]['amount'];

            $settleAmount = min($debtorAmount, $creditorAmount);

            if ($settleAmount > 0.009) {
                $transactions[] = [
                    'from_user_id' => $debtors[$d]['user_id'],
                    'from_name' => $debtors[$d]['name'],
                    'to_user_id' => $creditors[$c]['user_id'],
                    'to_name' => $creditors[$c]['name'],
                    'amount' => round($settleAmount, 2),
                ];
            }

            $debtors[$d]['amount'] = round($debtors[$d]['amount'] - $settleAmount, 2);
            $creditors[$c]['amount'] = round($creditors[$c]['amount'] - $settleAmount, 2);

            if ($debtors[$d]['amount'] <= 0.009) {
                $d++;
            }
            if ($creditors[$c]['amount'] <= 0.009) {
                $c++;
            }
        }

        return $transactions;
    }
}
