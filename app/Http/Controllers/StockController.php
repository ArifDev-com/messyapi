<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\StockHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Yajra\DataTables\DataTables;

class StockController extends Controller
{
    /**
     * Display a listing of products with stock information.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Product::class);

        if ($request->ajax()) {
            $query = Product::with(['category', 'unit']);

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('product_info', function ($product) {
                    $html = '<strong>' . $product->name . '</strong>';
                    if ($product->description) {
                        $html .= '<br><small class="text-muted">' . Str::limit($product->description, 30) . '</small>';
                    }
                    return $html;
                })
                ->addColumn('barcode_display', function ($product) {
                    return $product->barcode ? '<code>' . $product->barcode . '</code>' : '<span class="text-muted">-</span>';
                })
                ->addColumn('category_name', function ($product) {
                    return $product->category->name ?? '-';
                })
                ->addColumn('current_stock', function ($product) {
                    $badgeClass = $product->stock_quantity <= $product->minimum_stock ? 'badge-danger' : 'badge-success';
                    $html = '<span class="badge ' . $badgeClass . '">' . $product->stock_quantity . ' ' . ($product->unit->symbol ?? '') . '</span>';
                    if ($product->stock_quantity <= $product->minimum_stock) {
                        $html .= '<br><small class="text-danger">Low Stock!</small>';
                    }
                    return $html;
                })
                ->addColumn('min_stock', function ($product) {
                    return $product->minimum_stock . ' ' . ($product->unit->symbol ?? '');
                })
                ->addColumn('status_badge', function ($product) {
                    return $product->is_active
                        ? '<span class="badge badge-success">Active</span>'
                        : '<span class="badge badge-secondary">Inactive</span>';
                })
                ->addColumn('w_price_display', function ($product) {
                    if ($product->price) {
                        $commissionAmount = $product->price * ($product->commission_rate ?? 0) / 100;
                        $priceAfterCommission = $product->price - $commissionAmount;
                        return '৳' . number_format($priceAfterCommission, 2);
                    }
                    return 'N/A';
                })
                ->addColumn('price_display', function ($product) {
                    return $product->formatted_price;
                })
                ->addColumn('weight', function ($product) {
                    return $product->weight ? $product->weight . ' kg' : 'N/A';
                })
                ->addColumn('total_weight', function ($product) {
                    if ($product->weight && $product->stock_quantity) {
                        $totalWeight = $product->stock_quantity * $product->weight;
                        return number_format($totalWeight, 2) . ' kg';
                    }
                    return 'N/A';
                })
                ->addColumn('g_amount', function ($product) {
                    if ($product->price && $product->stock_quantity) {
                        $totalAmount = $product->stock_quantity * $product->price;
                        return '৳' . number_format($totalAmount, 2);
                    }
                    return 'N/A';
                })
                ->addColumn('commission_rate', function ($product) {
                    return $product->commission_rate ? $product->commission_rate . '%' : '0%';
                })
                ->addColumn('net_amount', function ($product) {
                    if ($product->price && $product->stock_quantity) {
                        $grossAmount = $product->stock_quantity * $product->price;
                        $commissionAmount = $grossAmount * ($product->commission_rate ?? 0) / 100;
                        $netAmount = $grossAmount - $commissionAmount;
                        return '৳' . number_format($netAmount, 2);
                    }
                    return 'N/A';
                })
                ->addColumn('actions', function ($product) {
                    return view('actions.stock-actions', ['product' => $product])->render();
                })
                ->rawColumns(['product_info', 'barcode_display', 'current_stock', 'status_badge', 'actions'])
                ->make(true);
        }

        $categories = ProductCategory::all();
        return view('stocks.index', compact('categories'));
    }

    /**
     * Show the form for adding stock to a product.
     */
    public function create()
    {
        $this->authorize('update', Product::class); // Only managers and admins can modify stock

        $products = Product::active()->get();
        return view('stocks.create', compact('products'));
    }

