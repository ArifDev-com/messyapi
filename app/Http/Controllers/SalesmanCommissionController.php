<?php

namespace App\Http\Controllers;

use App\Models\SalesmanCommission;
use App\Models\User;
use App\Models\Bill;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesmanCommissionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = SalesmanCommission::with(['user', 'bill']);

            // Apply filters
            if ($request->filled('salesman_id')) {
                $query->where('user_id', $request->salesman_id);
            }

            if ($request->filled('bill_number')) {
                $query->whereHas('bill', function($q) use ($request) {
                    $q->where('bill_number', 'like', "%{$request->bill_number}%");
                });
            }

            if ($request->filled('from_date')) {
                $query->whereDate('created_at', '>=', $request->from_date);
            }

            if ($request->filled('to_date')) {
                $query->whereDate('created_at', '<=', $request->to_date);
            }

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('user_name', function ($commission) {
                    return $commission->user->name;
                })
                ->addColumn('bill_number', function ($commission) {
                    return $commission->bill->bill_number;
                })
                ->addColumn('commission_amount', function ($commission) {
                    return $commission->formatted_commission_amount;
                })
                ->addColumn('commission_type_badge', function ($commission) {
                    return '<span class="badge badge-' . ($commission->commission_type === 'monthly' ? 'info' : 'success') . '">' . ucfirst($commission->commission_type) . '</span>';
                })
                ->addColumn('applied_date', function ($commission) {
                    return $commission->applied_date->format('M d, Y');
                })
                ->addColumn('actions', function ($commission) {
                    return view('actions.salesman-commission-actions', compact('commission'))->render();
                })
                ->filterColumn('user_name', function($query, $keyword) {
                    $query->whereHas('user', function($q) use ($keyword) {
                        $q->where('name', 'like', "%{$keyword}%");
                    });
                })
                ->filterColumn('bill_number', function($query, $keyword) {
                    $query->whereHas('bill', function($q) use ($keyword) {
                        $q->where('bill_number', 'like', "%{$keyword}%");
                    });
                })
                ->rawColumns(['commission_type_badge', 'actions'])
                ->make(true);
        }

        $salesmen = User::where('role', 'salesman')->where('is_active', true)->get();
        $bills = Bill::get(['bill_number', 'id']);

        return view('salesman-commissions.index', compact('salesmen', 'bills'));
    }

    /**
     * Display the specified resource.
     */
    public function show(SalesmanCommission $salesmanCommission)
    {
        $salesmanCommission->load(['user', 'bill']);

        return view('salesman-commissions.show', compact('salesmanCommission'));
    }

    /**
     * Export salesman commissions to CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        $filename = 'salesman-commissions-' . now()->format('Y-m-d-H-i-s') . '.csv';

        return response()->stream(function () use ($request) {
            $handle = fopen('php://output', 'w');

            // Write CSV headers
            fputcsv($handle, [
                'ID',
                'Salesman Name',
                'Bill Number',
                'Commission Amount',
                'Commission Type',
                'Applied Date',
                'Notes',
                'Created At'
            ]);

            // Build query with filters
            $query = SalesmanCommission::with(['user', 'bill']);

            if ($request->filled('salesman_id')) {
                $query->where('user_id', $request->salesman_id);
            }

            if ($request->filled('bill_number')) {
                $query->whereHas('bill', function($q) use ($request) {
                    $q->where('bill_number', 'like', "%{$request->bill_number}%");
                });
            }

            if ($request->filled('from_date')) {
                $query->whereDate('created_at', '>=', $request->from_date);
            }

            if ($request->filled('to_date')) {
                $query->whereDate('created_at', '<=', $request->to_date);
            }

            // Get and write data
            $query->chunk(1000, function ($commissions) use ($handle) {
                foreach ($commissions as $commission) {
                    fputcsv($handle, [
                        $commission->id,
                        $commission->user->name,
                        $commission->bill->bill_number,
                        $commission->commission_amount,
                        ucfirst($commission->commission_type),
                        $commission->applied_date->format('Y-m-d'),
                        $commission->notes,
                        $commission->created_at->format('Y-m-d H:i:s')
                    ]);
                }
            });

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
