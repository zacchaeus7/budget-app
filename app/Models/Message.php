<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'admin_id',
        'user_id',
        'month',
        'year',
        'monthly_budget_amount',
        'consumed_amount',
        'consumed_percentage',
        'message',
        'sent_at',
        'read_at',
    ];

    protected $casts = [
        'month' => 'integer',
        'year' => 'integer',
        'monthly_budget_amount' => 'decimal:2',
        'consumed_amount' => 'decimal:2',
        'consumed_percentage' => 'decimal:2',
        'sent_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
