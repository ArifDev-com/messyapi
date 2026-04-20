<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;

class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = Customer::query();

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('customer_code_bold', function ($customer) {
                    return '<strong>' . $customer->customer_code . '</strong>';
                })
                ->addColumn('name_with_email', function ($customer) {
                    $html = '<div class="font-weight-bold">' . $customer->name . '</div>';
                    if ($customer->email) {
                        $html .= '<small class="text-muted">' . $customer->email . '</small>';
                    }
                    return $html;
                })
                ->addColumn('type_badge', function ($customer) {
                    return '<span class="badge badge-' . ($customer->type == 'company' ? 'info' : 'secondary') . ' p-2">' .
                           ucfirst($customer->type) . '</span>';
                })
                ->addColumn('location', function ($customer) {
                    if ($customer->city || $customer->country) {
                        return ($customer->city ? $customer->city . ', ' : '') . $customer->country;
                    } else {
                        return '<span class="text-muted">N/A</span>';
                    }
                })
                ->addColumn('status_badge', function ($customer) {
                    return $customer->is_active
                        ? '<span class="badge badge-success p-2">Active</span>'
                        : '<span class="badge badge-secondary p-2">Inactive</span>';
                })
                ->addColumn('actions', function ($customer) {
                    return view('actions.customer-actions', compact('customer'))->render();
                })
                ->rawColumns(['customer_code_bold', 'name_with_email', 'type_badge', 'location', 'status_badge', 'actions'])
                ->make(true);
        }

        return view('customers.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    // public function create()
    // {
    //     return view('customers.create');
    // }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'required|string|max:20',
            'address' => 'nullable|string',
            'customer_code' => 'nullable|string|max:50|unique:customers',
            'type' => 'required|max:50',
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

        $data = $request->all();
        if(!($data['address'] ?? null)) {
            $data['address'] = '';
        }
        if(!($data['customer_code'] ?? null)) {
            $code = 1000 + (Customer::latest()->first()?->id ?: 1);
            while(Customer::where('customer_code', $code)->exists()) {
                $code++;
            }
            $data['customer_code'] = $code;
        }
        $customer = Customer::create($data);

        if ($request->ajax()) {
            return response()->json($customer);
        } else {
            return redirect()->route('customers.index')
                ->with('success', 'Customer created successfully.');
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Customer $customer)
    {
        return view('customers.show', compact('customer'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Customer $customer)
    {
        return view('customers.edit', compact('customer'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'required|string|max:20',
            'address' => 'nullable|string',
            'type' => 'required|max:50',
            'is_active' => 'boolean',
        ]);
        if(!($data['address'] ?? null)) {
            $data['address'] = '';
        }
        $customer->update($request->all());

        return redirect()->route('customers.index')
            ->with('success', 'Customer updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Customer $customer)
    {
        $customer->delete();

        return redirect()->route('customers.index')
            ->with('success', 'Customer deleted successfully.');
    }
}
