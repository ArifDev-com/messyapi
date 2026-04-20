<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Account::class);

        $query = Account::query();

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('account_number', 'like', "%{$search}%");
            });
        }

        $accounts = $query->latest()->paginate(10)->appends($request->all());

        return view('accounts.index', compact('accounts'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create', Account::class);

        return view('accounts.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Account::class);

        $request->validate([
            'name' => 'required|string|max:255',
            'account_number' => 'required|string|max:100|unique:accounts',
            'opening_balance' => 'required|numeric|min:0',
            'balance' => 'required|numeric|min:0',
            'type' => ['required', Rule::in(\App\Models\Payment::$methods)],
            'notes' => 'nullable|string|max:1000',
        ]);

        Account::create($request->all());

        return redirect()->route('accounts.index')
            ->with('success', 'Account created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Account $account)
    {
        $this->authorize('view', $account);

        return view('accounts.show', compact('account'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Account $account)
    {
        $this->authorize('update', $account);

        return view('accounts.edit', compact('account'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Account $account)
    {
        $this->authorize('update', $account);

        $request->validate([
            'name' => 'required|string|max:255',
            'account_number' => 'required|string|max:100|unique:accounts,account_number,' . $account->id,
            'opening_balance' => 'required|numeric|min:0',
            'balance' => 'required|numeric|min:0',
            'type' => ['required', Rule::in(\App\Models\Payment::$methods)],
            'notes' => 'nullable|string|max:1000',
        ]);

        $account->update($request->all());

        return redirect()->route('accounts.index')
            ->with('success', 'Account updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Account $account)
    {
        $this->authorize('delete', $account);

        $account->delete();

        return redirect()->route('accounts.index')
            ->with('success', 'Account deleted successfully.');
    }
}
