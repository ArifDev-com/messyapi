<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FacebookAccount extends Model
{
    protected $fillable = [
        'user_id',
        'facebook_user_id',
        'access_token',
        'name',
        'email',
        'pages_data',
        'token_expires_at',
    ];

    protected $casts = [
        'pages_data' => 'array',
        'token_expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pages(): HasMany
    {
        return $this->hasMany(FacebookPage::class);
    }
}
