<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SmsMessage extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'phone',
        'message',
        'status',
        'attempts',
        'error',
        'sent_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'sent_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }
}
