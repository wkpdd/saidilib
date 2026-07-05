<?php

namespace App\Console\Commands;

use App\Models\ProductImage;
use App\Support\Thumbnailer;
use Illuminate\Console\Command;

class GenerateThumbnails extends Command
{
    protected $signature = 'images:thumbnails';

    protected $description = 'Generate responsive WebP thumbnails for all locally-stored product images';

    public function handle(): int
    {
        $images = ProductImage::query()->get();
        $written = 0;

        $bar = $this->output->createProgressBar($images->count());
        $bar->start();

        foreach ($images as $image) {
            $written += Thumbnailer::generateAll($image->path);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Done. {$written} thumbnails generated across {$images->count()} images.");

        return self::SUCCESS;
    }
}
