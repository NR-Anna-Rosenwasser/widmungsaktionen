<?php

namespace Database\Seeders;

use App\Enums\AktionStatus;
use App\Enums\TenantRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Local demo data. Prices and book data are placeholders – check before going live.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@widmungen.test',
            'password' => 'password',
        ]);
        $admin->forceFill(['is_super_admin' => true])->save();

        $anna = Tenant::create([
            'name' => 'Anna Rosenwasser',
            'slug' => 'anna-rosenwasser',
            'order_prefix' => 'AR',
            'contact_email' => 'widmungen@example.com',
            'letterhead' => "Nationalrätin Anna Rosenwasser\nc/o Büro für Fortschritt\nBuckhauserstrasse 36\n8048 Zürich",
            'creditor_name' => 'Anna Rosenwasser',
            'creditor_street' => 'Buckhauserstrasse',
            'creditor_building_number' => '36',
            'creditor_postal_code' => '8048',
            'creditor_town' => 'Zürich',
            'creditor_country' => 'CH',
            'iban' => 'CH9300762011623852957',
            'mail_from_name' => 'Anna Rosenwasser',
            'settings' => [
                'storefront' => [
                    'colors' => ['primary' => '#2B3C69', 'accent' => '#D989B2', 'accent_light' => '#EEC6D8'],
                    // Switch to "image" once a hero photo is uploaded in the shop settings.
                    'hero' => ['visual' => 'heart', 'heart_text' => 'Anna Rosenwasser'],
                ],
            ],
        ]);
        $anna->domains()->create(['domain' => 'anna-test.ddev.site']);

        $anna->users()->attach(User::create([
            'name' => 'Anna',
            'email' => 'anna@widmungen.test',
            'password' => 'password',
        ]), ['role' => TenantRole::Widmen->value]);

        $herz = $anna->books()->create([
            'author_name' => 'Anna Rosenwasser',
            'title' => 'Herz',
            'subtitle' => 'Feministische Strategien und queere Hoffnung',
            'price' => 3000,
            'weight_grams' => 350,
            'sort' => 1,
        ]);
        $rosa = $anna->books()->create([
            'author_name' => 'Anna Rosenwasser',
            'title' => 'Rosa Buch',
            'subtitle' => 'Queere Texte von Herzen',
            'price' => 2800,
            'weight_grams' => 400,
            'sort' => 2,
        ]);

        $aktion = $anna->aktionen()->create([
            'name' => 'Herbst-Widmungsaktion 2026',
            'status' => AktionStatus::Open,
            'orders_open_at' => now()->subWeek(),
            'order_deadline' => now()->addWeeks(3)->setTime(23, 59),
            'signing_date' => now()->addWeeks(4)->toDateString(),
            'estimated_shipping_date' => now()->addWeeks(5)->toDateString(),
        ]);
        $aktion->books()->attach([$herz->id => ['sort' => 1], $rosa->id => ['sort' => 2]]);

        $anna->aktionen()->create([
            'name' => 'Winter-Widmungsaktion 2027',
            'status' => AktionStatus::Scheduled,
            'orders_open_at' => now()->addWeeks(3)->setTime(23, 59),
            'order_deadline' => now()->addWeeks(12)->setTime(23, 59),
            'signing_date' => now()->addWeeks(13)->toDateString(),
            'estimated_shipping_date' => now()->addWeeks(14)->toDateString(),
        ])->books()->attach([$herz->id => ['sort' => 1]]);

        // Demo orders for local development only; tests need a predictable database.
        if (app()->environment('local')) {
            $this->call(DemoOrdersSeeder::class);
        }
    }
}
