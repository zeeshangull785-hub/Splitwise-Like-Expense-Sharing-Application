<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SettlementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string)$this->_id,
            'group_id' => (string)$this->group_id,
            'paid_by' => (string)$this->paid_by,
            'payer' => new UserResource($this->payer),
            'paid_to' => (string)$this->paid_to,
            'receiver' => new UserResource($this->receiver),
            'amount' => round((float)$this->amount, 2),
            'note' => $this->note,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
