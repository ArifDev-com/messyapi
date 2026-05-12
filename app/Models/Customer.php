<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = [
        'platform',
        'platform_user_id',
        'name',
        'email',
        'phone',
        'profile_data',
        'last_message_at',
        'auto_reply_enabled',
    ];
    protected $casts = [
        'last_message_at' => 'datetime',
        'auto_reply_enabled' => 'boolean',
        'profile_data' => 'array',
    ];

    public function messages()
    {
        return $this->hasMany(FacebookMessage::class);
    }
}
