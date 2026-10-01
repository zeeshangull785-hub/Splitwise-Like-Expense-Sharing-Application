<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Auth\Authenticatable;

class User extends Model implements AuthenticatableContract
{
    use Authenticatable;

    protected $connection = 'mongodb';
    protected $collection = 'users';

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
    ];

    /**
     * User's active tokens in the tokens collection.
     */
    public function tokens()
    {
        return $this->hasMany(Token::class, 'user_id', '_id');
    }

    /**
     * Groups owned by the user.
     */
    public function ownedGroups()
    {
        return $this->hasMany(Group::class, 'owner_id', '_id');
    }

    /**
     * Expenses where user was the payer.
     */
    public function paidExpenses()
    {
        return $this->hasMany(Expense::class, 'paid_by', '_id');
    }

    /**
     * Settlements paid by the user.
     */
    public function paidSettlements()
    {
        return $this->hasMany(Settlement::class, 'paid_by', '_id');
    }

    /**
     * Settlements received by the user.
     */
    public function receivedSettlements()
    {
        return $this->hasMany(Settlement::class, 'paid_to', '_id');
    }
}