    /**
     * Store stock addition.
     */
    public function store(Request $request)
    {
        $this->authorize('update', Product::class); // Only managers and admins can modify stock
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'reason' => 'nullable|string|max:255',
        ]);

        $product = Product::findOrFail($request->product_id);
        $previousQuantity = $product->stock_quantity;

        $product->increment('stock_quantity', $request->quantity);
        $newQuantity = $product->fresh()->stock_quantity;

        // Record stock history
        StockHistory::create([
            'product_id' => $product->id,
            'user_id' => user()->id,
            'action' => 'add',
            'quantity_changed' => $request->quantity,
            'previous_quantity' => $previousQuantity,
            'new_quantity' => $newQuantity,
            'reason' => $request->reason,
        ]);

        return redirect()->route('stocks.index')
            ->with('success', "Stock added successfully. New quantity: {$newQuantity}");
    }

    /**
     * Display stock details for a specific product.
     */
    public function show(Product $stock)
    {
        $this->authorize('view', $stock);

        $stock->load(['category', 'unit']);
        $stockHistory = StockHistory::where('product_id', $stock->id)
            ->with('user')
            ->latest()
            ->paginate(10);

        return view('stocks.show', [
            'product' => $stock,
            'stockHistory' => $stockHistory
        ]);
    }

    /**
     * Show the form for adjusting stock.
     */
    public function edit(Product $stock)
    {
        $this->authorize('update', $stock);

        return view('stocks.edit', ['product' => $stock]);
    }

    /**
     * Update stock quantity.
     */
    public function update(Request $request, Product $stock)
    {
        $this->authorize('update', $stock);
        $request->validate([
            'stock_quantity' => 'required|integer|min:0',
            'minimum_stock' => 'required|integer|min:0',
            'reason' => 'nullable|string|max:255',
        ]);

        $oldQuantity = $stock->stock_quantity;

        $stock->update([
            'stock_quantity' => $request->stock_quantity,
            'minimum_stock' => $request->minimum_stock,
        ]);

        // Record stock history if quantity changed
        if ($oldQuantity != $request->stock_quantity) {
            $quantityChanged = $request->stock_quantity - $oldQuantity;
            StockHistory::create([
                'product_id' => $stock->id,
                'user_id' => user()->id,
                'action' => 'adjust',
                'quantity_changed' => $quantityChanged,
                'previous_quantity' => $oldQuantity,
                'new_quantity' => $request->stock_quantity,
                'reason' => $request->reason,
            ]);
        }

        $change = $request->stock_quantity - $oldQuantity;
        $message = $change > 0
            ? "Stock increased by {$change} units. New quantity: {$request->stock_quantity}"
            : ($change < 0
                ? "Stock decreased by " . abs($change) . " units. New quantity: {$request->stock_quantity}"
                : "Stock quantity updated to {$request->stock_quantity}");

        return redirect()->route('stocks.index')
            ->with('success', $message);
    }

    /**
     * Display stock history report.
     */
    public function history(Request $request)
    {
        $this->authorize('viewAny', Product::class);

        if ($request->ajax()) {
            $query = StockHistory::with(['product.category', 'product.unit', 'user'])
                ->latest();

            // Apply filters
            if ($request->filled('product_id')) {
                $query->where('product_id', $request->product_id);
            }

            if ($request->filled('action')) {
                $query->where('action', $request->action);
            }

            if ($request->filled('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            if ($request->filled('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('product_name', function ($history) {
                    return $history->product->name ?? 'Unknown Product';
                })
                ->addColumn('category_name', function ($history) {
                    return $history->product->category->name ?? '-';
                })
                ->addColumn('user_name', function ($history) {
                    return $history->user->name ?? 'Unknown User';
                })
                ->addColumn('action_badge', function ($history) {
                    return $history->action_badge;
                })
                ->addColumn('quantity_changed_display', function ($history) {
                    return $history->formatted_quantity_changed;
                })
                ->addColumn('previous_quantity_display', function ($history) {
                    return $history->previous_quantity;
                })
                ->addColumn('new_quantity_display', function ($history) {
                    return $history->new_quantity;
                })
                ->addColumn('reason_display', function ($history) {
                    return $history->reason ?? '<span class="text-muted">-</span>';
                })
                ->addColumn('created_at_formatted', function ($history) {
                    return $history->created_at->format('M d, Y H:i');
                })
                ->rawColumns(['action_badge', 'reason_display'])
                ->make(true);
        }

        $products = Product::active()->get();
        $users = \App\Models\User::all();
        return view('stocks.history', compact('products', 'users'));
    }

    /**
     * Export stock history to CSV.
     */
    public function export(Request $request)
    {
        $this->authorize('viewAny', Product::class);

        $query = StockHistory::with(['product.category', 'product.unit', 'user']);

        // Apply filters
        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $stockHistories = $query->latest()->get();
        $filename = 'stock_history_' . date('Y-m-d_H-i-s') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($stockHistories) {
            $file = fopen('php://output', 'w');

            // Write headers
            fputcsv($file, [
                'Serial',
                'ID',
                'Product Name',
                'Category',
                'User Name',
                'Action',
                'Quantity Changed',
                'Previous Quantity',
                'New Quantity',
                'Reason',
                'Created At',
                'Updated At',
            ]);

            // Write data
            $serial = 1;
            foreach ($stockHistories as $history) {
                fputcsv($file, [
                    $serial++,
                    $history->id,
                    $history->product->name ?? 'Unknown Product',
                    $history->product->category->name ?? 'N/A',
                    $history->user->name ?? 'Unknown User',
                    $history->action,
                    $history->quantity_changed,
                    $history->previous_quantity,
                    $history->new_quantity,
                    $history->reason ?? '',
                    $history->created_at,
                    $history->updated_at,
                ]);
            }

            fclose($file);
        };

        return response()->streamDownload($callback, $filename, $headers);
    }

    /**
     * Export stocks to CSV.
     */
    public function exportStocks(Request $request)
    {
        $this->authorize('viewAny', Product::class);

        $query = Product::with(['category', 'unit']);

        $products = $query->get();
        $filename = 'stocks_' . date('Y-m-d_H-i-s') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($products) {
            $file = fopen('php://output', 'w');

            // Write headers
            fputcsv($file, [
                'Serial',
                'ID',
                'Product Code',
                'Product Name',
                'Category',
                'Unit',
                'Stock Quantity',
                'Minimum Stock',
                'Purchase Price',
                'Sales Price',
                'Weight',
                'Commission Rate',
                'Total Weight',
                'Gross Amount',
                'Net Amount',
                'Is Active',
                'Created At',
                'Updated At',
            ]);

            // Write data
            $serial = 1;
            foreach ($products as $product) {
                $grossAmount = $product->price && $product->stock_quantity ? $product->stock_quantity * $product->price : 0;
                $commissionAmount = $grossAmount * ($product->commission_rate ?? 0) / 100;
                $netAmount = $grossAmount - $commissionAmount;
                $totalWeight = $product->weight && $product->stock_quantity ? $product->stock_quantity * $product->weight : 0;

                fputcsv($file, [
                    $serial++,
                    $product->id,
                    $product->product_code,
                    $product->name,
                    $product->category->name ?? 'N/A',
                    $product->unit->name ?? 'N/A',
                    $product->stock_quantity,
                    $product->minimum_stock,
                    $product->purchase_price,
                    $product->price,
                    $product->weight,
                    $product->commission_rate,
                    $totalWeight ? number_format($totalWeight, 2) : 'N/A',
                    $grossAmount ? number_format($grossAmount, 2) : 'N/A',
                    $netAmount ? number_format($netAmount, 2) : 'N/A',
                    $product->is_active ? 'Yes' : 'No',
                    $product->created_at,
                    $product->updated_at,
                ]);
            }

            // Write totals row
            fputcsv($file, [
                'Total',
                '',
                '',
                '',
                '',
                '',
                $products->sum('stock_quantity'),
                $products->sum('minimum_stock'),
                $products->sum('purchase_price'),
                $products->sum('price'),
                '',
                '',
                '',
                $products->sum(function($product) {
                    return $product->price && $product->stock_quantity ? $product->stock_quantity * $product->price : 0;
                }),
                $products->sum(function($product) {
                    $grossAmount = $product->price && $product->stock_quantity ? $product->stock_quantity * $product->price : 0;
                    $commissionAmount = $grossAmount * ($product->commission_rate ?? 0) / 100;
                    return $grossAmount - $commissionAmount;
                }),
                '',
                '',
            ]);

            fclose($file);
        };

        return response()->streamDownload($callback, $filename, $headers);
    }
}
