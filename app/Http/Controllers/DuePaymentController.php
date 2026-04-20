<?php

namespace App\Http\Controllers;

use App\Jobs\CalculateDealerCommissions;
use App\Jobs\CalculateSalesmanCommissions;
use App\Models\Bill;
use App\Models\Payment;
use App\Models\Customer;
use App\Models\Dealer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Yajra\DataTables\DataTables;

class DuePaymentController extends Controller
{
    /**
     * Display a listing of customer due payments.
     */
    public function customerIndex(Request $request)
    {
        if ($request->ajax()) {
            $query = Payment::with(['bill.billable', 'user'])
                ->where('payment_type', 'due')
                ->whereHas('bill', function ($q) {
                    $q->where('billable_type', 'App\\Models\\Customer');
                })
                ->latest();

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
                ->addColumn('bill_info', function ($payment) {
                    $bill = $payment->bill;
                    $html = '<div>';
                    $html .= '<div class="font-weight-bold">#' . $bill->bill_number . '</div>';
                    $html .= '<div class="text-muted small">' . $bill->bill_date->format('M d, Y') . '</div>';
                    $html .= '</div>';
                    return $html;
                })
                ->addColumn('amount_display', function ($payment) {
                    return '<span class="font-weight-bold text-danger">' . $payment->formatted_amount . '</span>';
                })
                ->addColumn('method_badge', function ($payment) {
                    return '<span class="badge badge-' . $payment->payment_method_badge . '">' . ucwords(str_replace('_', ' ', $payment->payment_method)) . '</span>';
                })
                ->addColumn('date_display', function ($payment) {
                    return $payment->payment_date->format('M d, Y');
                })
                ->addColumn('actions', function ($payment) {
                    return view('actions.due-payment-actions', compact('payment'))->render();
                })
                ->rawColumns(['customer_info', 'bill_info', 'amount_display', 'method_badge', 'actions'])
                ->make(true);
        }

        $customers = Customer::active()->get();
        return view('due-payments.customer-index', compact('customers'));
    }

    /**
     * Display a listing of dealer due payments.
     */
    public function dealerIndex(Request $request)
    {
        if ($request->ajax()) {
            $query = Payment::with(['bill.billable', 'user'])
                ->where('payment_type', 'due')
                ->whereHas('bill', function ($q) {
                    $q->where('billable_type', 'App\\Models\\Dealer');
                })
                ->latest();

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
                ->addColumn('bill_info', function ($payment) {
                    $bill = $payment->bill;
                    $html = '<div>';
                    $html .= '<div class="font-weight-bold">#' . $bill->bill_number . '</div>';
                    $html .= '<div class="text-muted small">' . $bill->bill_date->format('M d, Y') . '</div>';
                    $html .= '</div>';
                    return $html;
                })
                ->addColumn('amount_display', function ($payment) {
                    return '<span class="font-weight-bold text-danger">' . $payment->formatted_amount . '</span>';
                })
                ->addColumn('method_badge', function ($payment) {
                    return '<span class="badge badge-' . $payment->payment_method_badge . '">' . ucwords(str_replace('_', ' ', $payment->payment_method)) . '</span>';
                })
                ->addColumn('date_display', function ($payment) {
                    return $payment->payment_date->format('M d, Y');
                })
                ->addColumn('actions', function ($payment) {
                    return view('actions.due-payment-actions', compact('payment'))->render();
                })
                ->rawColumns(['dealer_info', 'bill_info', 'amount_display', 'method_badge', 'actions'])
                ->make(true);
        }

        $dealers = Dealer::active()->get();
        return view('due-payments.dealer-index', compact('dealers'));
    }

