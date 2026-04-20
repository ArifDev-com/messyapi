<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Dealer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Yajra\DataTables\DataTables;

class OrderController extends Controller
{
    /**
     * Display a listing of dealer orders.
     */
    public function dealerOrdersIndex(Request $request)
    {
        $this->authorize('viewAny', Order::class);

        if ($request->ajax()) {
            $query = Order::with(['orderable', 'salesman'])
                ->where('orderable_type', 'App\Models\Dealer')
                ->latest();

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('order_number_link', function ($order) {
                    return '<strong><a href="' . route( 'orders.show', $order) . '">' . $order->order_number . '</a></strong>';
                })
                ->addColumn('dealer_name', function ($order) {
                    return $order->orderable->name;
                })
                ->addColumn('salesman_name', function ($order) {
                    return $order->salesman->name;
                })
                ->addColumn('order_date_formatted', function ($order) {
                    return $order->order_date->format('M d, Y');
                })
                ->addColumn('total_amount', function ($order) {
                    return $order->total_amount;
                })
                ->addColumn('status_badge', function ($order) {
                    return '<span class="badge badge-' . $order->status_badge . '">' . ucfirst($order->status) . '</span>';
                })
                ->addColumn('actions', function ($order) {
                    return view('actions.order-actions', compact('order'))->render();
                })
                ->rawColumns(['order_number_link', 'status_badge', 'actions'])
                ->make(true);
        }

        $dealers = Dealer::active()->get();
        return view('orders.dealer-index', compact('dealers'));
    }

    /**
     * Display a listing of customer orders.
     */
    public function customerOrdersIndex(Request $request)
    {
        $this->authorize('viewAny', Order::class);

        if ($request->ajax()) {
            $query = Order::with(['orderable', 'salesman'])
                ->where('orderable_type', 'App\Models\Customer')
                ->latest();

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('order_number_link', function ($order) {
                    return '<strong><a href="' . route('orders.show', $order) . '">' . $order->order_number . '</a></strong>';
                })
                ->addColumn('customer_name', function ($order) {
                    return $order->orderable->name;
                })
                ->addColumn('salesman_name', function ($order) {
                    return $order->salesman->name;
                })
                ->addColumn('order_date_formatted', function ($order) {
                    return $order->order_date->format('M d, Y');
                })
                ->addColumn('total_amount_display', function ($order) {
                    return $order->formatted_total_amount;
                })
                ->addColumn('status_badge', function ($order) {
                    return '<span class="badge badge-' . $order->status_badge . '">' . ucfirst($order->status) . '</span>';
                })
                ->addColumn('actions', function ($order) {
                    return view('actions.order-actions', compact('order'))->render();
                })
                ->rawColumns(['order_number_link', 'status_badge', 'actions'])
                ->make(true);
        }

        $customers = Customer::active()->get();
        return view('orders.customer-index', compact('customers'));
    }

    /**
     * Show the form for creating a dealer order.
     */
    public function dealerOrdersCreate(Request $request)
    {
        $this->authorize('create', Order::class);

        // For salesmen, only show dealers assigned to them
        if (user()->role === 'salesman') {
            $orderables = Dealer::active()
                ->whereHas('users', function ($q) {
                    $q->where('user_id', user()->id);
                })
                ->get();
        } else {
            $orderables = Dealer::active()->get();
        }

        $orderableType = 'dealer';
        $salesmen = User::role('salesman')->get();
        $products = Product::active()->get();

        return view('orders.create', compact('orderables', 'orderableType', 'salesmen', 'products'));
    }

    /**
     * Show the form for creating a customer order.
     */
    // public function customerOrdersCreate(Request $request)
    // {
    //     $this->authorize('create', Order::class);

    //     $orderables = Customer::active()->get();
    //     $orderableType = 'customer';
    //     $salesmen = User::role('salesman')->get();
    //     $products = Product::active()->get();

