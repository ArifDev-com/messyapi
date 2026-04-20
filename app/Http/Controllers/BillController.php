<?php

namespace App\Http\Controllers;

use App\Jobs\CalculateDealerCommissions;
use App\Jobs\CalculateSalesmanCommissions;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Customer;
use App\Models\Dealer;
use App\Models\DeliveryPoint;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Yajra\DataTables\DataTables;

class BillController extends Controller
{
    /**
     * Display a listing of dealer bills.
     */
    public function dealerBillsIndex(Request $request)
    {
        $this->authorize('viewAny', Bill::class);

        if ($request->ajax()) {
            $query = Bill::with(['billable', 'salesman'])
                ->where('billable_type', 'App\Models\Dealer')
                ->whereHas('billable') // Ensure billable (dealer) exists
                ->whereHas('salesman') // Ensure salesman exists
                ->latest();

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('bill_number_link', function ($bill) {
                    $routePrefix = 'bills';
                    return '<strong><a href="' . route($routePrefix . '.show', $bill) . '">' . $bill->bill_number . '</a></strong>';
                })
                ->addColumn('dealer_name', function ($bill) {
                    return $bill->billable ? $bill->billable->name : 'N/A';
                })
                ->addColumn('salesman_name', function ($bill) {
                    return $bill->salesman ? $bill->salesman->name : 'N/A';
                })
                ->addColumn('bill_date_formatted', function ($bill) {
                    return $bill->bill_date->format('M d, Y');
                })
                ->addColumn('total_amount_display', function ($bill) {
                    return $bill->formatted_total_amount;
                })
                ->addColumn('total_paid_display', function ($bill) {
                    return $bill->formatted_total_paid;
                })
                ->addColumn('due_amount_display', function ($bill) {
                    $badgeClass = $bill->due_amount > 0 ? 'badge-warning' : 'badge-success';
                    return '<span class="badge ' . $badgeClass . '">' . $bill->formatted_due_amount . '</span>';
                })
                ->addColumn('status_badge', function ($bill) {
                    return '<span class="badge badge-' . $bill->status_badge . '">' . ucfirst($bill->status) . '</span>';
                })
                ->addColumn('actions', function ($bill) {
                    return view('actions.bill-actions', compact('bill'))->render();
                })
                ->rawColumns(['bill_number_link', 'due_amount_display', 'status_badge', 'actions'])
                ->make(true);
        }

        $dealers = Dealer::active()->get();
        return view('bills.dealer-index', compact('dealers'));
    }

    /**
     * Display a listing of customer bills.
     */
    public function customerBillsIndex(Request $request)
    {
        $this->authorize('viewAny', Bill::class);

        if ($request->ajax()) {
            $query = Bill::with(['billable', 'salesman'])
                ->where('billable_type', 'App\Models\Customer')
                ->whereHas('billable') // Ensure billable (customer) exists
                ->whereHas('salesman'); // Ensure salesman exists

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('bill_number_link', function ($bill) {
                    $routePrefix = 'bills';
                    return '<strong><a href="' . route($routePrefix . '.show', $bill) . '">' . $bill->bill_number . '</a></strong>';
                })
                ->addColumn('customer_name', function ($bill) {
                    return $bill->billable ? $bill->billable->name : 'N/A';
                })
                ->addColumn('salesman_name', function ($bill) {
                    return $bill->salesman ? $bill->salesman->name : 'N/A';
                })
                ->addColumn('bill_date_formatted', function ($bill) {
                    return $bill->bill_date->format('M d, Y');
                })
                ->addColumn('total_amount_display', function ($bill) {
                    return $bill->formatted_total_amount;
                })
                ->addColumn('total_paid_display', function ($bill) {
                    return $bill->formatted_total_paid;
                })
                ->addColumn('due_amount_display', function ($bill) {
                    $badgeClass = $bill->due_amount > 0 ? 'badge-warning' : 'badge-success';
                    return '<span class="badge ' . $badgeClass . '">' . $bill->formatted_due_amount . '</span>';
                })
                ->addColumn('status_badge', function ($bill) {
                    return '<span class="badge badge-' . $bill->status_badge . '">' . ucfirst($bill->status) . '</span>';
                })
                ->addColumn('actions', function ($bill) {
                    return view('actions.bill-actions', compact('bill'))->render();
                })
                ->rawColumns(['bill_number_link', 'due_amount_display', 'status_badge', 'actions'])
                ->make(true);
        }

        $customers = Customer::active()->get();
        return view('bills.customer-index', compact('customers'));
    }

