<?php

namespace Database\Seeders;

use App\Models\Core\LedgerAccount;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class LedgerAccountsSeeder extends Seeder
{
    /**
     * @var array<int, array<string, mixed>>
     */
    protected array $accounts = [
        [
            'code' => '1000',
            'name' => 'Assets',
            'type' => 'asset',
            'children' => [
                ['code' => '1100', 'name' => 'Cash', 'type' => 'asset'],
                ['code' => '1200', 'name' => 'Bank', 'type' => 'asset'],
                ['code' => '1300', 'name' => 'Inventory', 'type' => 'asset', 'note' => 'Seeds, Fertilizer, Feed'],
                ['code' => '1400', 'name' => 'Livestock', 'type' => 'asset'],
                ['code' => '1500', 'name' => 'Equipment', 'type' => 'asset'],
            ],
        ],
        [
            'code' => '2000',
            'name' => 'Liabilities',
            'type' => 'liability',
            'children' => [
                ['code' => '2100', 'name' => 'Accounts Payable', 'type' => 'liability'],
                ['code' => '2200', 'name' => 'Loans', 'type' => 'liability'],
            ],
        ],
        [
            'code' => '3000',
            'name' => 'Equity',
            'type' => 'equity',
            'children' => [
                ['code' => '3100', 'name' => "Owner's Capital", 'type' => 'equity'],
            ],
        ],
        [
            'code' => '4000',
            'name' => 'Revenue',
            'type' => 'revenue',
            'children' => [
                ['code' => '4100', 'name' => 'Crop Sales', 'type' => 'revenue'],
                ['code' => '4200', 'name' => 'Livestock Sales', 'type' => 'revenue'],
                ['code' => '4300', 'name' => 'Other Income', 'type' => 'revenue'],
            ],
        ],
        [
            'code' => '5000',
            'name' => 'Expenses',
            'type' => 'expense',
            'children' => [
                ['code' => '5100', 'name' => 'Seeds & Seedlings', 'type' => 'expense'],
                ['code' => '5200', 'name' => 'Fertilizer & Chemicals', 'type' => 'expense'],
                ['code' => '5300', 'name' => 'Labor', 'type' => 'expense'],
                ['code' => '5400', 'name' => 'Equipment & Maintenance', 'type' => 'expense'],
                ['code' => '5500', 'name' => 'Veterinary', 'type' => 'expense'],
                ['code' => '5600', 'name' => 'Feed', 'type' => 'expense'],
                ['code' => '5700', 'name' => 'Transport', 'type' => 'expense'],
                ['code' => '5800', 'name' => 'Utilities', 'type' => 'expense', 'note' => 'Water, Electricity'],
                ['code' => '5900', 'name' => 'Land Rent / Lease', 'type' => 'expense'],
            ],
        ],
    ];

    public function run(): void
    {
        $this->command?->info('📒 Seeding ledger chart of accounts...');

        foreach ($this->accounts as $account) {
            $parent = $this->upsertAccount($account);

            foreach ($account['children'] ?? [] as $child) {
                $this->upsertAccount($child, $parent->id);
            }
        }

        $this->command?->info('✅ Ledger chart of accounts seeded successfully.');
    }

    /**
     * @param  array<string, mixed>  $account
     */
    protected function upsertAccount(array $account, ?int $parentId = null): LedgerAccount
    {
        $descriptionParts = ["Code: {$account['code']}"];

        if (!empty($account['note'])) {
            $descriptionParts[] = (string) $account['note'];
        }

        return LedgerAccount::updateOrCreate(
            [
                'name' => $account['name'],
                'parent_id' => $parentId,
                'farmer_id' => null,
            ],
            [
                'uuid' => (string) Str::orderedUuid(),
                'slug' => Str::slug($account['name']),
                'type' => $account['type'],
                'description' => implode(' | ', $descriptionParts),
                'is_system' => true,
                'status' => 1,
                'user_id' => null,
            ]
        );
    }
}

