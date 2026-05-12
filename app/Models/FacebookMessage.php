<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacebookMessage extends Model
{
    protected $fillable = [
        'facebook_page_id',
        'customer_id',
        'message_id',
        'sender_id',
        'recipient_id',
        'message_text',
        'attachments',
        'sent_at',
        'is_echo',
        'is_reply',
        'reply_text',
        'replied_at',
    ];

    protected $casts = [
        'attachments' => 'array',
        'sent_at' => 'datetime',
        'is_reply' => 'boolean',
        'is_echo' => 'boolean',
        'replied_at' => 'datetime',
        'sent_at' => 'datetime'
    ];

    public function facebookPage(): BelongsTo
    {
        return $this->belongsTo(FacebookPage::class);
    }
    function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
