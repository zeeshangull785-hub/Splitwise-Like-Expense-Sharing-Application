<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Group extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'groups';

    protected $fillable = [
        'name',
        'description',
        'owner_id',
        'member_ids',
    ];

    protected $casts = [
        'member_ids' => 'array',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id', '_id');
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class, 'group_id', '_id');
    }

    public function settlements()
    {
        return $this->hasMany(Settlement::class, 'group_id', '_id');
    }

    /**
     * Check if a given user is a member of the group.
     */
    public function hasMember(string $userId): bool
    {
        $memberIds = array_map('strval', $this->member_ids ?? []);
        return in_array((string)$userId, $memberIds, true);
    }

    /**
     * Check if a given user is the owner of the group.
     */
    public function isOwner(string $userId): bool
    {
        return (string)$this->owner_id === (string)$userId;
    }
}
