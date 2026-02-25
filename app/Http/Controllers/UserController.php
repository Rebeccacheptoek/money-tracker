<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function store(Request $request)
    {
        
        $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
        ]);

        $user = new User();
        $user->name  = $request->name;
        $user->email = $request->email;
        $user->save();

        return response()->json($user, 201);
    }

    public function show($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $wallets = $user->wallets;
        $totalBalance = 0;
        $walletData = [];

        foreach ($wallets as $wallet) {
            $income  = $wallet->transactions->where('type', 'income')->sum('amount');
            $expense = $wallet->transactions->where('type', 'expense')->sum('amount');
            $balance = $income - $expense;

            $totalBalance += $balance;

            $walletData[] = [
                'id'      => $wallet->id,
                'name'    => $wallet->name,
                'balance' => $balance,
            ];
        }

        return response()->json([
            'id'            => $user->id,
            'name'          => $user->name,
            'email'         => $user->email,
            'total_balance' => $totalBalance,
            'wallets'       => $walletData,
        ]);
    }
}