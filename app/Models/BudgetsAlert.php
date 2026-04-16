<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class BudgetsAlert extends Model
{
    use HasFactory;

    protected $table = 'budgets_alerts';

    protected $fillable = [
        'budget_id',
        'threshold_percentage',
        'notified',
    ];

    protected $casts = [
        'notified' => 'boolean',
    ];

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budgets::class, 'budget_id');
    }
}
