<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Support\Thumbnailer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ProductImportController extends Controller
{
    /** Template columns: header label => example value. */
    private const COLUMNS = [
        'name_fr'          => 'Cahier 96 pages',
        'name_ar'          => 'كراس 96 صفحة',
        'categorie'        => 'Scolaire',
        'marque'           => 'Saidi',
        'sku'              => 'SP-1001',
        'prix'             => '120',
        'ancien_prix'      => '160',
        'stock'            => '100',
        'description'      => 'Cahier grand format, papier 70g.',
        'actif'            => '1',
        'nouveau'          => '1',
        'vedette'          => '0',
        'image_url'        => 'https://exemple.com/photo.jpg',
    ];

    public function form()
    {
        return view('admin.products.import');
    }

    /** Download the blank .xlsx template (headers + one example row). */
    public function template()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Produits');

        $headers = array_keys(self::COLUMNS);
        $examples = array_values(self::COLUMNS);
        foreach ($headers as $i => $h) {
            $letter = Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValue($letter . '1', $h);
            $sheet->setCellValue($letter . '2', $examples[$i]);
            $sheet->getColumnDimension($letter)->setWidth(22);
        }

        $lastCol = Coordinate::stringFromColumnIndex(count($headers));
        $headerStyle = $sheet->getStyle('A1:' . $lastCol . '1');
        $headerStyle->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $headerStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F0900E');
        $sheet->freezePane('A2');

        $tmp = tempnam(sys_get_temp_dir(), 'tpl') . '.xlsx';
        (new Xlsx($spreadsheet))->save($tmp);

        return response()->download($tmp, 'modele-produits-saidi.xlsx')->deleteFileAfterSend(true);
    }

    /** Parse the uploaded spreadsheet and create/update products. */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv,txt|max:10240',
        ]);

        try {
            $sheet = IOFactory::load($request->file('file')->getRealPath())->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, true); // 1-indexed rows, letter-indexed cols
        } catch (\Throwable $e) {
            return back()->with('error', 'Fichier illisible : ' . $e->getMessage());
        }

        if (count($rows) < 2) {
            return back()->with('error', 'Le fichier est vide ou ne contient pas de lignes de données.');
        }

        // Map header labels -> spreadsheet column letters.
        $header = array_shift($rows);
        $map = [];
        foreach ($header as $letter => $label) {
            $key = $this->normalizeHeader((string) $label);
            if ($key) {
                $map[$key] = $letter;
            }
        }
        if (! isset($map['name_fr']) || ! isset($map['prix'])) {
            return back()->with('error', "Colonnes obligatoires manquantes : 'name_fr' et 'prix'.");
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];
        $rowNum = 1;

        foreach ($rows as $row) {
            $rowNum++;
            $get = fn ($k) => isset($map[$k]) ? trim((string) ($row[$map[$k]] ?? '')) : '';

            $name = $get('name_fr');
            $priceRaw = str_replace([' ', ','], ['', '.'], $get('prix'));

            if ($name === '' && $priceRaw === '') {
                $skipped++;
                continue; // blank line
            }
            if ($name === '') {
                $errors[] = "Ligne {$rowNum} : nom manquant.";
                continue;
            }
            if (! is_numeric($priceRaw)) {
                $errors[] = "Ligne {$rowNum} : prix invalide (« {$get('prix')} »).";
                continue;
            }

            $attrs = [
                'name_fr'          => $name,
                'name_ar'          => $get('name_ar') ?: null,
                'brand'            => $get('marque') ?: null,
                'sku'              => $get('sku') ?: null,
                'short_desc_fr'    => $get('description') ?: null,
                'price'            => (float) $priceRaw,
                'compare_at_price' => is_numeric(str_replace([' ', ','], ['', '.'], $get('ancien_prix')))
                    ? (float) str_replace([' ', ','], ['', '.'], $get('ancien_prix')) : null,
                'stock'            => (int) $get('stock'),
                'category_id'      => $this->resolveCategory($get('categorie')),
                'is_active'        => $this->bool($get('actif'), true),
                'is_new'           => $this->bool($get('nouveau'), false),
                'is_featured'      => $this->bool($get('vedette'), false),
            ];

            // Upsert by SKU when provided, else create a new product.
            $existing = $attrs['sku'] ? Product::where('sku', $attrs['sku'])->first() : null;
            if ($existing) {
                $existing->update($attrs);
                $product = $existing;
                $updated++;
            } else {
                $attrs['slug'] = $this->uniqueSlug($name);
                $product = Product::create($attrs);
                $created++;
            }

            // Optional image URL.
            $imageUrl = $get('image_url');
            if ($imageUrl && Str::startsWith($imageUrl, ['http://', 'https://']) && ! $product->images()->where('path', $imageUrl)->exists()) {
                $product->images()->create(['path' => $imageUrl, 'sort_order' => $product->images()->count()]);
                if (! $product->main_image) {
                    $product->update(['main_image' => $imageUrl]);
                }
            }
        }

        $summary = "Import terminé : {$created} créé(s), {$updated} mis à jour, {$skipped} ignoré(s).";
        if ($errors) {
            $summary .= ' ' . count($errors) . ' erreur(s).';
        }

        return back()->with('success', $summary)->with('import_errors', array_slice($errors, 0, 25));
    }

    private function normalizeHeader(string $label): ?string
    {
        $l = Str::of($label)->lower()->ascii()->trim()->replace([' ', '-'], '_')->value();

        return match (true) {
            in_array($l, ['name_fr', 'nom', 'nom_fr', 'designation', 'produit'], true) => 'name_fr',
            in_array($l, ['name_ar', 'nom_ar'], true)                                  => 'name_ar',
            in_array($l, ['categorie', 'category', 'cat'], true)                       => 'categorie',
            in_array($l, ['marque', 'brand'], true)                                    => 'marque',
            in_array($l, ['sku', 'reference', 'ref'], true)                            => 'sku',
            in_array($l, ['prix', 'price'], true)                                      => 'prix',
            in_array($l, ['ancien_prix', 'compare_at_price', 'old_price'], true)       => 'ancien_prix',
            in_array($l, ['stock', 'quantite', 'qte'], true)                           => 'stock',
            in_array($l, ['description', 'description_courte', 'short_desc_fr'], true)  => 'description',
            in_array($l, ['actif', 'active', 'is_active'], true)                       => 'actif',
            in_array($l, ['nouveau', 'new', 'is_new'], true)                           => 'nouveau',
            in_array($l, ['vedette', 'featured', 'is_featured'], true)                 => 'vedette',
            in_array($l, ['image_url', 'image', 'photo', 'image_url_'], true)          => 'image_url',
            default                                                                     => null,
        };
    }

    private function bool(string $v, bool $default): bool
    {
        $v = Str::lower(trim($v));
        if ($v === '') {
            return $default;
        }

        return in_array($v, ['1', 'oui', 'yes', 'true', 'x', 'vrai', 'o'], true);
    }

    /** Find a category by name (any level) or create a top-level one. */
    private function resolveCategory(string $name): ?int
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $cat = Category::whereRaw('LOWER(name_fr) = ?', [Str::lower($name)])->first();
        if ($cat) {
            return $cat->id;
        }

        return Category::create([
            'name_fr' => $name,
            'slug'    => Str::slug($name) . '-' . Str::random(4),
            'color'   => '#e07d00',
            'is_active' => true,
        ])->id;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'produit';
        $slug = $base;
        $i = 2;
        while (Product::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}
