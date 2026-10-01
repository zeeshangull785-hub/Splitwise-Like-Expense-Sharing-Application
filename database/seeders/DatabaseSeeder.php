<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Token;
use App\Models\Group;
use App\Models\Expense;
use App\Models\Settlement;
use App\Services\ExpenseService;
use App\Services\BalanceService;
use App\Services\SettlementService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with 10+ users, 3+ groups, 10+ expenses (equal, exact, percentage), and settlements.
     */
    public function run(): void
    {
        $this->command->info('Cleaning existing collections...');
        User::truncate();
        Token::truncate();
        Group::truncate();
        Expense::truncate();
        Settlement::truncate();

        $this->command->info('Seeding 12 Users with Tokens...');
        $userData = [
            ['name' => 'Ali Khan', 'email' => 'ali@example.com'],
            ['name' => 'Ahmed Raza', 'email' => 'ahmed@example.com'],
            ['name' => 'Usman Tariq', 'email' => 'usman@example.com'],
            ['name' => 'Bilal Hassan', 'email' => 'bilal@example.com'],
            ['name' => 'Sara Fatima', 'email' => 'sara@example.com'],
            ['name' => 'Zainab Noor', 'email' => 'zainab@example.com'],
            ['name' => 'Hamza Malik', 'email' => 'hamza@example.com'],
            ['name' => 'Omar Farooq', 'email' => 'omar@example.com'],
            ['name' => 'Ayesha Siddiqua', 'email' => 'ayesha@example.com'],
            ['name' => 'Mustafa Kamal', 'email' => 'mustafa@example.com'],
            ['name' => 'Fatima Zahra', 'email' => 'fatima@example.com'],
            ['name' => 'Tariq Mehmood', 'email' => 'tariq@example.com'],
        ];

        $users = [];
        foreach ($userData as $u) {
            $user = User::create([
                'name' => $u['name'],
                'email' => $u['email'],
                'password' => Hash::make('password123'),
            ]);
            $users[] = $user;

            Token::create([
                'user_id' => (string)$user->_id,
                'api_token' => bin2hex(random_bytes(32)),
            ]);
        }

        $this->command->info('Seeding 4 Groups...');
        // Group 1: Dubai Trip (Ali, Ahmed, Usman, Bilal, Sara)
        $g1Members = [
            (string)$users[0]->_id,
            (string)$users[1]->_id,
            (string)$users[2]->_id,
            (string)$users[3]->_id,
            (string)$users[4]->_id,
        ];
        $group1 = Group::create([
            'name' => 'Dubai Trip',
            'description' => 'Vacation and hotel expenses for Dubai holiday',
            'owner_id' => (string)$users[0]->_id,
            'member_ids' => $g1Members,
        ]);

        // Group 2: Apartment Expenses (Ahmed, Usman, Hamza, Omar)
        $g2Members = [
            (string)$users[1]->_id,
            (string)$users[2]->_id,
            (string)$users[6]->_id,
            (string)$users[7]->_id,
        ];
        $group2 = Group::create([
            'name' => 'Apartment Expenses',
            'description' => 'Monthly rent, utilities, and grocery supplies for Flat 402',
            'owner_id' => (string)$users[1]->_id,
            'member_ids' => $g2Members,
        ]);

        // Group 3: Office Lunch (Zainab, Hamza, Ayesha, Mustafa, Fatima)
        $g3Members = [
            (string)$users[5]->_id,
            (string)$users[6]->_id,
            (string)$users[8]->_id,
            (string)$users[9]->_id,
            (string)$users[10]->_id,
        ];
        $group3 = Group::create([
            'name' => 'Office Lunch',
            'description' => 'Daily team lunches and coffee runs',
            'owner_id' => (string)$users[5]->_id,
            'member_ids' => $g3Members,
        ]);

        // Group 4: Weekend Getaway (Bilal, Tariq, Ali)
        $g4Members = [
            (string)$users[3]->_id,
            (string)$users[11]->_id,
            (string)$users[0]->_id,
        ];
        $group4 = Group::create([
            'name' => 'Weekend Getaway',
            'description' => 'Cabin rental and barbeque trip',
            'owner_id' => (string)$users[3]->_id,
            'member_ids' => $g4Members,
        ]);

        $this->command->info('Seeding 12+ Expenses with Equal, Exact, and Percentage Splits...');
        $expenseService = app(ExpenseService::class);

        // Expense 1: Dubai Flight Tickets (Equal split between 4 members)
        $expenseService->createExpense($group1, $users[0], [
            'description' => 'Flight Tickets Booking',
            'amount' => 12000,
            'paid_by' => (string)$users[0]->_id,
            'split_type' => 'equal',
            'category' => 'Travel',
            'participants' => [
                ['user_id' => (string)$users[0]->_id],
                ['user_id' => (string)$users[1]->_id],
                ['user_id' => (string)$users[2]->_id],
                ['user_id' => (string)$users[3]->_id],
            ],
        ]);

        // Expense 2: Luxury Dinner at Marina (Exact split)
        $expenseService->createExpense($group1, $users[1], [
            'description' => 'Marina Seafood Dinner',
            'amount' => 10000,
            'paid_by' => (string)$users[1]->_id,
            'split_type' => 'exact',
            'category' => 'Food',
            'participants' => [
                ['user_id' => (string)$users[0]->_id, 'amount' => 4000],
                ['user_id' => (string)$users[1]->_id, 'amount' => 3000],
                ['user_id' => (string)$users[2]->_id, 'amount' => 2000],
                ['user_id' => (string)$users[3]->_id, 'amount' => 1000],
            ],
        ]);

        // Expense 3: Desert Safari Tour (Percentage split)
        $expenseService->createExpense($group1, $users[2], [
            'description' => 'Desert Safari & Quad Biking',
            'amount' => 8000,
            'paid_by' => (string)$users[2]->_id,
            'split_type' => 'percentage',
            'category' => 'Entertainment',
            'participants' => [
                ['user_id' => (string)$users[0]->_id, 'percentage' => 40],
                ['user_id' => (string)$users[1]->_id, 'percentage' => 30],
                ['user_id' => (string)$users[2]->_id, 'percentage' => 20],
                ['user_id' => (string)$users[3]->_id, 'percentage' => 10],
            ],
        ]);

        // Expense 4: Souvenir Shopping (Equal)
        $expenseService->createExpense($group1, $users[0], [
            'description' => 'Dubai Mall Souvenirs',
            'amount' => 5000,
            'paid_by' => (string)$users[0]->_id,
            'split_type' => 'equal',
            'category' => 'Shopping',
            'participants' => [
                ['user_id' => (string)$users[0]->_id],
                ['user_id' => (string)$users[4]->_id],
            ],
        ]);

        // Group 2: Apartment Expenses
        // Expense 5: Monthly Apartment Rent (Equal)
        $expenseService->createExpense($group2, $users[1], [
            'description' => 'Apartment Monthly Rent',
            'amount' => 40000,
            'paid_by' => (string)$users[1]->_id,
            'split_type' => 'equal',
            'category' => 'Rent',
            'participants' => [
                ['user_id' => (string)$users[1]->_id],
                ['user_id' => (string)$users[2]->_id],
                ['user_id' => (string)$users[6]->_id],
                ['user_id' => (string)$users[7]->_id],
            ],
        ]);

        // Expense 6: Electricity & High-speed Fiber Bill (Equal)
        $expenseService->createExpense($group2, $users[2], [
            'description' => 'Electricity & High-speed Internet',
            'amount' => 6000,
            'paid_by' => (string)$users[2]->_id,
            'split_type' => 'equal',
            'category' => 'Utilities',
            'participants' => [
                ['user_id' => (string)$users[1]->_id],
                ['user_id' => (string)$users[2]->_id],
                ['user_id' => (string)$users[6]->_id],
                ['user_id' => (string)$users[7]->_id],
            ],
        ]);

        // Expense 7: Grocery Haul (Exact split)
        $expenseService->createExpense($group2, $users[6], [
            'description' => 'Hypermarket Monthly Groceries',
            'amount' => 15000,
            'paid_by' => (string)$users[6]->_id,
            'split_type' => 'exact',
            'category' => 'Food',
            'participants' => [
                ['user_id' => (string)$users[1]->_id, 'amount' => 3000],
                ['user_id' => (string)$users[2]->_id, 'amount' => 4000],
                ['user_id' => (string)$users[6]->_id, 'amount' => 5000],
                ['user_id' => (string)$users[7]->_id, 'amount' => 3000],
            ],
        ]);

        // Expense 8: Cleaning Services (Percentage split)
        $expenseService->createExpense($group2, $users[7], [
            'description' => 'Apartment Deep Cleaning Service',
            'amount' => 4000,
            'paid_by' => (string)$users[7]->_id,
            'split_type' => 'percentage',
            'category' => 'Utilities',
            'participants' => [
                ['user_id' => (string)$users[1]->_id, 'percentage' => 25],
                ['user_id' => (string)$users[2]->_id, 'percentage' => 25],
                ['user_id' => (string)$users[6]->_id, 'percentage' => 25],
                ['user_id' => (string)$users[7]->_id, 'percentage' => 25],
            ],
        ]);

        // Group 3: Office Lunch
        // Expense 9: Pizza Friday (Equal split)
        $expenseService->createExpense($group3, $users[5], [
            'description' => 'Pizza Friday Feast',
            'amount' => 7500,
            'paid_by' => (string)$users[5]->_id,
            'split_type' => 'equal',
            'category' => 'Food',
            'participants' => [
                ['user_id' => (string)$users[5]->_id],
                ['user_id' => (string)$users[6]->_id],
                ['user_id' => (string)$users[8]->_id],
                ['user_id' => (string)$users[9]->_id],
                ['user_id' => (string)$users[10]->_id],
            ],
        ]);

        // Expense 10: Specialty Espresso & Pastries (Exact split)
        $expenseService->createExpense($group3, $users[8], [
            'description' => 'Afternoon Specialty Coffee',
            'amount' => 2500,
            'paid_by' => (string)$users[8]->_id,
            'split_type' => 'exact',
            'category' => 'Food',
            'participants' => [
                ['user_id' => (string)$users[5]->_id, 'amount' => 500],
                ['user_id' => (string)$users[8]->_id, 'amount' => 1000],
                ['user_id' => (string)$users[9]->_id, 'amount' => 1000],
            ],
        ]);

        // Group 4: Weekend Getaway
        // Expense 11: Cabin Reservation (Equal split)
        $expenseService->createExpense($group4, $users[3], [
            'description' => 'Mountain Cabin Stay',
            'amount' => 18000,
            'paid_by' => (string)$users[3]->_id,
            'split_type' => 'equal',
            'category' => 'Travel',
            'participants' => [
                ['user_id' => (string)$users[3]->_id],
                ['user_id' => (string)$users[11]->_id],
                ['user_id' => (string)$users[0]->_id],
            ],
        ]);

        // Expense 12: Barbeque Grilling Supplies (Percentage split)
        $expenseService->createExpense($group4, $users[11], [
            'description' => 'BBQ Meat and Charcoal',
            'amount' => 6000,
            'paid_by' => (string)$users[11]->_id,
            'split_type' => 'percentage',
            'category' => 'Food',
            'participants' => [
                ['user_id' => (string)$users[3]->_id, 'percentage' => 30],
                ['user_id' => (string)$users[11]->_id, 'percentage' => 40],
                ['user_id' => (string)$users[0]->_id, 'percentage' => 30],
            ],
        ]);

        $this->command->info('Seeding Settlements...');
        $settlementService = app(SettlementService::class);

        // Bilal settles part of his debt in Dubai Trip to Ali
        $settlementService->createSettlement($group1, $users[3], [
            'paid_to' => (string)$users[0]->_id,
            'amount' => 1000,
            'note' => 'Partial cash settlement for Dubai flights',
        ]);

        // Sara settles part of her debt in Dubai Trip to Ahmed
        $settlementService->createSettlement($group1, $users[4], [
            'paid_to' => (string)$users[1]->_id,
            'amount' => 500,
            'note' => 'Direct transfer settlement for dinner',
        ]);

        $this->command->info('✓ Seeding complete! Database is populated with realistic data.');
    }
}
