<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\Payment;
use App\Models\Customer;
use App\Models\Dealer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class PaymentController extends Controller
{
    /**
     * Display a listing of payments.
     */
    public function index(Request $request)
    {
        $query = Payment::with(['bill.billable', 'user']);

        // Filter by billable type
        if ($request->filled('billable_type')) {
            $query->whereHas('bill', function ($q) use ($request) {
                $q->where('billable_type', $request->billable_type);
            });
        }

        // Filter by billable id
        if ($request->filled('billable_id')) {
            $query->whereHas('bill', function ($q) use ($request) {
                $q->where('billable_id', $request->billable_id);
            });
        }

        // Filter by payment type
        if ($request->filled('payment_type')) {
            $query->where('payment_type', $request->payment_type);
        }

        // Filter by date range
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('payment_date', [$request->start_date, $request->end_date]);
        }

        $payments = $query->latest()->paginate(15)->appends($request->all());
        $customers = Customer::active()->get();
        $dealers = Dealer::active()->get();

        return view('payments.index', compact('payments', 'customers', 'dealers'));
    }

    /**
     * Show the form for creating a new payment.
     */
    public function create(Request $request)
    {
        $customers = Customer::active()->get();
        $dealers = Dealer::active()->get();

        // If bill_id is provided, get bill details
        $bill = null;
        $billable = null;
        $currentDueBalance = 0;
        $currentAdvanceBalance = 0;

        if ($request->filled('bill_id')) {
            $bill = Bill::find($request->bill_id);
            if ($bill) {
                $billable = $bill->billable;
                $currentDueBalance = $billable->due_balance ?? 0;
                $currentAdvanceBalance = $billable->advance_balance ?? 0;
            }
        }

        return view('payments.create', compact(
            'customers',
            'dealers',
            'bill',
            'billable',
            'currentDueBalance',
            'currentAdvanceBalance'
        ));
    }

    /**
     * Show the form for creating a due payment.
     */
    public function createDue(Request $request)
    {
        $customers = Customer::active()->get();
        $dealers = Dealer::active()->get();

        // If bill_id is provided, get bill details
        $bill = null;
        $billable = null;
        $currentDueBalance = 0;

        if ($request->filled('bill_id')) {
            $bill = Bill::find($request->bill_id);
            if ($bill) {
                $billable = $bill->billable;
                $currentDueBalance = $billable->due_balance ?? 0;
            }
        }

        return view('payments.create-due', compact(
            'customers',
            'dealers',
            'bill',
            'billable',
            'currentDueBalance'
        ));
    }

    /**
     * Show the form for creating an advance payment.
     */
    public function createAdvance(Request $request)
    {
        $customers = Customer::active()->get();
        $dealers = Dealer::active()->get();

        // If billable info is provided, get their current advance balance
        $billable = null;
        $currentAdvanceBalance = 0;

        if ($request->filled('billable_type') && $request->filled('billable_id')) {
            $billable = $request->billable_type === 'App\\Models\\Customer'
                ? Customer::find($request->billable_id)
                : Dealer::find($request->billable_id);
            if ($billable) {
                $currentAdvanceBalance = $billable->advance_balance ?? 0;
            }
        }

        return view('payments.create-advance', compact(
            'customers',
            'dealers',
            'billable',
            'currentAdvanceBalance'
        ));
    }

    /**
     * Store a newly created payment.
     */
    public function store(Request $request)
    {
        $request->validate([
            'bill_id' => 'required|exists:bills,id',
            'payment_type' => 'required|in:due,advance',
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'payment_method' => 'required|in:cash,advance,bank_transfer,check,credit_card',
            'use_advance' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:500',
            'reference_number' => 'nullable|string|max:100',
        ]);

        $bill = Bill::findOrFail($request->bill_id);
        $billable = $bill->billable;

        // Validate payment constraints
        if ($request->payment_type === 'due') {
            // For due payments, check if billable has sufficient due balance
            $billDue = $bill->total_amount - $bill->total_paid;
            if ($request->amount > $billDue) {
                return back()->withErrors(['amount' => 'Payment amount cannot exceed the bill due amount.'])->withInput();
            }
            if ($request->payment_method === 'advance'
                && $request->amount > ($billable->advance_balance ?? 0)) {
                    return back()->withErrors(['amount' => 'Payment amount cannot exceed the billable\'s advance balance.'])->withInput();
            }
        }

        $payment = null;

        DB::transaction(function () use ($request, $bill, $billable, &$payment) {
            $actualPaymentAmount = $request->amount;

            // Handle balance updates based on payment type and method
            if ($request->payment_type === 'due') {
                if ($request->payment_method === 'advance') {
                    // Using advance balance to pay due
                    $billable->decrement('advance_balance', $request->amount);
                } else {
                    // Regular due payment
                    $billable->decrement('due_balance', $request->amount);
                }
            } else {
                // Advance payment - add to advance balance
                $billable->increment('advance_balance', $request->amount);
            }

            // Create payment record
            $payment = Payment::create([
                'bill_id' => $request->bill_id,
                'user_id' => Auth::id(),
                'payment_type' => $request->payment_type,
                'amount' => $actualPaymentAmount,
                'previous_due_balance' => $billable->due_balance + ($request->payment_type === 'due' && $request->payment_method !== 'advance' ? $request->amount : 0),
                'previous_advance_balance' => $billable->advance_balance + ($request->payment_type === 'due' && $request->payment_method === 'advance' ? $request->amount : 0) - ($request->payment_type === 'advance' ? $request->amount : 0),
                'payment_date' => $request->payment_date,
                'payment_method' => ($request->payment_type === 'due' && $request->payment_method === 'advance' ? 'Advance' : $request->payment_method),
                'notes' => $request->notes,
                'reference_number' => $request->reference_number,
            ]);

            // Update bill total_paid
            $bill->updateTotalPaid();
        });

        // Check if it's an AJAX request
        if (request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Payment recorded successfully.',
                'payment' => $payment
            ]);
        }

        return redirect()->route('payments.index')
            ->with('success', 'Payment recorded successfully.');
    }

    /**
     * Display the specified payment.
     */
    public function show(Payment $payment)
    {
        $payment->load(['bill.billable', 'user']);
        return view('payments.show', compact('payment'));
    }

    /**
     * Show the form for editing the specified payment.
     */
    public function edit(Payment $payment)
    {
        $customers = Customer::active()->get();
        $dealers = Dealer::active()->get();
        $bill = $payment->bill;
        $billable = $bill->billable;

        return view('payments.edit', compact('payment', 'customers', 'dealers', 'bill', 'billable'));
    }

    /**
     * Update the specified payment.
     */
    public function update(Request $request, Payment $payment)
    {
        $request->validate([
            'bill_id' => 'required|exists:bills,id',
            'payment_type' => 'required|in:due,advance',
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'payment_method' => 'required|in:cash,bank_transfer,check,credit_card',
            'notes' => 'nullable|string|max:500',
            'reference_number' => 'nullable|string|max:100',
        ]);

        $bill = Bill::findOrFail($request->bill_id);
        $billable = $bill->billable;

        DB::transaction(function () use ($request, $payment, $bill, $billable) {
            // Reverse previous balance changes
            if ($payment->payment_type === 'due') {
                $billable->increment('due_balance', $payment->amount);
            } else {
                $billable->decrement('advance_balance', $payment->amount);
            }

            // Update payment record
            $payment->update([
                'bill_id' => $request->bill_id,
                'payment_type' => $request->payment_type,
                'amount' => $request->amount,
                'payment_date' => $request->payment_date,
                'payment_method' => $request->payment_method,
                'notes' => $request->notes,
                'reference_number' => $request->reference_number,
            ]);

            // Apply new balance changes
            if ($request->payment_type === 'due') {
                $billable->decrement('due_balance', $request->amount);
            } else {
                $billable->increment('advance_balance', $request->amount);
            }

            // Update bill total_paid
            $bill->updateTotalPaid();
        });

        return redirect()->route('payments.index')
            ->with('success', 'Payment updated successfully.');
    }

    /**
     * Remove the specified payment.
     */
    public function destroy(Payment $payment)
    {
        DB::transaction(function () use ($payment) {
            $bill = $payment->bill;
            $billable = $bill->billable;

            // Reverse balance changes
            if ($payment->payment_type === 'due') {
                $billable->increment('due_balance', $payment->amount);
            } else {
                $billable->decrement('advance_balance', $payment->amount);
            }

            $payment->delete();

            // Update bill total_paid
            $bill->updateTotalPaid();
        });

        return redirect()->route('payments.index')
            ->with('success', 'Payment deleted successfully.');
    }

    /**
     * Get bills via AJAX.
     */
    public function getBills(Request $request)
    {
        $billableType = $request->get('billable_type');
        $billableId = $request->get('billable_id');

        $bills = Bill::where('billable_type', $billableType)
            ->where('billable_id', $billableId)
            ->where('total_amount', '>', 0)
            ->with('payments')
            ->get()
            ->map(function ($bill) {
                $paid = $bill->payments->sum('amount');
                $due = $bill->total_amount - $paid;
                return [
                    'id' => $bill->id,
                    'bill_number' => $bill->bill_number,
                    'bill_date' => $bill->bill_date->format('Y-m-d'),
                    'total_amount' => $bill->total_amount,
                    'paid_amount' => $paid,
                    'due_amount' => $due,
                ];
            });

        return response()->json($bills);
    }

    /**
     * Print payment receipt.
     */
    public function print(Payment $payment)
    {
        $payment->load(['bill.billable', 'user']);
        return view('payments.print', compact('payment'));
    }
}
