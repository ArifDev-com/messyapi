<?php
namespace  App\Services;

use Illuminate\Support\Facades\Log;
use App\Models\FacebookPage;
use App\Models\Customer;
use App\Models\FacebookMessage;

class FacebookService {
    /**
     * Handle messaging events from webhooks (both customer and echo)
     */
    public function handleMessagingEvent(array $event, $pageId)
    {
        $timestamp = $event['timestamp'];
        $isEcho = isset($event['message']['is_echo']) && $event['message']['is_echo'] === true;
        $customer = null;

        // Determine sender and recipient based on echo status
        if ($isEcho) {
            // This is our page's reply (echo)
            $senderId = $event['sender']['id']; // Our page
            $recipientId = $event['recipient']['id']; // Customer
            $messageText = $event['message']['text'] ?? '';
            $messageId = $event['message']['mid'] ?? null;

        } else {
            // This is a customer message
            if (!isset($event['message'])) {
                return; // Skip non-message events
            }

            $senderId = $event['sender']['id']; // Customer
            $recipientId = $event['recipient']['id']; // Our page
            $messageText = $event['message']['text'] ?? '';
            $messageId = $event['message']['mid'];

            // Get or create customer
            $customer = Customer::getOrCreateCustomerFacebook($senderId, $pageId);

            // Send reply only if both page and customer auto reply are enabled
            $page = FacebookPage::where('page_id', $recipientId)->first();
        }

        // Find the page
        $page = FacebookPage::where('page_id', $pageId)->first();
        if (!$page) {
            $page = FacebookPage::create([
                'facebook_account_id' => 1,
                'page_id' => $pageId,
                'name' => $pageId,
                'access_token' => $pageId,
            ]);
        }

        // Save the message (both customer and echo)
        $message = FacebookMessage::create([
            'facebook_page_id' => $page->id,
            'customer_id' => $customer?->id,
            'message_id' => $messageId,
            'sender_id' => $isEcho ? $senderId : $senderId,
            'recipient_id' => $isEcho ? $recipientId : $recipientId,
            'message_text' => $messageText ?? '',
            'attachments' => $event['message']['attachments'] ?? null,
            'is_echo' => $isEcho,
            'sent_at' => now()->createFromTimestampMs($timestamp),
        ]);

        if ($isEcho) {
            FacebookMessage::where([
                'facebook_page_id' => $page->id,
                'sender_id' => $senderId,
                'message_id' => '0',
            ])->delete();
        }
        // If this is a reply to a previous message, update it
        if (!$isEcho && isset($replyText)) {
            // Find the original customer message and mark as replied
            FacebookMessage::where('message_id', $messageId)->update([
                'is_reply' => true,
                'reply_text' => $replyText,
                'replied_at' => now(),
            ]);
        }
        if (!$isEcho) {
            if ($page?->auto_reply_enabled && $customer?->auto_reply_enabled) {
                dispatch(function () use ($message, $page, $senderId) {
                    $ai = app(AIService::class);
                    $replyText = $ai->generateMessage($page, $senderId, $message);
                    FacebookService::sendMessage($page, $senderId, $replyText);
                });
            }
        }
    }
    /**
     * Send message via Facebook API
     */
    static public function sendMessageWithAttachments(FacebookPage $page, string $recipientId, ?string $messageText = null, array $images = [])
    {
        try {
            if (empty($images) && !$messageText) {
                return null;
            }

            $url = "https://graph.facebook.com/v18.0/{$page->page_id}/messages";
            $results = [];

            // Send text message first (if provided) - only once
            if ($messageText) {
                $results[] = self::sendTextMessage($page, $recipientId, $messageText);
            }

            // Send each image as separate message with timeout
            foreach ($images as $image) {
                // Get the image URL directly without saving to disk
                $imageUrl = self::getImageUrl($image);
                if (!$imageUrl) {
                    Log::warning('Invalid image, skipping', ['image' => $image]);
                    continue;
                }

                $result = self::sendImageMessage($page, $recipientId, $imageUrl);
                $results[] = $result;

                // Small delay to avoid rate limiting
                if(count($images) > 1) sleep(1); // 0.5 seconds
            }

            return $results;

        } catch (\Exception $e) {
            Log::error('Failed to send message with attachments: ' . $e->getMessage());
            return null;
        }
    }

// Send text message
    static public function sendMessage(FacebookPage $page, string $recipientId, string $messageText)
    {
        return self::sendTextMessage($page, $recipientId, $messageText);
    }
    static public function sendTextMessage(FacebookPage $page, string $recipientId, string $messageText)
    {
        $url = "https://graph.facebook.com/v18.0/{$page->page_id}/messages";

        $postData = [
            'recipient' => json_encode(['id' => $recipientId]),
            'message' => json_encode(['text' => $messageText]),
            'access_token' => $page->access_token
        ];

        return self::makeCurlRequest($url, $postData);
    }

// Send image message
    static public function sendImageMessage(FacebookPage $page, string $recipientId, string $imageUrl)
    {
        $url = "https://graph.facebook.com/v18.0/{$page->page_id}/messages";

        $postData = [
            'recipient' => json_encode(['id' => $recipientId]),
            'message' => json_encode([
                'attachment' => [
                    'type' => 'image',
                    'payload' => ['url' => $imageUrl]
                ]
            ]),
            'access_token' => $page->access_token
        ];

        return self::makeCurlRequest($url, $postData);
    }

// Centralized cURL request with timeout
    static public function makeCurlRequest($url, $postData)
    {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($postData),
            CURLOPT_SSL_VERIFYPEER => false, // Only for local
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => 10, // 10 second timeout
            CURLOPT_CONNECTTIMEOUT => 5, // 5 second connection timeout
            CURLOPT_HEADER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            Log::error('cURL Error: ' . $curlError);
            return null;
        }

        if ($httpCode !== 200) {
            Log::error('Facebook API Error', [
                'http_code' => $httpCode,
                'response' => $response
            ]);
            return null;
        }
        Log::info('Facebook API Response', [$response]);

        return json_decode($response, true);
    }

    static public function getImageUrl($image)
    {
        // If it's already a public URL
        if (filter_var($image, FILTER_VALIDATE_URL)) {
            return $image;
        }

        // If it's an uploaded file, save and return public URL
        if ($image instanceof \Illuminate\Http\UploadedFile) {
            // Store in storage/app/public/temp
            $path = $image->store('temp_images', 'public');
            return url('storage/' . $path);
        }

        // If it's a local file path
        if (is_string($image) && file_exists($image)) {
            // Copy to public directory
            $filename = time() . '_' . uniqid() . '.' . pathinfo($image, PATHINFO_EXTENSION);
            $destination = public_path('storage/temp_images/' . $filename);

            if (!file_exists(public_path('storage/temp_images'))) {
                mkdir(public_path('storage/temp_images'), 0755, true);
            }

            copy($image, $destination);
            return url('storage/temp_images/' . $filename);
        }

        return null;
    }
}
