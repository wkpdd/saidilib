<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Support\Thumbnailer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        // Categories give the "industry of categories" feel — each has an icon + color.
        // The last flag marks a "special" category that gets the playful kids treatment.
        $categories = [
            ['Scolaire', 'مدرسة', '✏️', '#f59e0b', 'Tout pour la rentrée et la réussite scolaire.', false],
            ['Bureautique', 'مكتب', '🗂️', '#2563eb', 'Fournitures et matériel pour le bureau.', false],
            ['Informatique', 'إعلام آلي', '💻', '#0ea5e9', 'Accessoires et consommables informatiques.', false],
            ['Beaux-Arts', 'فنون', '🎨', '#db2777', 'Dessin, peinture et loisirs créatifs.', false],
            ['Papier & Impression', 'ورق وطباعة', '📄', '#16a34a', 'Ramettes, cahiers et papeterie.', false],
            ['Bagagerie', 'حقائب', '🎒', '#7c3aed', 'Cartables, sacs et trousses.', false],
            ['Jeux pour Enfants', 'ألعاب الأطفال', '🧸', '#ec4899', 'Jeux, jouets et loisirs pour les petits — apprendre en s\'amusant.', true],
        ];

        $catModels = [];
        $sort = 0;
        foreach ($categories as [$fr, $ar, $icon, $color, $desc, $special]) {
            $catModels[$fr] = Category::updateOrCreate(
                ['slug' => Str::slug($fr)],
                [
                    'name_fr'        => $fr,
                    'name_ar'        => $ar,
                    'icon'           => $icon,
                    'color'          => $color,
                    'description_fr' => $desc,
                    'is_featured'    => true,
                    'is_active'      => true,
                    // Sort the special category first (0) so it leads the storefront.
                    'sort_order'     => $special ? 0 : ++$sort,
                ]
            );
        }

        // [name_fr, name_ar, category, price, compare_at, brand, new?, featured?, sizes[], image_keywords]
        $products = [
            ['Cahier 96 pages grand format', 'كراس 96 صفحة', 'Scolaire', 120, 160, 'Saidi', true, true,
                ['Petit format (17x22)', 'Grand format (24x32)', 'Format A4'], 'notebook,school'],
            ['Stylo à bille bleu (boîte de 50)', 'أقلام جافة', 'Scolaire', 850, null, 'BIC', false, true, [], 'pen,ballpoint'],
            ['Trousse scolaire double compartiment', 'مقلمة مدرسية', 'Bagagerie', 650, 900, 'Saidi', true, true,
                ['Rose', 'Bleu', 'Noir', 'Vert'], 'pencilcase'],
            ['Cartable à roulettes 18 pouces', 'حقيبة مدرسية بعجلات', 'Bagagerie', 4500, 5800, 'Saidi', false, true,
                ['18 pouces', '20 pouces'], 'schoolbag,backpack'],
            ['Ramette papier A4 80g (500 feuilles)', 'رزمة ورق A4', 'Papier & Impression', 720, null, 'Double A', false, true, [], 'paper,stack'],
            ['Classeur à levier A4', 'مصنف', 'Bureautique', 380, 480, 'Saidi', false, false,
                ['Dos 5cm', 'Dos 8cm'], 'binder,folder'],
            ['Calculatrice scientifique 240 fonctions', 'آلة حاسبة علمية', 'Bureautique', 2200, 2700, 'Casio', true, true, [], 'calculator'],
            ['Boîte de 12 feutres de coloriage', 'أقلام تلوين', 'Beaux-Arts', 320, null, 'Maped', false, true,
                ['12 couleurs', '24 couleurs', '36 couleurs'], 'markers,crayons'],
            ['Set de peinture acrylique 12 tubes', 'ألوان أكريليك', 'Beaux-Arts', 1450, 1900, 'Saidi', true, false, [], 'paint,acrylic'],
            ['Clé USB 64 Go', 'فلاش USB', 'Informatique', 1200, 1600, 'Kingston', false, true,
                ['32 Go', '64 Go', '128 Go'], 'usb,flashdrive'],
            ['Souris optique sans fil', 'فأرة لاسلكية', 'Informatique', 1100, null, 'Logitech', true, false,
                ['Noir', 'Blanc'], 'computer,mouse'],
            ['Cartouche d\'encre noire compatible', 'خرطوشة حبر', 'Informatique', 1850, 2300, 'Saidi', false, false, [], 'printer,ink'],

            // ── Games for Kids ──────────────────────────────────────────────
            ['Puzzle 100 pièces animaux', 'أحجية 100 قطعة', 'Jeux pour Enfants', 550, 700, 'Saidi', true, true,
                ['100 pièces', '250 pièces'], 'puzzle,jigsaw'],
            ['Jeu de construction 120 blocs', 'مكعبات البناء 120 قطعة', 'Jeux pour Enfants', 1350, 1800, 'Saidi', true, true,
                ['120 blocs', '250 blocs'], 'buildingblocks,toy'],
            ['Jeu de société famille', 'لعبة عائلية', 'Jeux pour Enfants', 980, null, 'Saidi', false, true, [], 'boardgame'],
            ['Peluche ourson 30cm', 'دمية دب 30 سم', 'Jeux pour Enfants', 750, 990, 'Saidi', true, true,
                ['30 cm', '45 cm'], 'teddybear,plush'],
        ];

        $palette = ['eef2ff/2563eb', 'fef3c7/f59e0b', 'fce7f3/db2777', 'dcfce7/16a34a', 'e0f2fe/0ea5e9', 'ede9fe/7c3aed'];
        $i = 0;
        foreach ($products as [$fr, $ar, $cat, $price, $cmp, $brand, $new, $feat, $sizes, $keywords]) {
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

            // Real, locally-hosted photos (downloaded once, then WebP-thumbnailed).
            // Falls back to a coloured placeholder if the download can't be fetched.
            $product->images()->delete();
            $imgs = [];
            for ($g = 1; $g <= 2; $g++) {
                $path = $this->fetchImage($product->slug, $keywords, $g)
                    ?? "https://placehold.co/800x800/{$palette[$i % count($palette)]}?text=" . urlencode($product->name_fr) . "+$g";

                $imgs[] = ProductImage::create([
                    'product_id' => $product->id,
                    'path'       => $path,
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

    /**
     * Download a real, keyword-relevant photo into the public disk and return
     * its storage path (e.g. "products/cahier-1.jpg"). Idempotent: skips the
     * download if the file already exists. Returns null on any failure so the
     * caller can fall back to a placeholder.
     */
    private function fetchImage(string $slug, string $keywords, int $n): ?string
    {
        $path = "products/{$slug}-{$n}.jpg";
        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            try {
                // loremflickr serves real Creative-Commons Flickr photos by tag.
                $url = "https://loremflickr.com/800/800/{$keywords}?random={$n}";
                $res = Http::timeout(20)->retry(2, 500)->get($url);

                if (! $res->successful() || strlen($res->body()) < 2000) {
                    return null;
                }
                $disk->put($path, $res->body());
            } catch (\Throwable $e) {
                return null;
            }
        }

        Thumbnailer::generateAll($path);

        return $path;
    }
}
