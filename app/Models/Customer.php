<?php

namespace App\Models;

use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    protected $fillable = ['user_id', 'name', 'email', 'phone', 'company', 'industry', 'city', 'status', 'notes', 'translations'];

    protected function casts(): array
    {
        return ['translations' => 'array'];
    }

    public function localized(string $attribute): mixed
    {
        $translations = $this->getAttribute('translations') ?? [];
        $localeValues = $translations[app()->getLocale()] ?? [];

        return array_key_exists($attribute, $localeValues) ? $localeValues[$attribute] : $this->getAttribute($attribute);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }
}