    //     return view('customer-orders.create', compact('orderables', 'orderableType', 'salesmen', 'products'));
    // }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Order::class);
        $request->validate([
            'orderable_type' => 'required|in:App\Models\Dealer,App\Models\Customer',
            'orderable_id' => 'required|integer',
            'salesman_id' => 'required|exists:users,id',
            'order_date' => 'required|date',
            'delivery_point_id' => 'nullable|exists:delivery_points,id',
            'transport_id' => 'nullable|exists:transports,id',
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
            'items.*.commission_rate' => 'required|numeric|min:0|max:100',
            'advance_payment' => 'nullable|numeric|min:0',
            'payment_method' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $order = null;
        DB::transaction(function () use ($request, &$order) {
            // Generate order number
            $count = Order::latest()->first()?->id ?: 1;
            $count++;
            $orderNumber = 'O-' . date('Y') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
            while(Order::where('order_number', $orderNumber)->exists()) {
                $orderNumber = 'O-' . date('Y') . '-' . str_pad((++$count) + 1, 4, '0', STR_PAD_LEFT);
            }

            // Create order items first to calculate totals
            $tempItems = [];
            foreach ($request->items as $itemData) {
                $item = new OrderItem([
                    'product_id' => $itemData['product_id'],
                    'size_inc' => $itemData['size_inc'] ?? null,
                    'pieces' => $itemData['pieces'] ?? null,
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'tax_percentage' => $itemData['tax_percentage'] ?? 0,
                    'tax_type' => $itemData['tax_type'] ?? 'exclusive',
                    'discount_value' => $itemData['discount_value'] ?? 0,
                    'discount_type' => $itemData['discount_type'] ?? 'fixed',
                    'commission_rate' => $itemData['commission_rate'],
                ]);
                $tempItems[] = $item;
            }

            // Calculate subtotal from items
            $subtotal = 0;
            foreach ($tempItems as $item) {
                $subtotal += $item->calculateTotalPrice();
            }
            // Create order with calculated totals
            $order = Order::create([
                'order_number' => $orderNumber,
                'orderable_type' => $request->orderable_type,
                'orderable_id' => $request->orderable_id,
                'salesman_id' => $request->salesman_id,
                'delivery_point_id' => $request->delivery_point_id,
                'transport_id' => $request->transport_id,
                'order_date' => $request->order_date,
                'subtotal' => $subtotal,
                'discount' => 0,
                'discount_type' => 'fixed',
                'tax' => 0,
                'total_amount' => $subtotal,
                'advance_payment' => $request->advance_payment ?? 0,
                'payment_method' => $request->payment_method,
                'status' => 'draft',
                'notes' => $request->notes,
                'gross_price' => 0,
                'comission_amount' => 0,
                'discount_amount' => 0
            ]);

            // Create order items with the order_id
            foreach ($tempItems as $item) {
                $item->order_id = $order->id;
                $item->save();
            }

            // Update total calculated fields
            $order->calculateTotals();
        });

        $redirectRoute = $request->orderable_type === 'App\Models\Dealer' ? 'dealer-orders.index' : 'customer-orders.index';
        $printRoute = route('orders.print', $order);

        return redirect()->route($redirectRoute)
            ->with('success', 'Order created successfully.')
            ->with('print_url', $printRoute);
    }

    /**
     * Display the specified resource.
     */
    public function show(Order $order)
    {
        $this->authorize('view', $order);

        $order->load(['orderable', 'salesman', 'items.product', 'transport']);

        return view('orders.show', compact('order'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Order $order)
    {
        $this->authorize('update', $order);

        $order->load(['items.product']);

        // Get orderable entities based on type
        $orderableType = $order->orderable_type === 'App\Models\Dealer' ? 'dealer' : 'customer';
        $orderables = collect();

        if ($orderableType === 'dealer') {
            // For salesmen, only show dealers assigned to them
            if (user()->role === 'salesman') {
                $orderables = Dealer::active()
                    ->whereHas('users', function ($q) {
                        $q->where('user_id', user()->id);
                    })
                    ->get();
            } else {
                $orderables = Dealer::active()->get();
            }
        } else {
            $orderables = Customer::active()->get();
        }

        $salesmen = User::role('salesman')->get();
        $products = Product::active()->get();

        return view('orders.edit', compact('order', 'orderables', 'orderableType', 'salesmen', 'products'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Order $order)
    {
        $this->authorize('update', $order);
        $request->validate([
            'orderable_type' => 'required|in:App\Models\Dealer,App\Models\Customer',
            'orderable_id' => 'required|integer',
            'salesman_id' => 'required|exists:users,id',
            'order_date' => 'required|date',
            'delivery_point_id' => 'nullable|exists:delivery_points,id',
            'transport_id' => 'nullable|exists:transports,id',
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
            'items.*.commission_rate' => 'required|numeric|min:0|max:100',
            'advance_payment' => 'nullable|numeric|min:0',
            'payment_method' => 'nullable|string|max:255',
            'discount' => 'nullable|numeric|min:0',
            'discount_type' => 'nullable|in:percentage,fixed',
            'notes' => 'nullable|string',
        ]);

        DB::transaction(function () use ($request, $order) {
            $order->items()->delete();
            // Create order items first to calculate totals
            $tempItems = [];
            foreach ($request->items as $itemData) {
                $item = new OrderItem([
                    'product_id' => $itemData['product_id'],
                    'size_inc' => $itemData['size_inc'] ?? null,
                    'pieces' => $itemData['pieces'] ?? null,
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'tax_percentage' => $itemData['tax_percentage'] ?? 0,
                    'tax_type' => $itemData['tax_type'] ?? 'exclusive',
                    'discount_value' => $itemData['discount_value'] ?? 0,
                    'discount_type' => $itemData['discount_type'] ?? 'fixed',
                    'commission_rate' => $itemData['commission_rate'],
                ]);
                $tempItems[] = $item;
            }

            // Calculate subtotal from items
            $subtotal = 0;
            foreach ($tempItems as $item) {
                $subtotal += $item->calculateTotalPrice();
            }
            // Create order with calculated totals
            $order->update([
                'orderable_type' => $request->orderable_type,
                'orderable_id' => $request->orderable_id,
                'salesman_id' => $request->salesman_id,
                'delivery_point_id' => $request->delivery_point_id,
                'transport_id' => $request->transport_id,
                'order_date' => $request->order_date,
                'subtotal' => $subtotal,
                'discount' => 0,
                'discount_type' => 'fixed',
                'tax' => 0,
                'total_amount' => $subtotal,
                'advance_payment' => $request->advance_payment ?? 0,
                'payment_method' => $request->payment_method,
                'status' => 'draft',
                'notes' => $request->notes,
                'gross_price' => 0,
                'comission_amount' => 0,
                'discount_amount' => 0
            ]);

            // Create order items with the order_id
            foreach ($tempItems as $item) {
                $item->order_id = $order->id;
                $item->save();
            }

            // Update total calculated fields
            $order->calculateTotals();
        });

        $redirectRoute = $order->orderable_type === 'App\Models\Dealer' ? 'dealer-orders.index' : 'customer-orders.index';
        return redirect()->route($redirectRoute)
            ->with('success', 'Order updated successfully.');
    }

    /**
     * Print the specified order.
     */
    public function print(Order $order)
    {
        $this->authorize('view', $order);

        $order->load(['orderable', 'salesman', 'items.product', 'deliveryPoint', 'transport']);

        return view('orders.print', compact('order'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Order $order)
    {
        $this->authorize('delete', $order);

        $order->items()->delete();
        $order->delete();

        $redirectRoute = $order->orderable_type === 'App\Models\Dealer' ? 'dealer-orders.index' : 'customer-orders.index';
        return redirect()->route($redirectRoute)
            ->with('success', 'Order deleted successfully.');
    }

    /**
     * Helper method to calculate discounted amount
     */
    private function calculateDiscountedAmount($amount, $discount, $discountType)
    {
        if ($discount <= 0) {
            return $amount;
        }

        if ($discountType === 'percentage') {
            return $amount * (1 - $discount / 100);
        } else {
            return max(0, $amount - $discount);
        }
    }
}
