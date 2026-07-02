<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StoreSeeder extends Seeder
{
    public function run(): void
    {
        // Admin account (full access)
        User::updateOrCreate(
            ['email' => 'admin@saidi-papetrie.dz'],
            [
                'name'      => 'Administrateur',
                'password'  => Hash::make('password'),
                'is_admin'  => true,
                'role'      => 'admin',
                'is_active' => true,
            ]
        );

        // Sample employee (no access to team/settings)
        User::updateOrCreate(
            ['email' => 'employe@saidi-papetrie.dz'],
            [
                'name'      => 'Employé Démo',
                'phone'     => '0555 11 22 33',
                'password'  => Hash::make('password'),
                'is_admin'  => true,
                'role'      => 'staff',
                'is_active' => true,
            ]
        );

        $settings = [
            ['general', 'store_name', 'Saidi Papetrie'],
            ['general', 'tagline_fr', 'Votre papeterie de confiance'],
            ['general', 'tagline_ar', 'مكتبتكم الموثوقة'],
            ['general', 'logo', ''],
            ['general', 'currency', 'DA'],
            ['general', 'announcement_fr', '🚚 Livraison à domicile et stop-desk dans les 58 wilayas — Paiement à la livraison.'],
            ['general', 'announcement_ar', '🚚 التوصيل إلى المنزل والمكتب لكل الولايات — الدفع عند الاستلام.'],
            ['contact', 'phone', '+213 555 00 00 00'],
            ['contact', 'email', 'contact@saidi-papetrie.dz'],
            ['contact', 'address_fr', 'Oran, Algérie'],
            ['contact', 'address_ar', 'وهران، الجزائر'],
            ['contact', 'hours', 'Sam - Jeu : 9h00 - 18h00'],
            ['social', 'facebook', 'https://facebook.com'],
            ['social', 'instagram', 'https://instagram.com'],
            ['social', 'tiktok', ''],
            ['shipping', 'free_shipping_threshold', '0'],
            ['shipping', 'cod_enabled', '1'],
            ['seo', 'meta_title', 'Saidi Papetrie — Fournitures scolaires & bureautiques en Algérie'],
            ['seo', 'meta_description', 'Achetez en ligne vos fournitures scolaires, bureautiques et informatiques. Livraison dans les 58 wilayas, paiement à la livraison.'],
        ];

        foreach ($settings as [$group, $key, $value]) {
            Setting::updateOrCreate(['key' => $key], ['group' => $group, 'value' => $value]);
        }

        Setting::flush();
    }
}
