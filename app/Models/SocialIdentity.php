<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialIdentity extends Model
{
    public const PROVIDER_GOOGLE = 'google';

    protected $fillable = [
        'user_id',
        'provider',
        'provider_subject',
        'provider_email',
        'provider_email_verified',
        'linked_at',
        'last_login_at',
    ];

    protected function casts(): array
    {
        return [
            'provider_email_verified' => 'boolean',
            'linked_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
