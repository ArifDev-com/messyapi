<?php

namespace App\Http\Controllers;

use App\Services\LaravelPersistentDataHandler;
use App\Models\FacebookAccount;
use App\Models\FacebookPage;
use App\Models\Customer;
use App\Models\FacebookMessage;
use Facebook\Facebook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class FacebookController extends Controller
{
    protected Facebook $fb;

    public function __construct()
    {
        // Ensure Laravel session is started
        if (!session()->isStarted()) {
            session()->start();
        }

//        $this->fb = new Facebook([
//            'app_id' => config('services.facebook.app_id'),
//            'app_secret' => config('services.facebook.app_secret'),
//            'default_graph_version' => 'v18.0',
//            'persistent_data_handler' => new LaravelPersistentDataHandler(),
//        ]);
    }

    /**
     * Redirect to Facebook OAuth
     */
    public function connect()
    {
        // Clear any previous Facebook session data
        $this->clearFacebookSessionData();

        // Manually generate and store state in Laravel session
        $state = md5(uniqid(rand(), true));
        session(['fb_state' => $state]);

        $permissions = [
            'pages_show_list',
            'pages_messaging',
            'pages_manage_metadata',
        ];

        // Build URL manually to avoid SDK session issues
        $loginUrl = 'https://www.facebook.com/v18.0/dialog/oauth?' . http_build_query([
                'client_id' => config('services.facebook.app_id'),
                'redirect_uri' => route('facebook.callback'),
                'state' => $state,
                'scope' => implode(',', $permissions),
                'response_type' => 'code',
            ]);

        // Log for debugging
        Log::info('Facebook OAuth initiated', [
            'state' => $state,
            'redirect_uri' => route('facebook.callback'),
            'session_id' => session()->getId()
        ]);

        return redirect($loginUrl);
    }

    /**
     * Handle Facebook OAuth callback
     */
    public function callback(Request $request)
    {
        // Log incoming request
        Log::info('Facebook OAuth callback received', [
            'has_code' => $request->has('code'),
            'has_state' => $request->has('state'),
            'has_error' => $request->has('error'),
            'session_id' => session()->getId()
        ]);

        // Check for errors from Facebook
        if ($request->has('error')) {
            $error = $request->get('error');
            $errorReason = $request->get('error_reason', '');
            $errorDescription = $request->get('error_description', '');

            Log::error('Facebook OAuth error', [
                'error' => $error,
                'reason' => $errorReason,
                'description' => $errorDescription
            ]);

            return redirect()->route('dashboard')->with('error', "Facebook authorization failed: {$errorDescription}");
        }

        // Get state from Laravel session
        $expectedState = session('fb_state');
        $receivedState = $request->get('state');

        // Validate state
        if (!$expectedState || $expectedState !== $receivedState) {
            Log::error('State validation failed', [
                'expected' => $expectedState,
                'received' => $receivedState,
                'session_id' => session()->getId()
            ]);
            return redirect()->route('dashboard')->with('error', 'Security validation failed. Please try again.');
        }

        // Clear the state from session
        session()->forget('fb_state');

        // Exchange code for access token
        $code = $request->get('code');

        if (!$code) {
            return redirect()->route('dashboard')->with('error', 'Authorization code not received');
        }

        try {
            // Get access token using curl
            $tokenUrl = 'https://graph.facebook.com/v18.0/oauth/access_token?' . http_build_query([
                    'client_id' => config('services.facebook.app_id'),
                    'client_secret' => config('services.facebook.app_secret'),
                    'redirect_uri' => route('facebook.callback'),
                    'code' => $code,
                ]);

            $response = file_get_contents($tokenUrl);
            if ($response === false) {
                throw new \Exception('Failed to fetch access token');
            }

            $tokenData = json_decode($response, true);

            if (!isset($tokenData['access_token'])) {
                throw new \Exception('Failed to get access token: ' . ($tokenData['error']['message'] ?? 'Unknown error'));
            }

            $accessToken = $tokenData['access_token'];

            // Get user info
            $userResponse = file_get_contents('https://graph.facebook.com/v18.0/me?fields=id,name,email&access_token=' . $accessToken);
            if ($userResponse === false) {
                throw new \Exception('Failed to fetch user info');
            }

            $userData = json_decode($userResponse, true);

            if (!isset($userData['id'])) {
                throw new \Exception('Failed to get user ID from Facebook');
            }

            // Get long-lived access token
            $longLivedTokenUrl = 'https://graph.facebook.com/v18.0/oauth/access_token?' . http_build_query([
                    'grant_type' => 'fb_exchange_token',
                    'client_id' => config('services.facebook.app_id'),
                    'client_secret' => config('services.facebook.app_secret'),
                    'fb_exchange_token' => $accessToken,
                ]);

            $longLivedResponse = file_get_contents($longLivedTokenUrl);
            if ($longLivedResponse === false) {
                throw new \Exception('Failed to fetch long-lived token');
            }

            $longLivedData = json_decode($longLivedResponse, true);
            $longLivedAccessToken = $longLivedData['access_token'] ?? $accessToken;

            // Get user's pages
            $pagesResponse = file_get_contents('https://graph.facebook.com/v18.0/me/accounts?access_token=' . $longLivedAccessToken);
            if ($pagesResponse === false) {
                throw new \Exception('Failed to fetch pages');
            }

            $pagesData = json_decode($pagesResponse, true);
            $pages = $pagesData['data'] ?? [];

            // Save or update Facebook account
            $facebookAccount = FacebookAccount::updateOrCreate(
                [
                    'user_id' => Auth::id(),
                    'facebook_user_id' => $userData['id'],
                ],
                [
                    'access_token' => $longLivedAccessToken,
                    'name' => $userData['name'],
                    'email' => $userData['email'] ?? null,
                    'pages_data' => $pages,
                    'token_expires_at' => now()->addDays(60),
                ]
            );

            // Save pages
            foreach ($pages as $pageData) {
                FacebookPage::updateOrCreate(
                    [
                        'facebook_account_id' => $facebookAccount->id,
                        'page_id' => $pageData['id'],
                    ],
                    [
                        'name' => $pageData['name'],
                        'access_token' => $pageData['access_token'],
                        'category' => $pageData['category'] ?? null,
                        'is_active' => true,
                    ]
                );
            }

            // Clear any remaining Facebook session data
            $this->clearFacebookSessionData();

            Log::info('Facebook account connected successfully', [
                'user_id' => Auth::id(),
                'facebook_user_id' => $userData['id'],
                'pages_count' => count($pages)
            ]);

            return redirect()->route('facebook.pages')->with('success', 'Facebook account connected successfully!');

        } catch (\Exception $e) {
            Log::error('Facebook OAuth Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->route('dashboard')->with('error', 'Failed to connect Facebook: ' . $e->getMessage());
        }
    }

    /**
     * Clear Facebook related session data
     */
    protected function clearFacebookSessionData(): void
    {
        session()->forget([
            'fb_state',
            'fb_code',
            'fb_access_token',
            'fb_oauth_state',
            'FBRLH_state',
        ]);
    }

    /**
     * Show user's Facebook pages
     */
    public function pages()
    {
        $facebookAccount = Auth::user()->facebookAccount;

        if (!$facebookAccount) {
            return redirect()->route('dashboard')->with('error', 'Please connect your Facebook account first.');
        }

        $pages = $facebookAccount->pages;

        return view('facebook.pages', compact('pages'));
    }

    /**
     * Setup webhook for a page
     */
    public function setupWebhook(FacebookPage $page)
    {
        if ($page->facebookAccount->user_id !== Auth::id()) {
            abort(403);
        }

        try {
            // Direct API call using cURL
            $url = 'https://graph.facebook.com/v18.0/' . $page->page_id . '/subscribed_apps';

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                'subscribed_fields' => 'messages,messaging_postbacks,messaging_optins,message_deliveries,message_reads',
                'access_token' => $page->access_token
            ]));
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
//            curl_setopt($ch, CURLOPT_CAINFO, '/Users/ariful/cacert.pem');

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode == 200) {
                $page->update([
                    'webhook_data' => [
                        'subscribed' => true,
                        'subscribed_at' => now(),
                    ]
                ]);
                return back()->with('success', 'Webhook setup successfully for ' . $page->name);
            } else {
                throw new \Exception("HTTP {$httpCode}: {$response}");
            }

        } catch (\Exception $e) {
            Log::error('Webhook setup error: ' . $e->getMessage());
            return back()->with('error', 'Failed to setup webhook: ' . $e->getMessage());
        }
    }

    /**
     * Handle Facebook webhooks
     */
    public function webhook(Request $request)
    {
        Log::info('Webhook received', ['content' => json_decode($request->getContent())]);
        $mode = $request->get('hub_mode');
        $token = $request->get('hub_verify_token');
        $challenge = $request->get('hub_challenge');

        // Webhook verification (GET request)
        if ($mode === 'subscribe' && $token === config('services.facebook.webhook_verify_token')) {
            return response($challenge, 200);
        }

        // Handle webhook events (POST request)
        $body = json_decode($request->getContent(), true);

        if ($body && isset($body['entry'])) {
            foreach ($body['entry'] as $entry) {
                if (isset($entry['messaging'])) {
                    foreach ($entry['messaging'] as $messagingEvent) {
                        $this->handleMessagingEvent($messagingEvent, $entry['id']);
                    }
                }

                // Handle WhatsApp messages if applicable
                if (isset($entry['changes'])) {
                    $this->handleWhatsAppEvent($entry);
                }
            }
        }

        return response('OK', 200);
    }

    /**
     * Handle messaging events from webhooks (both customer and echo)
     */
    protected function handleMessagingEvent(array $event, $pageId)
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
            $customer = $this->getOrCreateCustomer($senderId, $event, $pageId);

            // Send reply only if both page and customer auto reply are enabled
            $page = FacebookPage::where('page_id', $recipientId)->first();
            if ($page && $page->auto_reply_enabled && $customer && $customer->auto_reply_enabled) {
                $this->sendMessage($page, $senderId);
            }
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

        // If this is a reply to a previous message, update it
        if (!$isEcho && isset($replyText)) {
            // Find the original customer message and mark as replied
            FacebookMessage::where('message_id', $messageId)->update([
                'is_reply' => true,
                'reply_text' => $replyText,
                'replied_at' => now(),
            ]);
        }
    }

    /**
     * Get or create customer from Facebook sender
     */
    protected function getOrCreateCustomer(string $senderId, array $event, $pageId): Customer
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

    /**
     * Send message via Facebook API
     */
    static public function sendMessageWithAttachments(FacebookPage $page, string $recipientId, ?string $messageText = null, array $images = [])
    {
        try {
            if (empty($images)) {
                return self::sendMessage($page, $recipientId, $messageText);
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
                usleep(500000); // 0.5 seconds
            }

            return $results;

        } catch (\Exception $e) {
            Log::error('Failed to send message with attachments: ' . $e->getMessage());
            return null;
        }
    }

