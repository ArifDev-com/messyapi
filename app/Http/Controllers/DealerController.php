<?php

namespace App\Http\Controllers;

use App\Models\Dealer;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;

class DealerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Dealer::class);

        if ($request->ajax()) {
            $query = Dealer::query();

            // For salesmen, only show dealers assigned to them
            if (user()->role === 'salesman') {
                $query->whereHas('users', function ($q) {
                    $q->where('user_id', user()->id);
                });
            }

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('dealer_info', function ($dealer) {
                    $html = '<div>';
                    $html .= '<div class="font-weight-bold text-dark">' . $dealer->name . '</div>';
                    $html .= '<div class="text-muted">' . $dealer->location . '</div>';
                    if ($dealer->trade_license) {
                        $html .= '<div class="text-muted small">License: ' . $dealer->trade_license . '</div>';
                    }
                    $html .= '</div>';
                    return $html;
                })
                ->addColumn('commission_display', function ($dealer) {
                    return '<div>' . $dealer->formatted_commission_rate . '</div>';
                })
                ->addColumn('monthly_commission_display', function ($dealer) {
                    return '<div>' . ($dealer->monthly_commission ? $dealer->monthly_commission . '%' : '-') . '</div>';
                })
                ->addColumn('yearly_commission_display', function ($dealer) {
                    return '<div>' . ($dealer->yearly_commission ? $dealer->yearly_commission . '%' : '-') . '</div>';
                })
                ->addColumn('target_display', function ($dealer) {
                    return $dealer->formatted_yearly_target;
                })
                ->addColumn('advance_display', function ($dealer) {
                    return '৳' . number_format($dealer->advance_balance, 2);
                })
                ->addColumn('due_display', function ($dealer) {
                    return '৳' . number_format($dealer->due_balance, 2);
                })
                ->addColumn('status_badge', function ($dealer) {
                    return '<span class="badge badge-pill ' . ($dealer->is_active ? 'badge-success' : 'badge-secondary') . '">' .
                           ($dealer->is_active ? 'Active' : 'Inactive') . '</span>';
                })
                ->addColumn('actions', function ($dealer) {
                    return view('actions.dealer-actions', compact('dealer'))->render();
                })
                ->rawColumns(['dealer_info', 'commission_display', 'monthly_commission_display', 'yearly_commission_display', 'target_display', 'advance_display', 'due_display', 'status_badge', 'actions'])
                ->make(true);
        }

        return view('dealers.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create', Dealer::class);

        $salesmen = \App\Models\User::role('salesman')->active()->get();

        return view('dealers.create', compact('salesmen'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Dealer::class);

        $rules = [
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20|unique:dealers',
            'location' => 'required|string|max:255',
            'trade_license' => 'nullable|string|max:100',
            'commission_rate' => 'required|numeric|min:0|max:100',
            'monthly_commission' => 'nullable|numeric|min:0|max:100',
            'yearly_commission' => 'nullable|numeric|min:0|max:100',
            'yearly_target' => 'required|numeric|min:0',
            'emergency_contact' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'is_active' => 'boolean',
            'salesmen' => 'nullable|array',
            'salesmen.*' => 'exists:users,id',
        ];

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['errors' => $validator->errors()], 422);
            } else {
                return redirect()->back()->withErrors($validator)->withInput();
            }
        }

        $dealer = Dealer::create($request->all());

        // Handle salesman assignments
        if ($request->has('salesmen')) {
            $dealer->users()->sync($request->salesmen);
        } elseif (user()->role === 'salesman') {
            // If no salesmen selected but current user is a salesman, auto-assign
            $dealer->users()->attach(user()->id);
        }

        if ($request->ajax()) {
            $dealer->load('users');
            return response()->json($dealer);
        } else {
            return redirect()->route('dealers.index')
                ->with('success', 'Dealer created successfully.');
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Dealer $dealer)
    {
        $this->authorize('view', $dealer);

        $dealer->load(['commissions' => function($query) {
            $query->orderBy('applied_date', 'desc');
        }]);

        return view('dealers.show', compact(
            'dealer',
        ));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Dealer $dealer)
    {
        $this->authorize('update', $dealer);

        $salesmen = \App\Models\User::role('salesman')->active()->get();

        return view('dealers.edit', compact('dealer', 'salesmen'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Dealer $dealer)
    {
        $this->authorize('update', $dealer);

        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'location' => 'required|string|max:255',
            'trade_license' => 'nullable|string|max:100',
            'commission_rate' => 'required|numeric|min:0|max:100',
            'monthly_commission' => 'nullable|numeric|min:0|max:100',
            'yearly_commission' => 'nullable|numeric|min:0|max:100',
            'yearly_target' => 'required|numeric|min:0',
            'emergency_contact' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'is_active' => 'boolean',
            'salesmen' => 'nullable|array',
            'salesmen.*' => 'exists:users,id',
        ]);

        $dealer->update($request->all());

        // Sync salesmen
        if ($request->has('salesmen')) {
            $dealer->users()->sync($request->salesmen);
        } else {
            $dealer->users()->detach();
        }

        return redirect()->route('dealers.index')
            ->with('success', 'Dealer updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Dealer $dealer)
    {
        $this->authorize('delete', $dealer);

        // Check if dealer has any bills
        if ($dealer->dealerBills()->count() > 0) {
            return redirect()->route('dealers.index')
                ->with('error', 'Cannot delete dealer. This dealer has associated bills.');
        }

        $dealer->delete();

        return redirect()->route('dealers.index')
            ->with('success', 'Dealer deleted successfully.');
    }
}
