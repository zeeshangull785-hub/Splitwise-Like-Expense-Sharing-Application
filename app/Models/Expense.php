<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Expense extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'expenses';

    protected $fillable = [
        'group_id',
        'description',
        'amount',
        'paid_by',
        'split_type',
        'participants',
        'category',
        'receipt_path',
    ];

    protected $casts = [
        'amount' => 'float',
        'participants' => 'array',
    ];

    public function group()
    {
        return $this->belongsTo(Group::class, 'group_id', '_id');
    }

    public function payer()
    {
        return $this->belongsTo(User::class, 'paid_by', '_id');
    }
}
