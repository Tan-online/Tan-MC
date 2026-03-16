<?php

namespace App\Http\Controllers;

use App\Models\DispatchEntry;
use Illuminate\Http\Request;

class DispatchEntryController extends Controller
{
    public function index(Request $request)
    {
        $status = (string) $request->string('status');

        $dispatchEntriesQuery = DispatchEntry::query()
            ->with(['serviceOrder.contract.client:id,name', 'serviceOrder.location:id,name,city', 'dispatchedBy:id,name'])
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->latest('updated_at');

        $this->accessControl()->scopeDispatchEntries($dispatchEntriesQuery, $request->user());

        $dispatchEntries = $dispatchEntriesQuery
            ->paginate(10)
            ->withQueryString();

        return view('operations.dispatch-entry.index', compact('dispatchEntries', 'status'));
    }

    public function dispatch(Request $request, DispatchEntry $dispatchEntry)
    {
        $this->accessControl()->scopeDispatchEntries(DispatchEntry::query()->whereKey($dispatchEntry->id), $request->user())->firstOrFail();

        $dispatchEntry->update([
            'status' => 'dispatched',
            'dispatched_by_user_id' => $request->user()->id,
            'dispatched_at' => now(),
            'remarks' => $request->input('remarks'),
        ]);

        $this->logActivity(
            'dispatch_entry',
            'dispatch',
            "Dispatched service order {$dispatchEntry->serviceOrder?->order_no}.",
            $dispatchEntry,
            $request->user()
        );

        return redirect()
            ->route('dispatch-entry.index')
            ->with('status', 'Dispatch entry updated successfully.');
    }
}
