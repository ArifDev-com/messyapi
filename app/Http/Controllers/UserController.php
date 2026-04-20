<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Yajra\DataTables\DataTables;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = User::with(['manager'])->type();

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('name_with_designation', function ($user) {
                    $html = '<a href="'.route('users.show', $user).'"><strong>' . $user->name . '</strong>';
                    // if ($user->designation) {
                    //     $html .= '<br><small class="text-muted">' . $user->designation . '</small>';
                    // }
                    return $html . '</a>';
                })
                ->addColumn('role_badge', function ($user) {
                    return '<span class="badge badge-info">' . ucf($user->role) . '</span>';
                })
                ->addColumn('status_badge', function ($user) {
                    if ($user->is_active) {
                        return '<span class="badge badge-success">Active</span>';
                    } else {
                        return '<span class="badge badge-secondary">Inactive</span>';
                    }
                })
                ->addColumn('manager_name', function ($user) {
                    return $user->manager ? $user->manager->name : '-';
                })
                ->addColumn('actions', function ($user) {
                    return view('actions.user-actions', compact('user'))->render();
                })
                ->filterColumn('name', function($query, $keyword) {
                    $query->where('name', 'like', "%{$keyword}%")
                          ->orWhere('email', 'like', "%{$keyword}%")
                          ->orWhere('phone', 'like', "%{$keyword}%")
                          ->orWhere('designation', 'like', "%{$keyword}%");
                })
                ->rawColumns(['name_with_designation', 'role_badge', 'status_badge', 'actions'])
                ->make(true);
        }

        $roles = User::$roles;
        return view('users.index', compact('roles'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $roles = User::$roles;
        return view('users.create', compact('roles'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|string|email|max:255|unique:users',
            'password' => 'nullable|string|min:8|confirmed',
            'phone' => 'required|string|max:20',
            'designation' => 'nullable|string|max:255',
            'role' => 'required|max:50',
            'manager_id' => 'nullable|exists:users,id',
            'dealers' => 'nullable|array',
            'dealers.*' => 'exists:dealers,id',
            'is_active' => 'boolean',
            'monthly_sell_target' => 'nullable|numeric|min:0',
            'monthly_sell_commission_rate' => 'nullable|numeric|min:0|max:100',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password ?: rand(999, 99999999)),
            'phone' => $request->phone,
            'designation' => $request->designation,
            'role' => $request->ajax() ? 'salesman' : $request->role,
            'manager_id' => $request->manager_id,
            'is_active' => $request->is_active ?? true,
            'user_type' => 'user',
            'monthly_sell_target' => $request->monthly_sell_target,
            'monthly_sell_commission_rate' => $request->monthly_sell_commission_rate,
        ]);

        // Attach dealers if role is salesman
        if ($request->role === 'salesman' && $request->has('dealers')) {
            $user->dealers()->attach($request->dealers);
        }
        if($request->ajax()) return $user;

        return redirect()->route('users.index')
            ->with('success', 'User created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        $user->load(['manager', 'subordinates', 'dealers', 'salesmanCommissions']);

        return view('users.show', compact(
            'user',
        ));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user)
    {
        $roles = User::$roles;
        $managers = User::type()->where('id', '!=', $user->id)->where('is_active', true)->get();

        return view('users.edit', compact('user', 'roles', 'managers'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|string|email|max:255|unique:users,email,' . $user->id,
            'phone' => 'required|string|max:20',
            'designation' => 'nullable|string|max:255',
            'role' => 'required|max:50',
            'manager_id' => 'nullable|exists:users,id',
            'dealers' => 'nullable|array',
            'dealers.*' => 'exists:dealers,id',
            'is_active' => 'boolean',
            'password' => 'nullable|string|min:8|confirmed',
            'monthly_sell_target' => 'nullable|numeric|min:0',
            'monthly_sell_commission_rate' => 'nullable|numeric|min:0|max:100',
        ]);

        $updateData = [
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'designation' => $request->designation,
            'role' => $request->role,
            'manager_id' => $request->manager_id,
            'is_active' => $request->is_active ?? true,
            'monthly_sell_target' => $request->monthly_sell_target,
            'monthly_sell_commission_rate' => $request->monthly_sell_commission_rate,
        ];

        if ($request->filled('password')) {
            $updateData['password'] = Hash::make($request->password);
        }

        $user->update($updateData);

        // Sync dealers if role is salesman
        if ($request->role === 'salesman') {
            $user->dealers()->sync($request->dealers ?? []);
        } else {
            // Remove all dealers if role changed from salesman
            $user->dealers()->detach();
        }

        return redirect()->route('users.index')
            ->with('success', 'User updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        // Prevent deleting self
        if ($user->id === user()->id) {
            return redirect()->route('users.index')
                ->with('error', 'You cannot delete your own account.');
        }

        // Check if user has subordinates
        if ($user->subordinates()->count() > 0) {
            return redirect()->route('users.index')
                ->with('error', 'Cannot delete user. This user has subordinates.');
        }

        // Check if user has dealer bills
        if ($user->dealerBills()->count() > 0) {
            return redirect()->route('users.index')
                ->with('error', 'Cannot delete user. This user has associated dealer bills.');
        }

        $user->delete();

        return redirect()->route('users.index')
            ->with('success', 'User deleted successfully.');
    }
}
