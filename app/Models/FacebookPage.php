<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FacebookPage extends Model
{
    protected $fillable = [
        'facebook_account_id',
        'page_id',
        'name',
        'access_token',
        'category',
        'is_active',
        'webhook_data',
        'auto_reply_enabled',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'webhook_data' => 'array',
        'auto_reply_enabled' => 'boolean',
    ];

    public function facebookAccount(): BelongsTo
    {
        return $this->belongsTo(FacebookAccount::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(FacebookMessage::class);
    }
}
