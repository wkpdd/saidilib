<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        // Categories give the "industry of categories" feel — each has an icon + color.
        $categories = [
            ['Scolaire', 'مدرسة', '✏️', '#f59e0b', 'Tout pour la rentrée et la réussite scolaire.'],
            ['Bureautique', 'مكتب', '🗂️', '#2563eb', 'Fournitures et matériel pour le bureau.'],
            ['Informatique', 'إعلام آلي', '💻', '#0ea5e9', 'Accessoires et consommables informatiques.'],
            ['Beaux-Arts', 'فنون', '🎨', '#db2777', 'Dessin, peinture et loisirs créatifs.'],
            ['Papier & Impression', 'ورق وطباعة', '📄', '#16a34a', 'Ramettes, cahiers et papeterie.'],
            ['Bagagerie', 'حقائب', '🎒', '#7c3aed', 'Cartables, sacs et trousses.'],
        ];

        $catModels = [];
        $sort = 0;
        foreach ($categories as [$fr, $ar, $icon, $color, $desc]) {
            $catModels[$fr] = Category::updateOrCreate(
                ['slug' => Str::slug($fr)],
                [
                    'name_fr'        => $fr,
                    'name_ar'        => $ar,
                    'icon'           => $icon,
                    'color'          => $color,
                    'description_fr' => $desc,
                    'is_featured'    => true,
                    'sort_order'     => $sort++,
                ]
            );
        }

        // [name_fr, name_ar, category, price, compare_at, brand, new?, featured?, sizes[]]
        $products = [
            ['Cahier 96 pages grand format', 'كراس 96 صفحة', 'Scolaire', 120, 160, 'Saidi', true, true,
                ['Petit format (17x22)', 'Grand format (24x32)', 'Format A4']],
            ['Stylo à bille bleu (boîte de 50)', 'أقلام جافة', 'Scolaire', 850, null, 'BIC', false, true, []],
            ['Trousse scolaire double compartiment', 'مقلمة مدرسية', 'Bagagerie', 650, 900, 'Saidi', true, true,
                ['Rose', 'Bleu', 'Noir', 'Vert']],
            ['Cartable à roulettes 18 pouces', 'حقيبة مدرسية بعجلات', 'Bagagerie', 4500, 5800, 'Saidi', false, true,
                ['18 pouces', '20 pouces']],
            ['Ramette papier A4 80g (500 feuilles)', 'رزمة ورق A4', 'Papier & Impression', 720, null, 'Double A', false, true, []],
            ['Classeur à levier A4', 'مصنف', 'Bureautique', 380, 480, 'Saidi', false, false,
                ['Dos 5cm', 'Dos 8cm']],
            ['Calculatrice scientifique 240 fonctions', 'آلة حاسبة علمية', 'Bureautique', 2200, 2700, 'Casio', true, true, []],
            ['Boîte de 12 feutres de coloriage', 'أقلام تلوين', 'Beaux-Arts', 320, null, 'Maped', false, true,
                ['12 couleurs', '24 couleurs', '36 couleurs']],
            ['Set de peinture acrylique 12 tubes', 'ألوان أكريليك', 'Beaux-Arts', 1450, 1900, 'Saidi', true, false, []],
            ['Clé USB 64 Go', 'فلاش USB', 'Informatique', 1200, 1600, 'Kingston', false, true,
                ['32 Go', '64 Go', '128 Go']],
            ['Souris optique sans fil', 'فأرة لاسلكية', 'Informatique', 1100, null, 'Logitech', true, false,
                ['Noir', 'Blanc']],
            ['Cartouche d\'encre noire compatible', 'خرطوشة حبر', 'Informatique', 1850, 2300, 'Saidi', false, false, []],
        ];

        $palette = ['eef2ff/2563eb', 'fef3c7/f59e0b', 'fce7f3/db2777', 'dcfce7/16a34a', 'e0f2fe/0ea5e9', 'ede9fe/7c3aed'];
        $i = 0;
        foreach ($products as [$fr, $ar, $cat, $price, $cmp, $brand, $new, $feat, $sizes]) {
            $product = Product::updateOrCreate(
                ['slug' => Str::slug($fr)],
                [
                    'category_id'      => $catModels[$cat]->id,
                    'name_fr'          => $fr,
                    'name_ar'          => $ar,
                    'brand'            => $brand,
                    'sku'              => 'SP-' . str_pad((string) (++$i), 4, '0', STR_PAD_LEFT),
                    'short_desc_fr'    => 'Qualité garantie — disponible chez Saidi Papetrie.',
                    'short_desc_ar'    => 'جودة مضمونة — متوفر لدى سعيدي للقرطاسية.',
                    'description_fr'   => "<p>$fr — un article de la sélection Saidi Papetrie. Idéal pour l'école, le bureau et la maison. Livraison disponible dans les 58 wilayas.</p>",
                    'description_ar'   => "<p>منتج من تشكيلة سعيدي للقرطاسية. مثالي للمدرسة والمكتب والمنزل. التوصيل متاح لكل الولايات.</p>",
                    'price'            => $price,
                    'compare_at_price' => $cmp,
                    'is_active'        => true,
                    'is_new'           => $new,
                    'is_featured'      => $feat,
                    'stock'            => 100,
                ]
            );

            // Gallery images (placeholders — replace via admin)
            $color = $palette[$i % count($palette)];
            $product->images()->delete();
            $imgs = [];
            for ($g = 1; $g <= 3; $g++) {
                $imgs[] = ProductImage::create([
                    'product_id' => $product->id,
                    'path'       => "https://placehold.co/800x800/{$color}?text=" . urlencode($product->name_fr) . "+$g",
                    'alt'        => $product->name_fr,
                    'sort_order' => $g,
                ]);
            }
            $product->update(['main_image' => $imgs[0]->path]);

            // Sizes / variants — optionally tied to an image so the photo swaps
            $product->variants()->delete();
            foreach ($sizes as $s => $label) {
                ProductVariant::create([
                    'product_id'  => $product->id,
                    'image_id'    => $imgs[$s % count($imgs)]->id,
                    'label_fr'    => $label,
                    'price_delta' => $s * 50,
                    'is_default'  => $s === 0,
                    'sort_order'  => $s,
                    'stock'       => 50,
                ]);
            }
        }
    }
}
