<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'activo',
        'status',
        'email_verified_at',
        'password_changed_at',
        'failed_attempts',
        'locked_until',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

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
            'activo' => 'boolean',
            'password_changed_at' => 'datetime',
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * Vínculo 1:1 con la ficha comercial del cliente (zona /cuenta/*).
     */
    public function customerLink(): HasOne
    {
        return $this->hasOne(CustomerUserLink::class);
    }

    /**
     * Fichas comerciales vinculadas (hoy 1:1; preparado para N:M futuro).
     */
    public function clientes(): BelongsToMany
    {
        return $this->belongsToMany(Cliente::class, 'customer_user_links')
            ->withPivot('estado', 'rol')
            ->withTimestamps();
    }

    /**
     * Tokens de un solo uso emitidos para esta cuenta.
     */
    public function oneTimeTokens(): HasMany
    {
        return $this->hasMany(OneTimeToken::class);
    }

    /**
     * Identidades externas vinculadas (Google OIDC).
     */
    public function socialIdentities(): HasMany
    {
        return $this->hasMany(SocialIdentity::class);
    }

    /**
     * Carrito de cotización (zona /cuenta/carrito, 1:1).
     */
    public function carrito(): HasOne
    {
        return $this->hasOne(Carrito::class);
    }

    /**
     * ¿La cuenta puede iniciar sesión en la zona pública?
     */
    public function puedeAccederCuenta(): bool
    {
        if (! $this->activo || $this->status !== 'ACTIVE' || ! $this->hasVerifiedEmail()) {
            return false;
        }

        if ($this->locked_until !== null && $this->locked_until->isFuture()) {
            return false;
        }

        return true;
    }
}