    /**
     * Show the form for creating a dealer bill.
     */
    public function dealerBillsCreate(Request $request)
    {
        $this->authorize('create', Bill::class);

        // For salesmen, only show dealers assigned to them
        if (user()->role === 'salesman') {
            $billables = Dealer::active()
                ->whereHas('users', function ($q) {
                    $q->where('user_id', user()->id);
                })
                ->get();
        } else {
            $billables = Dealer::active()->get();
        }

        $billableType = 'dealer';
        $salesmen = User::role('salesman')->get();
        $products = Product::active()->get();

        // Get orders for the selected dealer (if any)
        // $orders = collect();
        // if ($request->filled('billable_id')) {
        //     $orders = Order::where('orderable_id', $request->billable_id)
        //         ->where('orderable_type', 'App\Models\Dealer')
        //         ->whereHas('items', function ($query) {
        //             $query->where('quantity', '>', 0);
        //         })
        //         ->with(['items' => function ($query) {
        //             $query->where('quantity', '>', 0);
        //         }])
        //         ->get();
        // }

        // Load order data for conversion if order_id is provided
        $selectedOrder = null;
        if ($request->filled('order_id')) {
            $selectedOrder = Order::with(['items.product', 'orderable', 'salesman'])
                ->find($request->order_id);

            // Pre-select dealer and salesman from order
            if ($selectedOrder) {
                $request->merge([
                    'billable_id' => $selectedOrder->orderable_id,
                    'salesman_id' => $selectedOrder->salesman_id,
                ]);
            }
        }

        return view('bills.create', compact('billables', 'billableType', 'salesmen', 'products', 'selectedOrder'));
    }

