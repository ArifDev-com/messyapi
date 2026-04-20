<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\Payment;
use App\Models\Customer;
use App\Models\Dealer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Yajra\DataTables\DataTables;

class AdvancePaymentController extends Controller
{
    /**
     * Display a listing of customer advance payments.
     */
    public function customerIndex(Request $request)
    {
        if ($request->ajax()) {
            $query = Payment::with(['bill.billable', 'user'])
                ->where('payment_type', 'advance')
                ->whereHas('bill', function ($q) {
                    $q->where('billable_type', 'App\\Models\\Customer');
                });

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('customer_info', function ($payment) {
                    $customer = $payment->bill->billable;
                    $html = '<div>';
                    $html .= '<div class="font-weight-bold">' . $customer->name . '</div>';
                    $html .= '<div class="text-muted small">' . $customer->phone . '</div>';
                    $html .= '</div>';
                    return $html;
                })
                ->addColumn('amount_display', function ($payment) {
                    return '<span class="font-weight-bold text-success">' . $payment->formatted_amount . '</span>';
                })
                ->addColumn('method_badge', function ($payment) {
                    return '<span class="badge badge-' . $payment->payment_method_badge . '">' . ucwords(str_replace('_', ' ', $payment->payment_method)) . '</span>';
                })
                ->addColumn('date_display', function ($payment) {
                    return $payment->payment_date->format('M d, Y');
                })
                ->addColumn('actions', function ($payment) {
                    return view('actions.advance-payment-actions', compact('payment'))->render();
                })
                ->rawColumns(['customer_info', 'amount_display', 'method_badge', 'actions'])
                ->make(true);
        }

        $customers = Customer::active()->get();
        return view('advance-payments.customer-index', compact('customers'));
    }

    /**
     * Display a listing of dealer advance payments.
     */
    public function dealerIndex(Request $request)
    {
        if ($request->ajax()) {
            $query = Payment::with(['bill.billable', 'user'])
                ->where('payment_type', 'advance')
                ->whereHas('bill', function ($q) {
                    $q->where('billable_type', 'App\\Models\\Dealer');
                });

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('dealer_info', function ($payment) {
                    $dealer = $payment->bill->billable;
                    $html = '<div>';
                    $html .= '<div class="font-weight-bold">' . $dealer->name . '</div>';
                    $html .= '<div class="text-muted small">' . $dealer->phone . '</div>';
                    $html .= '</div>';
                    return $html;
                })
                ->addColumn('amount_display', function ($payment) {
                    return '<span class="font-weight-bold text-success">' . $payment->formatted_amount . '</span>';
                })
                ->addColumn('method_badge', function ($payment) {
                    return '<span class="badge badge-' . $payment->payment_method_badge . '">' . ucwords(str_replace('_', ' ', $payment->payment_method)) . '</span>';
                })
                ->addColumn('date_display', function ($payment) {
                    return $payment->payment_date->format('M d, Y');
                })
                ->addColumn('actions', function ($payment) {
                    return view('actions.advance-payment-actions', compact('payment'))->render();
                })
                ->rawColumns(['dealer_info', 'amount_display', 'method_badge', 'actions'])
                ->make(true);
        }

        $dealers = Dealer::active()->get();
        return view('advance-payments.dealer-index', compact('dealers'));
    }

    /**
     * Store a newly created advance payment.
     */
    public function store(Request $request)
    {
        $request->validate([
            'billable_type' => 'required|in:App\\Models\\Customer,App\\Models\\Dealer',
            'billable_id' => 'required|integer',
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'payment_method' => ['required', Rule::in(Payment::$methods)],
            'notes' => 'nullable|string|max:500',
            'reference_number' => 'nullable|string|max:100',
        ]);

        $billable = $request->billable_type === 'App\\Models\\Customer'
            ? Customer::findOrFail($request->billable_id)
            : Dealer::findOrFail($request->billable_id);

        // Create a dummy bill for advance payment (since advance payments aren't tied to specific bills)
        $bill = Bill::create([
            'billable_type' => $request->billable_type,
            'billable_id' => $request->billable_id,
            'bill_number' => 'ADV-' . time() . '-' . $request->billable_id,
            'bill_date' => $request->payment_date,
            'due_date' => $request->payment_date,
            'subtotal' => 0,
            'total_amount' => 0,
            'advance_payment' => $request->amount,
            'status' => 'approved',
            'notes' => 'Advance payment bill',
        ]);

        DB::transaction(function () use ($request, $billable, $bill) {
            // Add to advance balance
            $billable->increment('advance_balance', $request->amount);

            // Create payment record
            Payment::create([
                'bill_id' => $bill->id,
                'user_id' => Auth::id(),
                'payment_type' => 'advance',
                'amount' => $request->amount,
                'previous_due_balance' => $billable->due_balance,
                'previous_advance_balance' => $billable->advance_balance - $request->amount,
                'payment_date' => $request->payment_date,
                'payment_method' => $request->payment_method,
                'notes' => $request->notes,
                'reference_number' => $request->reference_number,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Advance payment recorded successfully.',
        ]);
    }

    /**
     * Remove the specified advance payment.
     */
    public function destroy(Payment $payment)
    {
        // // Ensure it's an advance payment
        // if ($payment->payment_type !== 'advance') {
        //     return response()->json(['success' => false, 'message' => 'Invalid payment type.'], 400);
        // }

        // DB::transaction(function () use ($payment) {
        //     $bill = $payment->bill;
        //     $billable = $bill->billable;

        //     // Reverse balance changes
        //     $billable->decrement('advance_balance', $payment->amount);

        //     // Delete the payment and the dummy bill
        //     $payment->delete();
        //     $bill->delete();
        // });

        // return response()->json([
        //     'success' => true,
        //     'message' => 'Advance payment deleted successfully.',
        // ]);
    }
}
