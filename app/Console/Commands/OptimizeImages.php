<?php

namespace App\Console\Commands;

use App\Models\ProductImage;
use App\Models\Setting;
use App\Support\ImageOptimizer;
use App\Support\Thumbnailer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class OptimizeImages extends Command
{
    protected $signature = 'images:optimize {--dry-run : Report savings without writing any files}';

    protected $description = 'Retroactively resize + recompress already-uploaded product images, then regenerate their thumbnails';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $disk = Storage::disk('public');
        $images = ProductImage::query()->get();

        $optimized = 0;
        $skipped = 0;
        $bytesBefore = 0;
        $bytesAfter = 0;

        $bar = $this->output->createProgressBar($images->count());
        $bar->start();

        foreach ($images as $image) {
            $bar->advance();

            if (Setting::isExternal($image->path) || ! $disk->exists($image->path)) {
                $skipped++;
                continue;
            }

            $original = $disk->get($image->path);
            $result = ImageOptimizer::optimize($original);

            if (strlen($result) >= strlen($original)) {
                $skipped++;
                continue;
            }

            $bytesBefore += strlen($original);
            $bytesAfter += strlen($result);
            $optimized++;

            if (! $dryRun) {
                $disk->put($image->path, $result);
                Thumbnailer::generateAll($image->path);
            }
        }

        $bar->finish();
        $this->newLine(2);

        $savedMb = round(($bytesBefore - $bytesAfter) / 1024 / 1024, 2);
        $label = $dryRun ? '[dry run] Would optimize' : 'Optimized';
        $this->info("{$label} {$optimized} image(s), skipped {$skipped} (already small or external). Saved {$savedMb} MB.");

        return self::SUCCESS;
    }
}
