<?php

namespace App\Http\Controllers;

use App\Models\Dealer;
use App\Models\DealerPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Yajra\DataTables\DataTables;

class DealerAdvancePaymentController extends Controller
{
    /**
     * Display a listing of dealer advance payments.
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = DealerPayment::with(['dealer', 'dealerBill', 'user'])
                ->where('payment_type', 'advance');

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('payment_info', function ($payment) {
                    $html = '<div>';
                    $html .= '<div class="font-weight-bold"># ' . $payment->id . '</div>';
                    $html .= '<div class="text-muted small">' . $payment->user->name . '</div>';
                    if ($payment->reference_number) {
                        $html .= '<div class="text-muted small">Ref: ' . $payment->reference_number . '</div>';
                    }
                    $html .= '</div>';
                    return $html;
                })
                ->addColumn('dealer_info', function ($payment) {
                    $html = '<div>';
                    $html .= '<div class="font-weight-bold">' . $payment->dealer->name . '</div>';
                    $html .= '<div class="text-muted small">' . $payment->dealer->phone . '</div>';
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
                ->rawColumns(['payment_info', 'dealer_info', 'amount_display', 'method_badge', 'actions'])
                ->make(true);
        }

        $dealers = Dealer::active()->get();
        return view('advance-payments.index', compact('dealers'));
    }

    /**
     * Show the form for creating a new advance payment.
     */
    public function create(Request $request)
    {
        $dealers = Dealer::active()->get();

        // If dealer_id is provided, get their current advance balance
        $dealer = null;
        $currentAdvanceBalance = 0;

        if ($request->filled('dealer_id')) {
            $dealer = Dealer::find($request->dealer_id);
            if ($dealer) {
                $currentAdvanceBalance = $dealer->advance_balance;
            }
        }

        return view('advance-payments.create', compact(
            'dealers',
            'dealer',
            'currentAdvanceBalance'
        ));
    }

    /**
     * Store a newly created advance payment.
     */
    public function store(Request $request)
    {
        $request->validate([
            'dealer_id' => 'required|exists:dealers,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'payment_method' => 'required|in:cash,bank_transfer,check,credit_card',
            'notes' => 'nullable|string|max:500',
            'reference_number' => 'nullable|string|max:100',
        ]);

        $dealer = Dealer::findOrFail($request->dealer_id);

        DB::transaction(function () use ($request, $dealer) {
            // Add to advance balance
            $dealer->increment('advance_balance', $request->amount);

            // Create payment record
            DealerPayment::create([
                'dealer_id' => $request->dealer_id,
                'user_id' => Auth::id(),
                'payment_type' => 'advance',
                'amount' => $request->amount,
                'previous_due_balance' => $dealer->due_balance,
                'previous_advance_balance' => $dealer->advance_balance - $request->amount,
                'payment_date' => $request->payment_date,
                'payment_method' => $request->payment_method,
                'notes' => $request->notes,
                'reference_number' => $request->reference_number,
            ]);

            // Update daily dues record
            $this->updateDailyDueRecord($dealer, $request->payment_date, $request->amount, 'advance');
        });

        // Check if it's an AJAX request
        if (request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Advance payment recorded successfully.',
            ]);
        }

        return redirect()->route('advance-payments.index')
            ->with('success', 'Advance payment recorded successfully.');
    }

    /**
     * Display the specified advance payment.
     */
    public function show(DealerPayment $dealerPayment)
    {
        // Ensure it's an advance payment
        if ($dealerPayment->payment_type !== 'advance') {
            abort(404);
        }

        $dealerPayment->load(['dealer', 'dealerBill', 'user']);
        return view('advance-payments.show', compact('dealerPayment'));
    }

    /**
     * Show the form for editing the specified advance payment.
     */
    public function edit(DealerPayment $dealerPayment)
    {
        // Ensure it's an advance payment
        if ($dealerPayment->payment_type !== 'advance') {
            abort(404);
        }

        $dealers = Dealer::active()->get();

        return view('advance-payments.edit', compact('dealerPayment', 'dealers'));
    }

    /**
     * Update the specified advance payment.
     */
    public function update(Request $request, DealerPayment $dealerPayment)
    {
        // Ensure it's an advance payment
        if ($dealerPayment->payment_type !== 'advance') {
            abort(404);
        }

        $request->validate([
            'dealer_id' => 'required|exists:dealers,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'payment_method' => 'required|in:cash,bank_transfer,check,credit_card',
            'notes' => 'nullable|string|max:500',
            'reference_number' => 'nullable|string|max:100',
        ]);

        $dealer = Dealer::findOrFail($request->dealer_id);

        DB::transaction(function () use ($request, $dealerPayment, $dealer) {
            // Reverse previous balance changes
            $dealer->decrement('advance_balance', $dealerPayment->amount);

            // Update payment record
            $dealerPayment->update([
                'dealer_id' => $request->dealer_id,
                'amount' => $request->amount,
                'payment_date' => $request->payment_date,
                'payment_method' => $request->payment_method,
                'notes' => $request->notes,
                'reference_number' => $request->reference_number,
            ]);

            // Apply new balance changes
            $dealer->increment('advance_balance', $request->amount);

            // Update daily dues records (this would need more complex logic for reversals)
            // For simplicity, we'll recreate the daily due record
            $this->updateDailyDueRecord($dealer, $request->payment_date, $request->amount, 'advance');
        });

        return redirect()->route('advance-payments.index')
            ->with('success', 'Advance payment updated successfully.');
    }

    /**
     * Remove the specified advance payment.
     */
    public function destroy(DealerPayment $dealerPayment)
    {
        // Ensure it's an advance payment
        if ($dealerPayment->payment_type !== 'advance') {
            abort(404);
        }

        DB::transaction(function () use ($dealerPayment) {
            $dealer = $dealerPayment->dealer;

            // Reverse balance changes
            $dealer->decrement('advance_balance', $dealerPayment->amount);

            $dealerPayment->delete();

            // Update daily dues record (reverse the payment)
            $this->updateDailyDueRecord($dealer, $dealerPayment->payment_date, -$dealerPayment->amount, 'advance');
        });

        return redirect()->route('advance-payments.index')
            ->with('success', 'Advance payment deleted successfully.');
    }

    /**
     * Print advance payment receipt.
     */
    public function print(DealerPayment $dealerPayment)
    {
        // Ensure it's an advance payment
        if ($dealerPayment->payment_type !== 'advance') {
            abort(404);
        }

        $dealerPayment->load(['dealer', 'dealerBill', 'user']);
        return view('advance-payments.print', compact('dealerPayment'));
    }

    /**
     * Update daily due record for a dealer.
     */
    private function updateDailyDueRecord(Dealer $dealer, $date, $amount, $type)
    {
        $dailyDue = \App\Models\DealerDailyDue::firstOrNew([
            'dealer_id' => $dealer->id,
            'due_date' => $date,
        ]);

        if (!$dailyDue->exists) {
            // Get previous day's closing due as opening due
            $previousDay = \App\Models\DealerDailyDue::where('dealer_id', $dealer->id)
                ->where('due_date', '<', $date)
                ->orderBy('due_date', 'desc')
                ->first();

            $dailyDue->opening_due = $previousDay ? $previousDay->closing_due : $dealer->due_balance;
            $dailyDue->bills_amount = 0;
            $dailyDue->payments_amount = 0;
        }

        if ($type === 'advance') {
            // Advances don't affect due calculations directly
        }

        $dailyDue->closing_due = $dailyDue->opening_due + $dailyDue->bills_amount - $dailyDue->payments_amount;
        $dailyDue->save();
    }
}
