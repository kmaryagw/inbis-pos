<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\User;
use App\Services\VoidService;
use App\Support\ReportFilters;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    /** TR-16: riwayat transaksi dengan filter nomor, tanggal, kasir, metode, status. */
    public function index(Request $request)
    {
        $user = $request->user();
        $filters = ReportFilters::fromRequest($request, $user);
        $status = in_array($request->status, [Transaction::PAID, Transaction::VOID], true) ? $request->status : null;

        $transactions = $filters->apply(Transaction::query())
            ->with(['event:id,name,code', 'cashier:id,name'])
            ->when($status, fn ($q, $s) => $q->where('status', $s))
            ->latest('transacted_at')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('transactions.index', [
            'transactions' => $transactions,
            'filters' => $filters,
            'status' => $status,
            'events' => $user->visibleEvents()->orderByDesc('start_date')->get(['id', 'name', 'status']),
            'cashiers' => $user->isCashier() ? collect() : User::whereIn('role', [User::ROLE_CASHIER, User::ROLE_ADMIN])->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(Request $request, Transaction $transaction)
    {
        $transaction->load(['event', 'cashier', 'details', 'voidedBy']);
        abort_unless($request->user()->canViewEvent($transaction->event), 403);

        return view('transactions.show', compact('transaction'));
    }

    /** TR-17 / BR-11: pembatalan oleh administrator dengan alasan. */
    public function void(Request $request, Transaction $transaction, VoidService $voids)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:200']], [], ['reason' => 'alasan pembatalan']);

        $voids->void($transaction, $request->user(), $data['reason']);

        return redirect()->route('transactions.show', $transaction)->with('success', 'Transaksi dibatalkan dan stok dikembalikan.');
    }
}
