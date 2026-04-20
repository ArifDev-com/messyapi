<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductUnit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Yajra\DataTables\DataTables;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Product::class);

        if ($request->ajax()) {
            $query = Product::with(['category', 'unit']);

            // Apply stocked filter
            if ($request->has('stocked') && $request->stocked == '1') {
                $query->where('stock_quantity', '>', 0);
            }

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('product_code', function ($product) {
                    return $product->product_code ?? 'N/A';
                })
                ->addColumn('image_display', function ($product) {
                    if ($product->image) {
                        return '<img src="' . asset($product->image) . '" alt="' . $product->name . '" class="img-thumbnail" style="width: 48px; height: 48px; object-fit: cover;">';
                    } else {
                        return '<div class="bg-secondary rounded d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;"><span class="text-white small">No Image</span></div>';
                    }
                })
                ->addColumn('product_info', function ($product) {
                    $html = '<div>';
                    $html .= '<div class="font-weight-bold">' . $product->name . '</div>';
                    $html .= '<div class="small text-muted">' . Str::limit($product->description, 50) . '</div>';
                    // if ($product->barcode) {
                    //     $html .= '<div class="small text-muted">Barcode: ' . $product->barcode . '</div>';
                    // }
                    $html .= '</div>';
                    return $html;
                })
                ->addColumn('category', function ($product) {
                    return $product->category->name ?? 'N/A';
                })
                ->addColumn('unit', function ($product) {
                    return $product->unit->name ?? 'N/A';
                })
                ->addColumn('sale_price', function ($product) {
                    return $product->formatted_sales_price;
                })
                ->addColumn('purchase_price', function ($product) {
                    return $product->formatted_purchase_price;
                })
                ->addColumn('stock_info', function ($product) {
                    $class = $product->stock_quantity <= $product->minimum_stock ? 'text-danger font-weight-bold' : '';
                    $html = '<div class="' . $class . '">' . $product->stock_quantity . '</div>';
                    return $html;
                })
                ->addColumn('weight', function ($product) {
                    return $product->weight ? $product->weight . ' kg' : 'N/A';
                })
                ->addColumn('commission_rate', function ($product) {
                    return $product->commission_rate ? $product->commission_rate . '%' : 'N/A';
                })
                ->addColumn('status_badge', function ($product) {
                    return '<span class="badge ' . ($product->is_active ? 'badge-success' : 'badge-danger') . ' p-2">' .
                           ($product->is_active ? 'Active' : 'Inactive') . '</span>';
                })
                ->addColumn('actions', function ($product) {
                    return view('actions.product-actions', compact('product'))->render();
                })
                ->rawColumns(['image_display', 'product_info', 'stock_info', 'status_badge', 'actions'])
                ->make(true);
        }

        $categories = ProductCategory::all();

        // Calculate totals for all products (or filtered if needed, but for now all)
        $allQuery = Product::query();
        if ($request->has('stocked') && $request->stocked == '1') {
            $allQuery->where('stock_quantity', '>', 0);
        }
        $totals = [
            'sale_price' => $allQuery->sum('sales_price'),
            'purchase_price' => $allQuery->sum('purchase_price'),
            'stock_quantity' => $allQuery->sum('stock_quantity'),
            'minimum_stock' => $allQuery->sum('minimum_stock'),
            'tax_percentage' => $allQuery->sum('tax_percentage'),
            'discount_value' => $allQuery->sum('discount_value'),
        ];

        return view('products.index', compact('categories', 'totals'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create', Product::class);

        $categories = ProductCategory::all();
        $units = ProductUnit::all();

        return view('products.create', compact('categories', 'units'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Product::class);

        $rules = [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'barcode' => 'nullable|string|max:100|unique:products',
            'price' => 'required|numeric|min:0',
            'sales_price' => 'required|numeric|min:0',
            'purchase_price' => 'required|numeric|min:0',
            'tax_percentage' => 'nullable|numeric|min:0|max:100',
            'tax_type' => 'nullable|in:inclusive,exclusive',
            'discount_value' => 'nullable|numeric|min:0',
            'discount_type' => 'nullable|in:percentage,fixed',
            'stock_quantity' => 'required|integer|min:0',
            'weight' => 'nullable|numeric|min:0',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'category_id' => 'required|exists:product_categories,id',
            'unit_id' => 'required|exists:product_units,id',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'is_active' => 'boolean',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['errors' => $validator->errors()], 422);
            } else {
                return redirect()->back()->withErrors($validator)->withInput();
            }
        }

        $data = $request->all();
        $data['tax_percentage'] = $data['tax_percentage'] ?? 0;
        $data['tax_type'] = $data['tax_type'] ?? 'inclusive';
        $data['discount_value'] = $data['discount_value'] ?? 0;
        $data['discount_type'] = $data['discount_type'] ?? 'fixed';
        $data['minimum_stock'] = 1;

        // Handle image upload
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time() . '_' . $image->getClientOriginalName();
            $image->move(public_path('images/products'), $imageName);
            $data['image'] = 'images/products/' . $imageName;
        }

        $product = Product::create($data);

        if ($request->ajax()) {
            return response()->json($product);
        } else {
            return redirect()->route('products.index')
                ->with('success', 'Product created successfully.');
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product)
    {
        $this->authorize('view', $product);

        $product->load(['category', 'unit']);
        return view('products.show', compact('product'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Product $product)
    {
        $this->authorize('update', $product);
        $categories = ProductCategory::all();
        $units = ProductUnit::all();

        return view('products.edit', compact('product', 'categories', 'units'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Product $product)
    {
        $this->authorize('update', $product);
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'barcode' => 'nullable|string|max:100|unique:products,barcode,' . $product->id,
            'price' => 'required|numeric|min:0',
            'sales_price' => 'required|numeric|min:0',
            'purchase_price' => 'required|numeric|min:0',
            'tax_percentage' => 'nullable|numeric|min:0|max:100',
            'tax_type' => 'nullable|in:inclusive,exclusive',
            'discount_value' => 'nullable|numeric|min:0',
            'discount_type' => 'nullable|in:percentage,fixed',
            'stock_quantity' => 'required|integer|min:0',
            'weight' => 'nullable|numeric|min:0',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'category_id' => 'required|exists:product_categories,id',
            'unit_id' => 'required|exists:product_units,id',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'is_active' => 'boolean',
        ]);

        $data = $request->all();
        $data['tax_percentage'] = $data['tax_percentage'] ?? 0;
        $data['tax_type'] = $data['tax_type'] ?? 'inclusive';
        $data['discount_value'] = $data['discount_value'] ?? '0';
        $data['discount_type'] = $data['discount_type'] ?? 'fixed';
        // Handle image upload
        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($product->image && file_exists(public_path($product->image))) {
                unlink(public_path($product->image));
            }

            $image = $request->file('image');
            $imageName = time() . '_' . $image->getClientOriginalName();
            $image->move(public_path('images/products'), $imageName);
            $data['image'] = 'images/products/' . $imageName;
        }

        $product->update($data);

        return redirect()->route('products.index')
            ->with('success', 'Product updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product)
    {
        $this->authorize('delete', $product);

        // Delete associated image if exists
        if ($product->image && file_exists(public_path($product->image))) {
            unlink(public_path($product->image));
        }

        $product->delete();

        return redirect()->route('products.index')
            ->with('success', 'Product deleted successfully.');
    }

    /**
     * Export products to CSV.
     */
    public function export(Request $request)
    {
        $this->authorize('viewAny', Product::class);

        $query = Product::with(['category', 'unit']);

        // Apply stocked filter
        if ($request->has('stocked') && $request->stocked == '1') {
            $query->where('stock_quantity', '>', 0);
        }

        $products = $query->get();
        $filename = 'products_' . date('Y-m-d_H-i-s') . '.csv';

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
                'Name',
                'Description',
                'Barcode',
                'Category',
                'Unit',
                'Sale Price',
                'Purchase Price',
                'Stock Quantity',
                'Minimum Stock',
                'Tax Percentage',
                'Tax Type',
                'Discount Value',
                'Discount Type',
                'Weight',
                'Commission Rate',
                'Is Active',
                'Image',
                'Created At',
                'Updated At',
            ]);

            // Write data
            $serial = 1;
            foreach ($products as $product) {
                fputcsv($file, [
                    $serial++,
                    $product->id,
                    $product->product_code,
                    $product->name,
                    $product->description,
                    $product->barcode,
                    $product->category->name ?? 'N/A',
                    $product->unit->name ?? 'N/A',
                    $product->sales_price,
                    $product->purchase_price,
                    $product->stock_quantity,
                    $product->minimum_stock,
                    $product->tax_percentage,
                    $product->tax_type,
                    $product->discount_value,
                    $product->discount_type,
                    $product->weight,
                    $product->commission_rate,
                    $product->is_active ? 'Yes' : 'No',
                    $product->image ? asset($product->image) : '',
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
                '',
                $products->sum('sales_price'),
                $products->sum('purchase_price'),
                $products->sum('stock_quantity'),
                $products->sum('minimum_stock'),
                $products->sum('tax_percentage'),
                '',
                $products->sum('discount_value'),
                '',
                '',
                '',
                '',
                '',
            ]);

            fclose($file);
        };

        return response()->streamDownload($callback, $filename, $headers);
    }
}
