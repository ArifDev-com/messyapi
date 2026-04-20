<?php

namespace App\Http\Controllers;

use App\Models\ProductUnit;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;

class ProductUnitController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', ProductUnit::class);

        if ($request->ajax()) {
            $query = ProductUnit::query();

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('symbol_display', function ($unit) {
                    return $unit->symbol ? '<code>' . $unit->symbol . '</code>' : '<span class="text-muted">-</span>';
                })
                ->addColumn('products_count', function ($unit) {
                    return '<span class="badge badge-info">' . $unit->products()->count() . '</span>';
                })
                ->addColumn('status_badge', function ($unit) {
                    return $unit->is_active
                        ? '<span class="badge badge-success">Active</span>'
                        : '<span class="badge badge-secondary">Inactive</span>';
                })
                ->addColumn('created_date', function ($unit) {
                    return $unit->created_at->format('M d, Y');
                })
                ->addColumn('actions', function ($unit) {
                    return view('actions.product-unit-actions', compact('unit'))->render();
                })
                ->rawColumns(['symbol_display', 'products_count', 'status_badge', 'actions'])
                ->make(true);
        }

        return view('product-units.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create', ProductUnit::class);

        return view('product-units.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create', ProductUnit::class);

        $rules = [
            'name' => 'required|string|max:255',
            'symbol' => 'nullable|string|max:10',
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

        $unit = ProductUnit::create($request->all());

        if ($request->ajax()) {
            return response()->json($unit);
        } else {
            return redirect()->route('product-units.index')
                ->with('success', 'Product unit created successfully.');
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(ProductUnit $productUnit)
    {
        $this->authorize('view', $productUnit);

        $productUnit->load('products');
        return view('product-units.show', compact('productUnit'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ProductUnit $productUnit)
    {
        $this->authorize('update', $productUnit);

        return view('product-units.edit', compact('productUnit'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ProductUnit $productUnit)
    {
        $this->authorize('update', $productUnit);
        $request->validate([
            'name' => 'required|string|max:255',
            'symbol' => 'nullable|string|max:10',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $productUnit->update($request->all());

        return redirect()->route('product-units.index')
            ->with('success', 'Product unit updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ProductUnit $productUnit)
    {
        $this->authorize('delete', $productUnit);

        if ($productUnit->products()->count() > 0) {
            return redirect()->route('product-units.index')
                ->with('error', 'Cannot delete unit with associated products.');
        }

        $productUnit->delete();

        return redirect()->route('product-units.index')
            ->with('success', 'Product unit deleted successfully.');
    }
}
