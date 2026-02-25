<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wallet;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MoneyTrackerTest extends TestCase
{
    use RefreshDatabase;

    // =====================
    // USER TESTS
    // =====================
    public function a_user_can_be_created()
    {
        $response = $this->postJson('/api/users', [
            'name'  => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $response->assertStatus(201);
        $response->assertJsonFragment(['name' => 'John Doe']);

        $this->assertDatabaseHas('users', ['email' => 'john@example.com']);
    }

    public function creating_a_user_requires_name_and_email()
    {
        $response = $this->postJson('/api/users', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'email']);
    }

    public function user_email_must_be_unique()
    {
        // Create first user
        $this->postJson('/api/users', [
            'name'  => 'John Doe',
            'email' => 'john@example.com',
        ]);

        // Try to create another user with same email
        $response = $this->postJson('/api/users', [
            'name'  => 'Jane Doe',
            'email' => 'john@example.com',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function a_user_profile_can_be_viewed()
    {
        $user = User::create([
            'name'  => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $response = $this->getJson('/api/users/' . $user->id);

        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => 'John Doe']);
        $response->assertJsonStructure([
            'id', 'name', 'email', 'total_balance', 'wallets'
        ]);
    }

    public function viewing_a_non_existent_user_returns_404()
    {
        $response = $this->getJson('/api/users/999');

        $response->assertStatus(404);
    }

    // =====================
    // WALLET TESTS
    // =====================

    public function a_wallet_can_be_created_for_a_user()
    {
        $user = User::create([
            'name'  => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $response = $this->postJson('/api/wallets', [
            'user_id' => $user->id,
            'name'    => 'Business Wallet',
        ]);

        $response->assertStatus(201);
        $response->assertJsonFragment(['name' => 'Business Wallet']);
        $this->assertDatabaseHas('wallets', ['name' => 'Business Wallet']);
    }

    public function creating_a_wallet_requires_user_id_and_name()
    {
        $response = $this->postJson('/api/wallets', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['user_id', 'name']);
    }

    public function wallet_user_id_must_exist_in_users_table()
    {
        $response = $this->postJson('/api/wallets', [
            'user_id' => 999,
            'name'    => 'Ghost Wallet',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['user_id']);
    }

    public function a_wallet_can_be_viewed_with_its_balance_and_transactions()
    {
        $user = User::create(['name' => 'John', 'email' => 'john@example.com']);
        $wallet = Wallet::create(['user_id' => $user->id, 'name' => 'My Wallet']);

        // Add income
        Transaction::create([
            'wallet_id'   => $wallet->id,
            'type'        => 'income',
            'amount'      => 500,
            'description' => 'Salary',
        ]);

        // Add expense
        Transaction::create([
            'wallet_id'   => $wallet->id,
            'type'        => 'expense',
            'amount'      => 100,
            'description' => 'Rent',
        ]);

        $response = $this->getJson('/api/wallets/' . $wallet->id);

        $response->assertStatus(200);
        $response->assertJsonFragment(['balance' => 400.0]);
        $response->assertJsonStructure([
            'id', 'name', 'balance', 'transactions'
        ]);
    }

    public function viewing_a_non_existent_wallet_returns_404()
    {
        $response = $this->getJson('/api/wallets/999');

        $response->assertStatus(404);
    }

    // =====================
    // TRANSACTION TESTS
    // =====================
    public function an_income_transaction_can_be_added_to_a_wallet()
    {
        $user   = User::create(['name' => 'John', 'email' => 'john@example.com']);
        $wallet = Wallet::create(['user_id' => $user->id, 'name' => 'My Wallet']);

        $response = $this->postJson('/api/transactions', [
            'wallet_id'   => $wallet->id,
            'type'        => 'income',
            'amount'      => 500,
            'description' => 'Salary',
        ]);

        $response->assertStatus(201);
        $response->assertJsonFragment(['type' => 'income', 'amount' => 500]);
        $this->assertDatabaseHas('transactions', ['type' => 'income', 'amount' => 500]);
    }

    public function an_expense_transaction_can_be_added_to_a_wallet()
    {
        $user   = User::create(['name' => 'John', 'email' => 'john@example.com']);
        $wallet = Wallet::create(['user_id' => $user->id, 'name' => 'My Wallet']);

        $response = $this->postJson('/api/transactions', [
            'wallet_id'   => $wallet->id,
            'type'        => 'expense',
            'amount'      => 200,
            'description' => 'Groceries',
        ]);

        $response->assertStatus(201);
        $response->assertJsonFragment(['type' => 'expense']);
        $this->assertDatabaseHas('transactions', ['type' => 'expense', 'amount' => 200]);
    }

    public function transaction_amount_must_be_positive()
    {
        $user   = User::create(['name' => 'John', 'email' => 'john@example.com']);
        $wallet = Wallet::create(['user_id' => $user->id, 'name' => 'My Wallet']);

        $response = $this->postJson('/api/transactions', [
            'wallet_id' => $wallet->id,
            'type'      => 'income',
            'amount'    => -50,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['amount']);
    }

    public function transaction_type_must_be_income_or_expense()
    {
        $user   = User::create(['name' => 'John', 'email' => 'john@example.com']);
        $wallet = Wallet::create(['user_id' => $user->id, 'name' => 'My Wallet']);

        $response = $this->postJson('/api/transactions', [
            'wallet_id' => $wallet->id,
            'type'      => 'transfer', // invalid type
            'amount'    => 100,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['type']);
    }

    public function balance_is_calculated_correctly()
    {
        $user   = User::create(['name' => 'John', 'email' => 'john@example.com']);
        $wallet = Wallet::create(['user_id' => $user->id, 'name' => 'My Wallet']);

        // Add multiple transactions
        Transaction::create(['wallet_id' => $wallet->id, 'type' => 'income',  'amount' => 1000]);
        Transaction::create(['wallet_id' => $wallet->id, 'type' => 'income',  'amount' => 500]);
        Transaction::create(['wallet_id' => $wallet->id, 'type' => 'expense', 'amount' => 300]);
        Transaction::create(['wallet_id' => $wallet->id, 'type' => 'expense', 'amount' => 200]);

        // Expected: 1000 + 500 - 300 - 200 = 1000
        $response = $this->getJson('/api/wallets/' . $wallet->id);

        $response->assertStatus(200);
        $response->assertJsonFragment(['balance' => 1000.0]);
    }

    public function user_total_balance_is_sum_of_all_wallets()
    {
        $user    = User::create(['name' => 'John', 'email' => 'john@example.com']);
        $wallet1 = Wallet::create(['user_id' => $user->id, 'name' => 'Wallet 1']);
        $wallet2 = Wallet::create(['user_id' => $user->id, 'name' => 'Wallet 2']);

        // Wallet 1 balance = 400
        Transaction::create(['wallet_id' => $wallet1->id, 'type' => 'income',  'amount' => 500]);
        Transaction::create(['wallet_id' => $wallet1->id, 'type' => 'expense', 'amount' => 100]);

        // Wallet 2 balance = 300
        Transaction::create(['wallet_id' => $wallet2->id, 'type' => 'income',  'amount' => 400]);
        Transaction::create(['wallet_id' => $wallet2->id, 'type' => 'expense', 'amount' => 100]);

        // Total should be 400 + 300 = 700
        $response = $this->getJson('/api/users/' . $user->id);

        $response->assertStatus(200);
        $response->assertJsonFragment(['total_balance' => 700.0]);
    }
}