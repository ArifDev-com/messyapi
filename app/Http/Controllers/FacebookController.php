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
use App\Services\FacebookService;

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
        $fb = app(FacebookService::class);
        if ($body && isset($body['entry'])) {
            foreach ($body['entry'] as $entry) {
                if (isset($entry['messaging'])) {
                    foreach ($entry['messaging'] as $messagingEvent) {
                        $fb->handleMessagingEvent($messagingEvent, $entry['id']);
                    }
                }

                // Handle WhatsApp messages if applicable
                if (isset($entry['changes'])) {
                    $fb->handleWhatsAppEvent($entry);
                }
            }
        }

        return response('OK', 200);
    }

// Keep your existing sendMessage for text only
    static public function sendMessage(FacebookPage $page, string $recipientId, ?string $messageText = null)
    {
        if (!$messageText) {
            return null;
        }

        return app(FacebookService::class)->sendTextMessage($page, $recipientId, $messageText);
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
