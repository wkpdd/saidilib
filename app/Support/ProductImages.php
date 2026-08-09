<?php

namespace App\Support;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

/**
 * Removing gallery photos properly, from every entry point (admin form,
 * staff app). Deleting just the row leaves two things broken: the stored file
 * plus its thumbnails linger forever, and `main_image` keeps pointing at a
 * path that no longer exists — so the product goes on advertising a dead
 * photo the admin can't replace.
 *
 * Variant → image links clear themselves (product_variants.image_id is
 * declared nullOnDelete).
 */
class ProductImages
{
    /** Delete the given photos of a product. Returns how many rows went. */
    public static function delete(Product $product, array $ids): int
    {
        $images = $product->images()->whereIn('id', $ids)->get();
        if ($images->isEmpty()) {
            return 0;
        }

        $mainPath = $product->main_image;
        $deletedMain = false;

        foreach ($images as $image) {
            $path = $image->path;
            $image->delete();

            if ($path === $mainPath) {
                $deletedMain = true;
            }

            // Only touch files we host, and only once no other row — this
            // product's or another's — still points at the same path.
            if (! Setting::isExternal($path) && ! ProductImage::where('path', $path)->exists()) {
                Thumbnailer::forget($path);
                Storage::disk('public')->delete($path);
            }
        }

        if ($deletedMain) {
            $product->update(['main_image' => $product->images()->orderBy('sort_order')->first()?->path]);
        }

        return $images->count();
    }
}