    /**
     * Show the form for creating a customer bill.
     */
    public function customerBillsCreate(Request $request)
    {
        $this->authorize('create', Bill::class);

        $billables = Customer::active()->get();
        $billableType = 'customer';
        $salesmen = User::role('salesman')->get();
        $products = Product::active()->get();

        // Get orders for the selected customer (if any)
        $orders = collect();
        if ($request->filled('billable_id')) {
            $orders = Order::where('orderable_id', $request->billable_id)
                ->where('orderable_type', 'App\Models\Customer')
                ->whereHas('items', function ($query) {
                    $query->where('quantity', '>', 0);
                })
                ->with(['items' => function ($query) {
                    $query->where('quantity', '>', 0);
                }])
                ->get();
        }

        // Load order data for conversion if order_id is provided
        $selectedOrder = null;
        if ($request->filled('order_id')) {
            $selectedOrder = Order::with(['items.product', 'orderable', 'salesman'])
                ->find($request->order_id);

            // Pre-select customer and salesman from order
            if ($selectedOrder) {
                $request->merge([
                    'billable_id' => $selectedOrder->orderable_id,
                    'salesman_id' => $selectedOrder->salesman_id,
                ]);
            }
        }

        return view('bills.create', compact('billables', 'billableType', 'salesmen', 'products', 'orders', 'selectedOrder'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Bill::class);

        $rules = [
            'billable_type' => 'required|in:App\Models\Dealer,App\Models\Customer',
            'billable_id' => 'required|integer',
            'order_id' => 'nullable|exists:orders,id',
            'salesman_id' => 'required|exists:users,id',
            'bill_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:bill_date',
            'delivery_point_id' => 'nullable|exists:delivery_points,id',
            'transport_id' => 'nullable|exists:transports,id',
            'transport_charge' => 'nullable|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.size_inc' => 'nullable|numeric|min:0',
            'items.*.pieces' => 'nullable|numeric|min:0',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.tax_percentage' => 'nullable|numeric|min:0|max:100',
            'items.*.tax_type' => 'required|in:inclusive,exclusive',
            'items.*.discount_value' => 'nullable|numeric|min:0',
            'items.*.discount_type' => 'required|in:percentage,fixed',
            'items.*.commission_rate' => 'nullable|numeric|max:100',
            'payments' => 'required|array',
            'payments.*.account_id' => 'nullable|exists:accounts,id',
            'payments.*.amount' => 'nullable|numeric',
            'payments.*.payment_method' => 'nullable',
            'payments.*.notes' => 'nullable|string|max:500',
            'payments.*.attachment' => 'nullable|file|max:5120',
            'notes' => 'nullable|string',
        ];

        $request->validate($rules);

        $bill = null;
        DB::transaction(function () use ($request, &$bill) {
            $order = null;
            if ($request->filled('order_id')) {
                $order = Order::find($request->order_id);

                // Validate quantities against order items
                foreach ($request->items as $itemData) {
                    $orderItem = $order->items()
                        ->where('product_id', $itemData['product_id'])
                        ->first();

                    if (!$orderItem) {
                        throw new \Exception("Product not found in the selected order.");
                    }

                    if ($itemData['quantity'] > $orderItem->quantity) {
                        throw new \Exception("Quantity for product {$orderItem->product->name} exceeds available quantity in order ({$orderItem->quantity}).");
                    }
                }
            }

            // Generate bill number
            $count = Bill::latest()->first()?->id ?: 1;
            $count++;
            $billNumber = 'B-' . date('Y') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
            while(Bill::where('bill_number', $billNumber)->exists()) {
                $billNumber = 'B-' . date('Y') . '-' . str_pad((++$count) + 1, 4, '0', STR_PAD_LEFT);
            }

            // Create bill items first to calculate totals
            $tempItems = [];
            foreach ($request->items as $itemData) {
                $item = new BillItem([
                    'product_id' => $itemData['product_id'],
                    'size_inc' => $itemData['size_inc'] ?? null,
                    'pieces' => $itemData['pieces'] ?? null,
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'tax_percentage' => $itemData['tax_percentage'] ?? 0,
                    'tax_type' => $itemData['tax_type'] ?? 'exclusive',
                    'discount_value' => $itemData['discount_value'] ?? 0,
                    'discount_type' => $itemData['discount_type'] ?? 'fixed',
                    'commission_rate' => $itemData['commission_rate'] ?? 0,
                ]);
                $tempItems[] = $item;
            }

            // Calculate subtotal from items
            $subtotal = 0;
            foreach ($tempItems as $item) {
                $subtotal += $item->calculateTotalPrice();
            }

            // Calculate bill-level totals
            $billDiscount = $request->discount ?? 0;
            $billDiscountType = $request->discount_type ?? 'fixed';

            // Auto-select transport from order if exists
            $transportId = $request->transport_id;
            if (!$transportId && $order && $order->transport_id) {
                $transportId = $order->transport_id;
            }

            // Create bill with calculated totals
            $bill = Bill::create([
                'bill_number' => $billNumber,
                'billable_type' => $request->billable_type,
                'billable_id' => $request->billable_id,
                'order_id' => $request->order_id,
                'salesman_id' => $request->salesman_id,
                'bill_date' => user()->role === 'salesman' ? now() : $request->bill_date,
                'due_date' => $request->due_date,
                'subtotal' => $subtotal,
                'discount' => $billDiscount,
                'discount_type' => $billDiscountType,
                'tax' => 0,
                'total_amount' => 0,
                'advance_payment' => 0,
                'payment_method' => null,
                'status' => 'open',
                'notes' => $request->notes,
                'gross_price' => 0,
                'comission_amount' => 0,
                'discount_amount' => 0,
                'transport_id' => $transportId,
                'transport_charge' => $request->transport_charge ?? 0,
                'delivery_point_id' => $request->delivery_point_id ?: ($request->delivery_point_create ? (
                    DeliveryPoint::create([
                        'name' => '',
                        'address' => $request->delivery_point_create,
                    ])->id
                ) : null),
                'user_id' => user()->id
            ]);

            // Create bill items with the bill_id
            foreach ($tempItems as $item) {
                $item->bill_id = $bill->id;
                $item->save();
            }

            $bill->calculateTotals();

            // Deduct quantities from order items if bill is created from order
            if ($order) {
                foreach ($request->items as $itemData) {
                    $orderItem = $order->items()
                        ->where('product_id', $itemData['product_id'])
                        ->first();

                    $orderItem->decrement('quantity', $itemData['quantity']);

                    // If quantity becomes 0, delete the order item
                    if ($orderItem->quantity <= 0) {
                        $orderItem->delete();
                    }
                    Product::query()
                        ->where('id', $itemData['product_id'])
                        ->decrement('stock_quantity', $itemData['quantity']);
                }

                // If no items left in order, delete the order
                if ($order->items()->count() === 0) {
                    $order->delete();
                }
            }

            // Get billable entity for balance updates
            $billable = $bill->billable;

            // Process payments if provided
            if ($request->filled('payments') && is_array($request->payments)) {
                foreach ($request->payments as $paymentData) {
                    $attachmentPath = null;
                    if(($paymentData['amount'] ?? null)) {
                        // Handle file upload if attachment is provided
                        if (isset($paymentData['attachment']) && $paymentData['attachment']) {
                            $attachmentPath = $paymentData['attachment']->store('payments', 'public');
                        }

                        // Create payment record
                        $pay = \App\Models\Payment::create([
                            'bill_id' => $bill->id,
                            'user_id' => Auth::id(),
                            'payment_type' => 'due', // All bill payments are due payments
                            'amount' => $paymentData['amount'],
                            'previous_due_balance' => $billable->due_balance,
                            'previous_advance_balance' => $billable->advance_balance,
                            'payment_date' => $request->bill_date,
                            'payment_method' => $paymentData['payment_method'] ?? 'Nil',
                            'notes' => $paymentData['notes'] ?? null,
                            'reference_number' => null,
                            'account_id' => $paymentData['account_id'] ?? null,
                            'attachment' => $attachmentPath,
                        ]);
                        if($pay->payment_method === 'Advance') {
                            $billable->decrement('advance_balance', $paymentData['amount']);
                        }
                        else if($pay->payment_method === 'Commission') {
                            if($billable instanceof Dealer)
                                $billable->decrement('commission_balance', $paymentData['amount']);
                        }
                        else if($pay->account_id) {
                            // Update account balance (increase balance as money is received)
                            $account = \App\Models\Account::find($pay->account_id);
                            $account->increment('balance', $pay->amount);
                        }

                        // Note: Due balance will be updated after all payments are processed
                    }
                }
            }

            // Update total paid and other calculated fields
            $bill->updateTotalPaid();
            // Update billable due balance (only for unpaid portion)
            $billable->increment('due_balance', $bill->due_amount);
        });
        if($bill?->due_amount == 0) {
            CalculateDealerCommissions::dispatch($bill?->id);
            CalculateSalesmanCommissions::dispatch($bill?->id);
        }

        $redirectRoute = $request->billable_type === 'App\Models\Dealer' ? 'dealer-bills.index' : 'customer-bills.index';
        $printRoute = route('bills.print', $bill);

        return redirect()->route($redirectRoute)
            ->with('success', 'Bill created successfully.')
            ->with('print_url', $printRoute);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Bill $bill)
    {
        $this->authorize('update', $bill);

        $rules = [
            'billable_type' => 'required|in:App\Models\Dealer,App\Models\Customer',
            'billable_id' => 'required|integer',
            'order_id' => 'nullable|exists:orders,id',
            'salesman_id' => 'required|exists:users,id',
            'bill_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:bill_date',
            'delivery_point_id' => 'nullable|exists:delivery_points,id',
            'transport_id' => 'nullable|exists:transports,id',
            'transport_charge' => 'nullable|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.size_inc' => 'nullable|numeric|min:0',
            'items.*.pieces' => 'nullable|numeric|min:0',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.tax_percentage' => 'nullable|numeric|min:0|max:100',
            'items.*.tax_type' => 'required|in:inclusive,exclusive',
            'items.*.discount_value' => 'nullable|numeric|min:0',
            'items.*.discount_type' => 'required|in:percentage,fixed',
            'items.*.commission_rate' => 'nullable|numeric|max:100',
            'payments' => 'required|array',
            'payments.*.account_id' => 'nullable|exists:accounts,id',
            'payments.*.amount' => 'nullable|numeric',
            'payments.*.payment_method' => 'nullable',
            'payments.*.notes' => 'nullable|string|max:500',
            'payments.*.attachment' => 'nullable|file|max:5120',
            'notes' => 'nullable|string',
        ];

        $request->validate($rules);

        DB::transaction(function () use ($request, $bill) {
            // Get current due amount before update
            $currentDueAmount = $bill->due_amount;

            // Get billable entity for balance updates
            $billable = $bill->billable;

            // Reverse the current due balance impact
            $billable->decrement('due_balance', $currentDueAmount);

            // Reverse old payments
            foreach ($bill->payments as $oldPayment) {
                if ($oldPayment->payment_method === 'Advance') {
                    $billable->increment('advance_balance', $oldPayment->amount);
                } elseif ($oldPayment->payment_method === 'Commission') {
                    if ($billable instanceof Dealer) {
                        $billable->increment('commission_balance', $oldPayment->amount);
                    }
                } elseif ($oldPayment->account_id) {
                    // Reverse account balance (decrease balance as money is refunded)
                    $account = \App\Models\Account::find($oldPayment->account_id);
                    $account->decrement('balance', $oldPayment->amount);
                }
                // Note: Due balance reversal is handled above by reversing the overall due_amount
            }

            // Delete old payments
            $bill->payments()->delete();

            // Update bill fields
            $bill->update([
                'billable_type' => $request->billable_type,
                'billable_id' => $request->billable_id,
                'order_id' => $request->order_id,
                'salesman_id' => $request->salesman_id,
                'bill_date' => user()->role === 'salesman' ? $bill->bill_date : $request->bill_date,
                'due_date' => $request->due_date,
                'transport_id' => $request->transport_id,
                'transport_charge' => $request->transport_charge ?? 0,
                'delivery_point_id' => $request->delivery_point_id ?: ($request->delivery_point_create ? (
                    DeliveryPoint::create([
                        'name' => '',
                        'address' => $request->delivery_point_create,
                    ])->id
                ) : null),
                'notes' => $request->notes,
                'commission_applied' => false,
                'salesman_commission_applied' => false,
            ]);

            // Delete old items
            $bill->items()->delete();

            // Create new bill items
            foreach ($request->items as $itemData) {
                $item = new BillItem([
                    'bill_id' => $bill->id,
                    'product_id' => $itemData['product_id'],
                    'size_inc' => $itemData['size_inc'] ?? null,
                    'pieces' => $itemData['pieces'] ?? null,
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'tax_percentage' => $itemData['tax_percentage'] ?? 0,
                    'tax_type' => $itemData['tax_type'] ?? 'exclusive',
                    'discount_value' => $itemData['discount_value'] ?? 0,
                    'discount_type' => $itemData['discount_type'] ?? 'fixed',
                    'commission_rate' => $itemData['commission_rate'] ?? 0,
                ]);
                $item->calculateTotalPrice();
                $item->save();
            }

            // Recalculate totals
            $bill->calculateTotals();

            // Process new payments if provided
            if ($request->filled('payments') && is_array($request->payments)) {
                foreach ($request->payments as $paymentData) {
                    $attachmentPath = null;
                    if (($paymentData['amount'] ?? null)) {
                        // Handle file upload if attachment is provided
                        if (isset($paymentData['attachment']) && $paymentData['attachment']) {
                            $attachmentPath = $paymentData['attachment']->store('payments', 'public');
                        }

                        // Create payment record
                        $pay = \App\Models\Payment::create([
                            'bill_id' => $bill->id,
                            'user_id' => Auth::id(),
                            'payment_type' => 'due', // All bill payments are due payments
                            'amount' => $paymentData['amount'],
                            'previous_due_balance' => $billable->due_balance,
                            'previous_advance_balance' => $billable->advance_balance,
                            'payment_date' => $request->bill_date,
                            'payment_method' => $paymentData['payment_method'] ?? 'Nil',
                            'notes' => $paymentData['notes'] ?? null,
                            'reference_number' => null,
                            'account_id' => $paymentData['account_id'] ?? null,
                            'attachment' => $attachmentPath,
                        ]);
                        if ($pay->payment_method === 'Advance') {
                            $billable->decrement('advance_balance', $paymentData['amount']);
                        } elseif ($pay->payment_method === 'Commission') {
                            if ($billable instanceof Dealer) {
                                $billable->decrement('commission_balance', $paymentData['amount']);
                            }
                        } elseif ($pay->account_id) {
                            // Update account balance (increase balance as money is received)
                            $account = \App\Models\Account::find($pay->account_id);
                            $account->increment('balance', $pay->amount);
                        }

                        // Note: Due balance will be updated after all payments are processed
                    }
                }
            }

            // Update total paid and other calculated fields
            $bill->updateTotalPaid();

            // Update billable due balance (only for unpaid portion)
            $billable->increment('due_balance', $bill->due_amount);
        });

        CalculateDealerCommissions::dispatch($bill->id);
        CalculateSalesmanCommissions::dispatch($bill->id);

        $redirectRoute = $request->billable_type === 'App\Models\Dealer' ? 'dealer-bills.index' : 'customer-bills.index';
        $printRoute = route('bills.print', $bill);

        return redirect()->route($redirectRoute)
            ->with('success', 'Bill updated successfully.')
            ->with('print_url', $printRoute);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Bill $bill)
    {
        $this->authorize('update', $bill);

        $bill->load(['billable', 'salesman', 'items.product', 'payments', 'order']);

        $billableType = $bill->billable_type === 'App\Models\Dealer' ? 'dealer' : 'customer';

        // For salesmen, only show billables assigned to them
        if (user()->role === 'salesman') {
            if ($billableType === 'dealer') {
                $billables = Dealer::active()
                    ->whereHas('users', function ($q) {
                        $q->where('user_id', user()->id);
                    })
                    ->get();
            } else {
                $billables = Customer::active()->get();
            }
        } else {
            $billables = $billableType === 'dealer' ? Dealer::active()->get() : Customer::active()->get();
        }

        $salesmen = User::role('salesman')->get();
        $products = Product::active()->get();

        return view('bills.edit', compact('bill', 'billables', 'billableType', 'salesmen', 'products'));
    }

    /**
     * Display the specified resource.
     */
    public function show(Bill $bill)
    {
        $this->authorize('view', $bill);

        $bill->load(['billable', 'salesman', 'items.product', 'payments.user', 'transport', 'commissions']);

        return view('bills.show', compact('bill'));
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Bill $bill)
    {
        $this->authorize('delete', $bill);

        $bill->items()->delete();
        $bill->delete();

        $redirectRoute = $bill->billable_type === 'App\Models\Dealer' ? 'dealer-bills.index' : 'customer-bills.index';
        return redirect()->route($redirectRoute)
            ->with('success', 'Bill deleted successfully.');
    }


    /**
     * Helper method to calculate taxed amount
     */
    /**
     * Print the specified bill.
     */
    public function print(Bill $bill)
    {
        $this->authorize('view', $bill);

        $bill->load(['billable', 'salesman', 'items.product', 'deliveryPoint']);

        return view('bills.print', compact('bill'));
    }

    private function calculateTaxedAmount($amount, $tax, $taxType)
    {
        if ($tax <= 0) {
            return $amount;
        }

        if ($taxType === 'percentage') {
            return $amount * (1 + $tax / 100);
        } else {
            return $amount + $tax;
        }
    }
}
