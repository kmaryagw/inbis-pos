<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Services\EscPosReceipt;
use Illuminate\Http\Request;

class ReceiptController extends Controller
{
    public function show(Request $request, Transaction $transaction, EscPosReceipt $escpos)
    {
        $transaction->load(['event', 'cashier', 'details']);

        abort_unless($request->user()->canViewEvent($transaction->event), 403);

        return view('receipts.show', [
            'transaction' => $transaction,
            'escpos' => $escpos->base64($transaction),
        ]);
    }
}
