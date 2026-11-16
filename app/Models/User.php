<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'translations'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function deals(): HasManyThrough
    {
        return $this->hasManyThrough(Deal::class, Customer::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'translations' => 'array',
        ];
    }

    public function localized(string $attribute): mixed
    {
        $translations = $this->getAttribute('translations') ?? [];
        $localeValues = $translations[app()->getLocale()] ?? [];

        return array_key_exists($attribute, $localeValues) ? $localeValues[$attribute] : $this->getAttribute($attribute);
    }
}