// Send text message
    static protected function sendTextMessage(FacebookPage $page, string $recipientId, string $messageText)
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
    static protected function sendImageMessage(FacebookPage $page, string $recipientId, string $imageUrl)
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
    static protected function makeCurlRequest($url, $postData)
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

    static protected function getImageUrl($image)
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

// And make sure to clean up old temporary images
    static protected function cleanupTempImages()
    {
        $tempDir = public_path('storage/temp_images');
        if (file_exists($tempDir)) {
            $files = glob($tempDir . '/*');
            foreach ($files as $file) {
                if (is_file($file) && time() - filemtime($file) > 3600) {
                    unlink($file);
                }
            }
        }
    }

// Keep your existing sendMessage for text only
    static public function sendMessage(FacebookPage $page, string $recipientId, ?string $messageText = null)
    {
        if (!$messageText) {
            return null;
        }

        return self::sendTextMessage($page, $recipientId, $messageText);
    }


    /**
     * Disconnect Facebook account
     */
    public function disconnect()
    {
        $facebookAccount = Auth::user()->facebookAccount;

        if ($facebookAccount) {
            // Revoke token with Facebook using cURL
            try {
                $url = "https://graph.facebook.com/v18.0/{$facebookAccount->facebook_user_id}/permissions?access_token={$facebookAccount->access_token}";

                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');

                // SSL handling
//                if (app()->environment('local')) {
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
//                } else {
//                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
//                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
//                }

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($httpCode !== 200) {
                    Log::warning('Failed to revoke permissions', [
                        'http_code' => $httpCode,
                        'response' => $response
                    ]);
                    return redirect()->route('dashboard')->with('error', 'Failed: ' . $response);
                }

            } catch (\Exception $e) {
                Log::warning('Failed to revoke Facebook permissions: ' . $e->getMessage());
            }

            // Delete from database (cascades to pages and messages)
            $facebookAccount->delete();
        }

        $this->clearFacebookSessionData();

        return redirect()->route('dashboard')->with('success', 'Facebook account disconnected successfully.');
    }
}