    /**
     * Store a newly created due payment.
     * This will distribute the payment across multiple bills if needed.
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

        // Check if payment amount exceeds due balance
        if ($request->amount > $billable->due_balance) {
            return response()->json([
                'success' => false,
                'message' => 'Payment amount cannot exceed the due balance of ' . $billable->formatted_due_balance ?? '৳' . number_format($billable->due_balance, 2) . '.'
            ], 400);
        }

        // Handle advance payment method
        if ($request->payment_method === 'Advance') {
            if ($request->amount > $billable->advance_balance) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment amount cannot exceed the advance balance.'
                ], 400);
            }
        }
        if ($request->payment_method === 'Commission') {
            if ($request->amount > ($billable->commission_balance ?: 0)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment amount cannot exceed the advance balance.'
                ], 400);
            }
        }

        $remainingAmount = $request->amount;
        $payments = [];
        $_bills = [];
        DB::transaction(function () use ($request, $billable, &$remainingAmount, &$payments, &$_bills) {
            // Get bills with due amounts, ordered by bill date (oldest first)
            $bills = Bill::where('billable_type', $request->billable_type)
                ->where('billable_id', $request->billable_id)
                ->whereRaw('(total_amount - COALESCE(total_paid, 0)) > 0')
                ->when($request->dealer_bill_id, function($q) use($request) {
                    $q->where('id', $request->dealer_bill_id);
                })
                ->orderBy('bill_date', 'asc')
                ->get();

            foreach ($bills as $bill) {
                if ($remainingAmount <= 0) break;

                $billDue = $bill->due_amount;
                $paymentAmount = min($remainingAmount, $billDue);

                // Handle balance updates based on payment method
                if ($request->payment_method === 'Advance') {
                    $billable->decrement('advance_balance', $paymentAmount);
                } else
                if ($request->payment_method === 'Commission') {
                    $billable->decrement('commission_balance', $paymentAmount);
                } else {
                    $billable->decrement('due_balance', $paymentAmount);
                }

                // Create payment record
                $payment = Payment::create([
                    'bill_id' => $bill->id,
                    'user_id' => Auth::id(),
                    'payment_type' => 'due',
                    'amount' => $paymentAmount,
                    'previous_due_balance' => $billable->due_balance + ($request->payment_method !== 'advance' ? $paymentAmount : 0),
                    'previous_advance_balance' => $billable->advance_balance + ($request->payment_method === 'advance' ? $paymentAmount : 0),
                    'payment_date' => $request->payment_date,
                    'payment_method' => $request->payment_method === 'advance' ? 'Advance' : $request->payment_method,
                    'notes' => $request->notes,
                    'reference_number' => $request->reference_number,
                ]);

                // Update bill total_paid
                $_bills[] = $bill;

                $payments[] = $payment;
                $remainingAmount -= $paymentAmount;
            }
        });
        foreach($_bills as $bil) {
            $bil->updateTotalPaid();
            CalculateDealerCommissions::dispatch($bil->id);
            if ($bil->salesman_id) {
                CalculateSalesmanCommissions::dispatch($bil->id);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Due payment(s) recorded successfully. ' . count($payments) . ' payment(s) created.',
            'payments_count' => count($payments)
        ]);
    }

    /**
     * Remove the specified due payment.
     */
    public function destroy(Payment $payment)
    {
        // // Ensure it's a due payment
        // if ($payment->payment_type !== 'due') {
        //     return response()->json(['success' => false, 'message' => 'Invalid payment type.'], 400);
        // }

        // DB::transaction(function () use ($payment) {
        //     $bill = $payment->bill;
        //     $billable = $bill->billable;

        //     // Reverse balance changes
        //     if ($payment->payment_method === 'Advance') {
        //         $billable->increment('advance_balance', $payment->amount);
        //     } else {
        //         $billable->increment('due_balance', $payment->amount);
        //     }

        //     $payment->delete();

        //     // Update bill total_paid
        //     $bill->updateTotalPaid();
        // });

        // return response()->json([
        //     'success' => true,
        //     'message' => 'Due payment deleted successfully.',
        // ]);
    }
}
