<?php

namespace App\Http\Controllers;

use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;

class ProductCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', ProductCategory::class);

        if ($request->ajax()) {
            $query = ProductCategory::query();

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('code_display', function ($category) {
                    return '<code>' . $category->code . '</code>';
                })
                ->addColumn('products_count', function ($category) {
                    return '<span class="badge badge-info">' . $category->products()->count() . '</span>';
                })
                ->addColumn('status_badge', function ($category) {
                    return $category->is_active
                        ? '<span class="badge badge-success">Active</span>'
                        : '<span class="badge badge-secondary">Inactive</span>';
                })
                ->addColumn('created_date', function ($category) {
                    return $category->created_at->format('M d, Y');
                })
                ->addColumn('actions', function ($category) {
                    return view('actions.product-category-actions', compact('category'))->render();
                })
                ->rawColumns(['code_display', 'products_count', 'status_badge', 'actions'])
                ->make(true);
        }

        return view('product-categories.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create', ProductCategory::class);

        return view('product-categories.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create', ProductCategory::class);

        $rules = [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:product_categories',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ];

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['errors' => $validator->errors()], 422);
            } else {
                return redirect()->back()->withErrors($validator)->withInput();
            }
        }

        $category = ProductCategory::create($request->all());

        if ($request->ajax()) {
            return response()->json($category);
        } else {
            return redirect()->route('product-categories.index')
                ->with('success', 'Product category created successfully.');
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(ProductCategory $productCategory)
    {
        $this->authorize('view', $productCategory);

        $productCategory->load('products');
        return view('product-categories.show', compact('productCategory'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ProductCategory $productCategory)
    {
        $this->authorize('update', $productCategory);

        return view('product-categories.edit', compact('productCategory'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ProductCategory $productCategory)
    {
        $this->authorize('update', $productCategory);
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:product_categories,code,' . $productCategory->id,
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $productCategory->update($request->all());

        return redirect()->route('product-categories.index')
            ->with('success', 'Product category updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ProductCategory $productCategory)
    {
        $this->authorize('delete', $productCategory);

        if ($productCategory->products()->count() > 0) {
            return redirect()->route('product-categories.index')
                ->with('error', 'Cannot delete category with associated products.');
        }

        $productCategory->delete();

        return redirect()->route('product-categories.index')
            ->with('success', 'Product category deleted successfully.');
    }
}
