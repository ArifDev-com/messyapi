<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\FacebookMessage;
use App\Models\FacebookPage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class MessengerController extends Controller
{
    public function index()
    {
        $facebookAccount = Auth::user()->facebookAccount;

        if (!$facebookAccount) {
            return redirect()->route('dashboard')->with('error', 'Please connect your Facebook account first.');
        }

        // Get customers with their latest message
        $customers = Customer::whereHas('messages.facebookPage', function($query) use ($facebookAccount) {
            $query->where('facebook_account_id', $facebookAccount->id);
        })
        ->with(['messages' => function($query) {
            $query->latest('sent_at')->take(1);
        }])
        ->orderBy('last_message_at', 'desc')
        ->get();

        return view('messenger.index', compact('customers'));
    }

    public function show(Customer $customer)
    {
        // Ensure the customer belongs to the authenticated user's Facebook account
        if (!$customer->messages()->whereHas('facebookPage', function($query) {
            $query->where('facebook_account_id', Auth::user()->facebookAccount->id ?? null);
        })->exists()) {
            abort(403);
        }

        // Get all messages for this customer
        $messages = FacebookMessage::where('customer_id', $customer->id)
            ->with('facebookPage')
            ->orderBy('sent_at', 'asc')
            ->get();

        return view('messenger.show', compact('customer', 'messages'));
    }

    public function sendMessage(Request $request, Customer $customer)
    {
        $request->validate([
            'message' => 'nullable|string|max:1000',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:5120', // 5MB max
        ]);

        // Ensure at least message or images are provided
        if (!$request->message && !$request->hasFile('images')) {
            return response()->json(['error' => 'Message or images are required'], 422);
        }

        // Ensure the customer belongs to the authenticated user's Facebook account
        $page = $customer->messages()->whereHas('facebookPage', function($query) {
            $query->where('facebook_account_id', Auth::user()->facebookAccount->id ?? null);
        })->first()?->facebookPage;

        if (!$page) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Send message via Facebook API
        $facebookController = app(\App\Services\FacebookService::class);
        $attachments = null;

        if ($request->hasFile('images')) {
            $attachments = $facebookController->sendMessageWithAttachments($page, $customer->platform_user_id, $request->message, $request->file('images'));
        } else {
            $facebookController->sendMessage($page, $customer->platform_user_id, $request->message);
        }

        // Save the message in database
        FacebookMessage::create([
            'facebook_page_id' => $page->id,
            'customer_id' => $customer->id,
            'message_id' => 0,
            'sender_id' => $page->page_id,
            'recipient_id' => $customer->platform_user_id,
            'message_text' => $request->message ?: '',
            'attachments' => $attachments,
            'is_echo' => true,
            'sent_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }

    public function getMessages(Customer $customer, Request $request)
    {
        // Ensure the customer belongs to the authenticated user's Facebook account

        $messages = FacebookMessage::where(function ($q) use ($request, $customer) {
            $q->where('customer_id', $customer->id)
                ->orWhere('recipient_id', $customer->platform_user_id);
        })
            ->with('facebookPage')
            ->when($request->after, fn($query) => $query->where('sent_at', '>', $request->after))
            ->orderBy('sent_at', 'asc')
            ->get()
            ->map(function($message) {
                return [
                    'id' => $message->id,
                    'text' => $message->message_text,
                    'is_echo' => $message->is_echo,
                    'sent_at' => $message->sent_at->format('Y-m-d H:i:s'),
                    'page_name' => $message->facebookPage->name,
                    'attachments' => $message->attachments,
                ];
            });

        return response()->json($messages);
    }

    public function toggleAutoReply(Customer $customer, Request $request)
    {
        // Ensure the customer belongs to the authenticated user's Facebook account
        if (!$customer->messages()->whereHas('facebookPage', function($query) {
            $query->where('facebook_account_id', Auth::user()->facebookAccount->id ?? null);
        })->exists()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $customer->update([
            'auto_reply_enabled' => $request->boolean('auto_reply_enabled')
        ]);

        return response()->json(['success' => true]);
    }

    public function getCustomer(Customer $customer)
    {
        // Ensure the customer belongs to the authenticated user's Facebook account
        if (!$customer->messages()->whereHas('facebookPage', function($query) {
            $query->where('facebook_account_id', Auth::user()->facebookAccount->id ?? null);
        })->exists()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json([
            'id' => $customer->id,
            'name' => $customer->name,
            'auto_reply_enabled' => $customer->auto_reply_enabled,
        ]);
    }
}
