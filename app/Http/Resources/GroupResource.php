<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\User;

class GroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $memberIds = array_map('strval', $this->member_ids ?? []);
        $members = User::whereIn('_id', $memberIds)->get();

        return [
            'id' => (string)$this->_id,
            'name' => $this->name,
            'description' => $this->description,
            'owner_id' => (string)$this->owner_id,
            'owner' => new UserResource($this->owner),
            'member_ids' => $memberIds,
            'members' => UserResource::collection($members),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
