<?php

namespace App\Http\Controllers;

use App\Models\Wallet;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'name'    => 'required|string|max:255',
        ]);

        $wallet = new Wallet();
        $wallet->user_id = $request->user_id;
        $wallet->name    = $request->name;
        $wallet->save();

        return response()->json($wallet, 201);
    }

    public function show($id)
    {
        $wallet = Wallet::find($id);

        if (!$wallet) {
            return response()->json(['message' => 'Wallet not found'], 404);
        }

        $transactions = $wallet->transactions;

        $income  = 0;
        $expense = 0;

        foreach ($transactions as $transaction) {
            if ($transaction->type == 'income') {
                $income += $transaction->amount;
            } else {
                $expense += $transaction->amount;
            }
        }

        $balance = $income - $expense;

        return response()->json([
            'id'           => $wallet->id,
            'name'         => $wallet->name,
            'balance'      => $balance,
            'transactions' => $transactions,
        ]);
    }
}