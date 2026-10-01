<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CreateMongoIndexesCommand extends Command
{
    protected $signature = 'mongo:create-indexes';
    protected $description = 'Create optimized MongoDB indexes for users, tokens, groups, expenses, and settlements';

    public function handle(): int
    {
        $this->info('Creating MongoDB indexes...');
        $db = DB::connection('mongodb')->getMongoDB();

        // 1. Users collection
        $usersCollection = $db->selectCollection('users');
        $usersCollection->createIndex(['email' => 1], ['unique' => true, 'name' => 'idx_users_email_unique']);
        $this->info('✓ Created unique index on users.email');

        // 2. Tokens collection
        $tokensCollection = $db->selectCollection('tokens');
        $tokensCollection->createIndex(['api_token' => 1], ['unique' => true, 'name' => 'idx_tokens_api_token_unique']);
        $tokensCollection->createIndex(['user_id' => 1], ['name' => 'idx_tokens_user_id']);
        $this->info('✓ Created unique index on tokens.api_token & tokens.user_id');

        // 3. Groups collection
        $groupsCollection = $db->selectCollection('groups');
        $groupsCollection->createIndex(['owner_id' => 1], ['name' => 'idx_groups_owner_id']);
        $groupsCollection->createIndex(['member_ids' => 1], ['name' => 'idx_groups_member_ids']);
        $this->info('✓ Created indexes on groups.owner_id and groups.member_ids');

        // 4. Expenses collection
        $expensesCollection = $db->selectCollection('expenses');
        $expensesCollection->createIndex(['group_id' => 1, 'created_at' => -1], ['name' => 'idx_expenses_group_created']);
        $expensesCollection->createIndex(['paid_by' => 1], ['name' => 'idx_expenses_paid_by']);
        $expensesCollection->createIndex(['group_id' => 1, 'split_type' => 1], ['name' => 'idx_expenses_group_split']);
        $expensesCollection->createIndex(['group_id' => 1, 'category' => 1], ['name' => 'idx_expenses_group_category']);
        $this->info('✓ Created compound and filtering indexes on expenses');

        // 5. Settlements collection
        $settlementsCollection = $db->selectCollection('settlements');
        $settlementsCollection->createIndex(['group_id' => 1, 'created_at' => -1], ['name' => 'idx_settlements_group_created']);
        $settlementsCollection->createIndex(['paid_by' => 1], ['name' => 'idx_settlements_paid_by']);
        $settlementsCollection->createIndex(['paid_to' => 1], ['name' => 'idx_settlements_paid_to']);
        $this->info('✓ Created compound indexes on settlements');

        $this->info('All MongoDB indexes created successfully!');
        return Command::SUCCESS;
    }
}
