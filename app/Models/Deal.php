<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Deal extends Model
{
    /** @use HasFactory<\Database\Factories\DealFactory> */
    use HasFactory;

    public const STAGES = ['lead', 'qualified', 'proposal', 'negotiation', 'won', 'lost'];

    public const OPEN_STAGES = ['lead', 'qualified', 'proposal', 'negotiation'];

    protected $fillable = ['customer_id', 'title', 'value', 'stage', 'expected_close_date', 'description', 'lost_reason'];

    protected function casts(): array
    {
        return ['value' => 'decimal:2', 'expected_close_date' => 'date:Y-m-d'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->whereHas('customer', fn (Builder $customers) => $customers->ownedBy($user));
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('stage', self::OPEN_STAGES);
    }
}
