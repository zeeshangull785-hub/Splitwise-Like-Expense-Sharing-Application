<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Settlement extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'settlements';

    protected $fillable = [
        'group_id',
        'paid_by',
        'paid_to',
        'amount',
        'note',
    ];

    protected $casts = [
        'amount' => 'float',
    ];

    public function group()
    {
        return $this->belongsTo(Group::class, 'group_id', '_id');
    }

    public function payer()
    {
        return $this->belongsTo(User::class, 'paid_by', '_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'paid_to', '_id');
    }
}
