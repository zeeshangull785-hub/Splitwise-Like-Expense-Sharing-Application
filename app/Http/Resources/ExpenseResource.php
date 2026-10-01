<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\User;

class ExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $participants = $this->participants ?? [];
        $participantUserIds = array_column($participants, 'user_id');
        $users = User::whereIn('_id', $participantUserIds)->get()->keyBy(fn($u) => (string)$u->_id);

        $detailedParticipants = array_map(function ($p) use ($users) {
            $uId = (string)($p['user_id'] ?? '');
            $user = $users->get($uId);
            return [
                'user_id' => $uId,
                'name' => $user?->name ?? 'Unknown',
                'amount' => round((float)($p['amount'] ?? 0), 2),
                'percentage' => isset($p['percentage']) ? round((float)$p['percentage'], 2) : null,
            ];
        }, $participants);

        return [
            'id' => (string)$this->_id,
            'group_id' => (string)$this->group_id,
            'description' => $this->description,
            'amount' => round((float)$this->amount, 2),
            'paid_by' => (string)$this->paid_by,
            'payer' => new UserResource($this->payer),
            'split_type' => $this->split_type,
            'category' => $this->category ?? 'Other',
            'receipt_url' => $this->receipt_path ? url('storage/' . $this->receipt_path) : null,
            'participants' => $detailedParticipants,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
