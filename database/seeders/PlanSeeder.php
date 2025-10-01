<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Free',
                'monthly_price' => 0,
                'yearly_price' => 0,
                'perks' => json_encode([
                    'Full access for 7 days',
                    'Ask any AI questions',
                    'Read hydro images (basic interpretation)',
                ]),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Individual',
                'monthly_price' => 19.99,
                'yearly_price' => 99,
                'perks' => json_encode([
                    'All features of Individual plan',
                    'Read & interpret images (basic hydro components)',
                    'Insights on hydropower standards, failures, case studies worldwide',
                    'Personal learning companion',
                ]),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Company',
                'monthly_price' => 49.99,
                'yearly_price' => 299,
                'perks' => json_encode([
                    'All features of Company plan',
                    'Advanced analytics for organizations',
                    'Shared knowledge base across teams',
                    'Unlimited Q&A',
                    'Multi-user management',
                ]),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Hydropower Utilities',
                'monthly_price' => 600,
                'yearly_price' => 5000,
                'perks' => json_encode([
                    'All features of Company plan',
                    'Seamless integration with ERP/Asset Management systems',
                    'Tailored to hydropower asset O&M (Operation & Maintenance)',
                    'Included support under TGM Expert contract',
                ]),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('plans')->insert($plans);
    }
}