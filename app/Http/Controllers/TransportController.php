<?php

namespace App\Http\Controllers;

use App\Models\Transport;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;

class TransportController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = Transport::query();

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('created_date', function ($transport) {
                    return $transport->created_at->format('M d, Y');
                })
                ->addColumn('actions', function ($transport) {
                    return view('actions.transport-actions', compact('transport'))->render();
                })
                ->rawColumns(['actions'])
                ->make(true);
        }

        return view('transports.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('transports.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $rules = [
            'name' => 'required|string|max:255',
        ];

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['errors' => $validator->errors()], 422);
            } else {
                return redirect()->back()->withErrors($validator)->withInput();
            }
        }

        $transport = Transport::create($request->all());

        if ($request->ajax()) {
            return response()->json($transport);
        } else {
            return redirect()->route('transports.index')
                ->with('success', 'Transport created successfully.');
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Transport $transport)
    {
        return view('transports.show', compact('transport'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Transport $transport)
    {
        $this->authorize('update', $transport);

        return view('transports.edit', compact('transport'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Transport $transport)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $transport->update($request->all());

        return redirect()->route('transports.index')
            ->with('success', 'Transport updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Transport $transport)
    {
        $transport->delete();

        return redirect()->route('transports.index')
            ->with('success', 'Transport deleted successfully.');
    }
}
