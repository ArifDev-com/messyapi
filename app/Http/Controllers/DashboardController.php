<?php

namespace App\Http\Controllers;

use App\Models\FacebookMessage;
use App\Models\FacebookPage;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        // Get message statistics
        $totalMessages = FacebookMessage::count();
        $totalCustomers = Customer::count();
        $totalPages = FacebookPage::count();

        // Messages by type (customer vs echo/reply)
        $messagesByType = FacebookMessage::selectRaw('
            CASE
                WHEN is_echo = 1 THEN "replies"
                ELSE "customer_messages"
            END as type,
            COUNT(*) as count
        ')
        ->groupBy('type')
        ->get()
        ->pluck('count', 'type')
        ->toArray();

        // Messages over time (last 30 days)
        $messagesOverTime = FacebookMessage::selectRaw('
            DATE(sent_at) as date,
            COUNT(*) as count
        ')
        ->where('sent_at', '>=', now()->subDays(30))
        ->groupBy('date')
        ->orderBy('date')
        ->get();

        // Top pages by message count
        $topPages = FacebookPage::withCount('messages')
            ->orderBy('messages_count', 'desc')
            ->take(5)
            ->get();

        // Recent messages
        $recentMessages = FacebookMessage::with(['facebookPage', 'customer'])
            ->orderBy('sent_at', 'desc')
            ->take(10)
            ->get();

        return view('dashboard', compact(
            'totalMessages',
            'totalCustomers',
            'totalPages',
            'messagesByType',
            'messagesOverTime',
            'topPages',
            'recentMessages'
        ));
    }
}
