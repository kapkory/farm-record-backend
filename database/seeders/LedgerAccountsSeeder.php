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
                ['code' => '1100', 'name' => 'Cash', 'type' => 'asset', 'note' => 'Money you keep as cash on the farm'],
                ['code' => '1150', 'name' => 'Mobile Money', 'type' => 'asset', 'note' => 'M-Pesa and other mobile wallets'],
                ['code' => '1200', 'name' => 'Bank', 'type' => 'asset', 'note' => 'Money in your bank or SACCO account'],
                ['code' => '1250', 'name' => 'Accounts Receivable', 'type' => 'asset', 'note' => 'Money buyers still owe you for sales'],
                ['code' => '1300', 'name' => 'Inventory', 'type' => 'asset', 'note' => 'Seeds, fertilizer and feed you have in store'],
                ['code' => '1350', 'name' => 'Produce in Store', 'type' => 'asset', 'note' => 'Harvested crops not yet sold'],
                ['code' => '1400', 'name' => 'Livestock', 'type' => 'asset', 'note' => 'Value of your animals'],
                ['code' => '1500', 'name' => 'Equipment & Tools', 'type' => 'asset', 'note' => 'Machines, tools and other durable items'],
            ],
        ],
        [
            'code' => '2000',
            'name' => 'Liabilities',
            'type' => 'liability',
            'children' => [
                ['code' => '2100', 'name' => 'Suppliers to Pay', 'type' => 'liability', 'note' => 'Money you owe suppliers (agrovet, dealers)'],
                ['code' => '2200', 'name' => 'Loans', 'type' => 'liability', 'note' => 'Money borrowed from bank, SACCO or others'],
            ],
        ],
        [
            'code' => '3000',
            'name' => 'Equity',
            'type' => 'equity',
            'children' => [
                ['code' => '3100', 'name' => "Owner's Capital", 'type' => 'equity', 'note' => 'Your own money put into the farm'],
                ['code' => '3200', 'name' => 'Drawings', 'type' => 'equity', 'note' => 'Money or produce you take for personal use'],
            ],
        ],
        [
            'code' => '4000',
            'name' => 'Income',
            'type' => 'revenue',
            'children' => [
                ['code' => '4100', 'name' => 'Crop Sales', 'type' => 'revenue', 'note' => 'Money from selling crops and produce'],
                ['code' => '4200', 'name' => 'Livestock Sales', 'type' => 'revenue', 'note' => 'Money from selling animals'],
                ['code' => '4250', 'name' => 'Milk, Eggs & Honey Sales', 'type' => 'revenue', 'note' => 'Money from animal products'],
                ['code' => '4300', 'name' => 'Other Income', 'type' => 'revenue', 'note' => 'Grants, subsidies and any other income'],
            ],
        ],
        [
            'code' => '5000',
            'name' => 'Expenses',
            'type' => 'expense',
            'children' => [
                ['code' => '5100', 'name' => 'Seeds & Seedlings', 'type' => 'expense', 'note' => 'What you spend on seeds and seedlings'],
                ['code' => '5200', 'name' => 'Fertilizer & Chemicals', 'type' => 'expense', 'note' => 'Fertilizer, pesticides and sprays'],
                ['code' => '5300', 'name' => 'Labour', 'type' => 'expense', 'note' => 'Wages paid to farm workers'],
                ['code' => '5400', 'name' => 'Equipment & Maintenance', 'type' => 'expense', 'note' => 'Repairs, servicing and fuel'],
                ['code' => '5500', 'name' => 'Veterinary & Medicine', 'type' => 'expense', 'note' => 'Vet visits, drugs and vaccines'],
                ['code' => '5600', 'name' => 'Animal Feed', 'type' => 'expense', 'note' => 'Feed and supplements for animals'],
                ['code' => '5700', 'name' => 'Transport', 'type' => 'expense', 'note' => 'Moving inputs and produce'],
                ['code' => '5800', 'name' => 'Utilities', 'type' => 'expense', 'note' => 'Water and electricity'],
                ['code' => '5900', 'name' => 'Land Rent / Lease', 'type' => 'expense', 'note' => 'Rent paid for land'],
                ['code' => '5950', 'name' => 'Harvest Expenses', 'type' => 'expense', 'note' => 'Picking, sorting and packing costs'],
                ['code' => '5990', 'name' => 'General Expenses', 'type' => 'expense', 'note' => 'Any other farm costs'],
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

        if (! empty($account['note'])) {
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
