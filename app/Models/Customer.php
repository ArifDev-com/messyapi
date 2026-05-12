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
    ];

    function messages()
    {

    }
}
