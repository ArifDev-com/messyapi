<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

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

    static function getOrCreateCustomerWhatsapp(string $senderId): Customer
    {
        return Customer::updateOrCreate(
            [
                'platform' => 'whatsapp',
                'platform_user_id' => $senderId,
            ], [
                'name' => $senderId,
                'profile_data' => null,
                'last_message_at' => now(),
            ]
        );
    }
    /**
     * Get or create customer from Facebook sender
     */
    static function getOrCreateCustomerFacebook(string $senderId, $pageId): Customer
    {
        // Try to get customer info from Facebook
        $customerInfo = null;
        try {
            $page = FacebookPage::where('page_id', $pageId)->first();
            if ($page) {
                // Use cURL instead of Facebook SDK
                $url = "https://graph.facebook.com/v18.0/{$senderId}?fields=name,first_name,last_name,profile_pic&access_token=" . $page->access_token;

                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
//                curl_setopt($ch, CURLOPT_CAINFO, '/Users/ariful/cacert.pem');

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($httpCode === 200 && $response) {
                    $userData = json_decode($response, true);
                    $customerInfo = [
                        'name' => $userData['name'] ?? null,
                        'profile_data' => [
                            'first_name' => $userData['first_name'] ?? null,
                            'last_name' => $userData['last_name'] ?? null,
                            'profile_pic' => $userData['profile_pic'] ?? null,
                        ]
                    ];
                } else {
                    Log::warning('Could not fetch customer info', ['http_code' => $httpCode, 'response' => $response]);
                }
            }
        } catch (\Exception $e) {
            Log::warning('Could not fetch customer info: ' . $e->getMessage());
        }

        return Customer::updateOrCreate(
            [
                'platform' => 'facebook',
                'platform_user_id' => $senderId,
            ],
            [
                'name' => $customerInfo['name'] ?? null,
                'profile_data' => $customerInfo['profile_data'] ?? null,
                'last_message_at' => now(),
            ]
        );
    }
}
