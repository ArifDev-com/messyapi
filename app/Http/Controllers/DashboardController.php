<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Dealer;
use App\Models\Bill;
use App\Models\Product;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'products' => Product::count(),
            'customers' => Customer::count(),
            'dealers' => Dealer::count(),
            'bills' => Bill::count(),
            'pending_bills' => Bill::where('status', 'pending')->count(),
            'low_stock_products' => Product::lowStock()->count(),
        ];

        $recent_bills = Bill::with(['billable', 'salesman'])
            ->latest()
            ->take(5)
            ->get();

        // Get overdue bills based on user role
        $overdue_bills = $this->getOverdueBillsForUser();

        return view('dashboard', compact('stats', 'recent_bills', 'overdue_bills'));
    }

    private function getOverdueBillsForUser()
    {
        $user = user();
        $query = Bill::with(['dealer', 'salesman'])
            ->overdue()
            ->latest()
            ->take(10);

        // If user is not admin, show only bills assigned to them
        if ($user->role === 'admin') {
        } else
        if ($user->role === 'salesman') {
            $query->where('salesman_id', $user->id);
        } else {
            return null;
        }
        return $query->get();
    }
}
