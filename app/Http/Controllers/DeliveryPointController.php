<?php

namespace App\Http\Controllers;

use App\Models\DeliveryPoint;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;

class DeliveryPointController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = DeliveryPoint::query();

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('address_short', function ($point) {
                    return \Illuminate\Support\Str::limit($point->address, 50);
                })
                ->addColumn('usage_count', function ($point) {
                    return '<span class="badge badge-info">' . ($point->bills()->count() + $point->orders()->count()) . '</span>';
                })
                ->addColumn('status_badge', function ($point) {
                    return $point->is_active
                        ? '<span class="badge badge-success">Active</span>'
                        : '<span class="badge badge-secondary">Inactive</span>';
                })
                ->addColumn('created_date', function ($point) {
                    return $point->created_at->format('M d, Y');
                })
                ->addColumn('actions', function ($point) {
                    return view('actions.delivery-point-actions', compact('point'))->render();
                })
                ->rawColumns(['usage_count', 'status_badge', 'actions'])
                ->make(true);
        }

        return view('delivery-points.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('delivery-points.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $rules = [
            'name' => 'required|string|max:255',
            'address' => 'required|string',
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

        $point = DeliveryPoint::create($request->all());

        if ($request->ajax()) {
            return response()->json($point);
        } else {
            return redirect()->route('delivery-points.index')
                ->with('success', 'Delivery point created successfully.');
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(DeliveryPoint $deliveryPoint)
    {
        $deliveryPoint->load(['bills', 'orders']);
        return view('delivery-points.show', compact('deliveryPoint'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(DeliveryPoint $deliveryPoint)
    {
        $this->authorize('update', $deliveryPoint);

        return view('delivery-points.edit', compact('deliveryPoint'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, DeliveryPoint $deliveryPoint)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string',
            'is_active' => 'boolean',
        ]);

        $deliveryPoint->update($request->all());

        return redirect()->route('delivery-points.index')
            ->with('success', 'Delivery point updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DeliveryPoint $deliveryPoint)
    {
        if ($deliveryPoint->bills()->count() > 0 || $deliveryPoint->orders()->count() > 0) {
            return redirect()->route('delivery-points.index')
                ->with('error', 'Cannot delete delivery point with associated bills or orders.');
        }

        $deliveryPoint->delete();

        return redirect()->route('delivery-points.index')
            ->with('success', 'Delivery point deleted successfully.');
    }
}
