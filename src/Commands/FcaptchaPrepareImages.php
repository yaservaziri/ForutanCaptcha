<?php

namespace Forutan\Captcha\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Forutan\Captcha\Models\FCaptcha;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;

class FcaptchaPrepareImages extends Command
{
    protected $signature = 'fcaptcha:prepare-images
        {--from= : Source image directory containing categorized subfolders. Defaults to package seed images.}';

    protected $description = 'Processes categorized CAPTCHA images: resizes, strips metadata, runs migrations, and stores them in storage and database.';

    public function handle()
    {
        $this->info('🔄 Ensuring FCaptcha migrations are up-to-date...');
        $this->call('migrate');
        $this->info('✅ FCaptcha migrations completed.');

        $from = $this->option('from') 
            ?? config('fcaptcha.image_seed_path') 
            ?? __DIR__ . '/../../database/fcaptcha_seeder';

        $width      = config('fcaptcha.default_width', 200);
        $height     = config('fcaptcha.default_height', 200);
        $quality    = config('fcaptcha.default_quality', 80);
        $outputPath = storage_path('app/private/fcaptcha');

        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $commandBase = $isWindows ? 'magick' : 'convert';

        $binaryCheckCmd = $isWindows ? 'where magick' : 'which convert';
        $binaryCheck = Process::fromShellCommandline($binaryCheckCmd);

        try {
            $binaryCheck->setTimeout(5)->mustRun();
        } catch (ProcessTimedOutException $e) {
            $this->error('⏱️ Timeout while checking for ImageMagick.');
            return 1;
        } catch (ProcessFailedException $e) {
            $this->error('❌ ImageMagick (magick/convert) is not installed or not in PATH.');
            return 1;
        }

        $this->info("🔍 Scanning source directory: $from");
        if (!File::exists($from)) {
            $this->error("❌ Source directory not found: $from");
            return 1;
        }

        $this->info('🗑️ Clearing old CAPTCHA data...');
        FCaptcha::truncate();
        File::deleteDirectory($outputPath);
        File::ensureDirectoryExists($outputPath);

        $categories = File::directories($from);
        if (empty($categories)) {
            $this->warn('⚠️ No category folders found in the source directory.');
            return 0;
        }

        $successCount = 0;
        $failCount = 0;

        foreach ($categories as $categoryPath) {
            $category = basename($categoryPath);

            foreach (File::files($categoryPath) as $file) {
                $ext = strtolower($file->getExtension());
                if (!in_array($ext, ['jpg', 'jpeg', 'png'])) {
                    continue;
                }

                $filename = Str::uuid() . '.jpg';
                $target   = $outputPath . DIRECTORY_SEPARATOR . $filename;

                $cmd = sprintf(
                    '%s %s -resize %dx%d! -strip -interlace Plane -quality %d %s',
                    $commandBase,
                    escapeshellarg($file->getRealPath()),
                    $width,
                    $height,
                    $quality,
                    escapeshellarg($target)
                );

                $process = Process::fromShellCommandline($cmd);

                try {
                    $process->mustRun();
                    $successCount++;
                    $this->info("✅ Converted: " . $file->getFilename());
                } catch (ProcessFailedException $exception) {
                    $failCount++;
                    $this->error("❌ Failed to convert: " . $file->getFilename());
                    continue;
                }

                FCaptcha::create([
                    'image'    => $filename,
                    'category' => $category,
                ]);

                $this->info("📥 Imported: $filename into [$category]");
            }
        }

        $this->line("🎉 <fg=green>Done importing CAPTCHA images from [$from].</>");
        $this->info("📊 Success: $successCount images, ❌ Failed: $failCount images");
        $this->info("📁 Total categories: " . count($categories));

        return 0;
    }
}
