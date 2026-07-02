<?php

namespace Database\Seeders;

use App\Models\Wilaya;
use Illuminate\Database\Seeder;

class WilayaSeeder extends Seeder
{
    public function run(): void
    {
        // [code, name_fr, name_ar, home_fee, stopdesk_fee]
        $rows = [
            [1, 'Adrar', 'أدرار', 1200, 700],
            [2, 'Chlef', 'الشلف', 700, 400],
            [3, 'Laghouat', 'الأغواط', 900, 550],
            [4, 'Oum El Bouaghi', 'أم البواقي', 700, 450],
            [5, 'Batna', 'باتنة', 700, 450],
            [6, 'Béjaïa', 'بجاية', 700, 400],
            [7, 'Biskra', 'بسكرة', 800, 500],
            [8, 'Béchar', 'بشار', 1100, 650],
            [9, 'Blida', 'البليدة', 500, 350],
            [10, 'Bouira', 'البويرة', 650, 400],
            [11, 'Tamanrasset', 'تمنراست', 1400, 900],
            [12, 'Tébessa', 'تبسة', 750, 450],
            [13, 'Tlemcen', 'تلمسان', 700, 400],
            [14, 'Tiaret', 'تيارت', 750, 450],
            [15, 'Tizi Ouzou', 'تيزي وزو', 650, 400],
            [16, 'Alger', 'الجزائر', 450, 300],
            [17, 'Djelfa', 'الجلفة', 850, 500],
            [18, 'Jijel', 'جيجل', 700, 400],
            [19, 'Sétif', 'سطيف', 700, 400],
            [20, 'Saïda', 'سعيدة', 800, 450],
            [21, 'Skikda', 'سكيكدة', 700, 400],
            [22, 'Sidi Bel Abbès', 'سيدي بلعباس', 700, 400],
            [23, 'Annaba', 'عنابة', 700, 400],
            [24, 'Guelma', 'قالمة', 700, 450],
            [25, 'Constantine', 'قسنطينة', 700, 400],
            [26, 'Médéa', 'المدية', 650, 400],
            [27, 'Mostaganem', 'مستغانم', 700, 400],
            [28, "M'Sila", 'المسيلة', 750, 450],
            [29, 'Mascara', 'معسكر', 700, 400],
            [30, 'Ouargla', 'ورقلة', 950, 600],
            [31, 'Oran', 'وهران', 600, 350],
            [32, 'El Bayadh', 'البيض', 1000, 600],
            [33, 'Illizi', 'إليزي', 1500, 1000],
            [34, 'Bordj Bou Arreridj', 'برج بوعريريج', 700, 400],
            [35, 'Boumerdès', 'بومرداس', 550, 350],
            [36, 'El Tarf', 'الطارف', 750, 450],
            [37, 'Tindouf', 'تندوف', 1500, 1000],
            [38, 'Tissemsilt', 'تيسمسيلت', 750, 450],
            [39, 'El Oued', 'الوادي', 900, 550],
            [40, 'Khenchela', 'خنشلة', 750, 450],
            [41, 'Souk Ahras', 'سوق أهراس', 750, 450],
            [42, 'Tipaza', 'تيبازة', 550, 350],
            [43, 'Mila', 'ميلة', 700, 400],
            [44, 'Aïn Defla', 'عين الدفلى', 700, 400],
            [45, 'Naâma', 'النعامة', 1000, 600],
            [46, 'Aïn Témouchent', 'عين تموشنت', 700, 400],
            [47, 'Ghardaïa', 'غرداية', 900, 550],
            [48, 'Relizane', 'غليزان', 700, 400],
            [49, 'El M\'Ghair', 'المغير', 950, 600],
            [50, 'El Meniaa', 'المنيعة', 1000, 650],
            [51, 'Ouled Djellal', 'أولاد جلال', 900, 550],
            [52, 'Bordj Badji Mokhtar', 'برج باجي مختار', 1600, 1100],
            [53, 'Béni Abbès', 'بني عباس', 1200, 750],
            [54, 'Timimoun', 'تيميمون', 1300, 800],
            [55, 'Touggourt', 'تقرت', 950, 600],
            [56, 'Djanet', 'جانت', 1600, 1100],
            [57, 'In Salah', 'عين صالح', 1400, 900],
            [58, 'In Guezzam', 'عين قزام', 1600, 1100],
        ];

        foreach ($rows as [$code, $fr, $ar, $home, $desk]) {
            Wilaya::updateOrCreate(
                ['code' => $code],
                [
                    'name_fr'      => $fr,
                    'name_ar'      => $ar,
                    'home_fee'     => $home,
                    'stopdesk_fee' => $desk,
                    'is_active'    => true,
                ]
            );
        }
    }
}
