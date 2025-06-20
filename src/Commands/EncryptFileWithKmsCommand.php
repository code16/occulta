<?php

namespace Code16\Occulta\Commands;

use Carbon\Carbon;
use Code16\Occulta\Occulta;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class EncryptFileWithKmsCommand extends Command
{
    public $signature = 'occulta:encrypt';

    public $description = 'Store in configured disk an encrypted version of current .env';

    public function handle(): int
    {
        $service = app(Occulta::class);
        $envFileSuffix = config('occulta.env_suffix', null);
        $envFilePath = base_path('.env');

        if ($envFileSuffix !== null) {
            if (!preg_match('/^[A-Za-z0-9_-]+$/m', $envFileSuffix)) {
                $this->error('Environment suffix contains non-alphanumeric characters.');

                return self::FAILURE;
            }

            $this->line('Using environment file: .env.'.$envFileSuffix);
            $envFilePath = base_path('.env.'.$envFileSuffix);
        }

        try {
            $files = $service->encryptFile($envFilePath);
        } catch (\Throwable $e) {
            $this->error('Encryption failed: '.$e->getMessage());

            return self::FAILURE;
        }

        if (!is_array($files) || !isset($files['file']) || !isset($files['key'])) {
            $this->error('Encryption failed or returned unexpected format.');

            return self::FAILURE;
        }

        $file = $files['file'];
        $key = $files['key'];

        $zip = new ZipArchive();
        $zipPath = base_path($envFileSuffix ? '.env.'.$envFileSuffix.'.encrypted.zip' : '.env.encrypted.zip');

        if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
            $this->error('Failed to create zip file.');

            return self::FAILURE;
        }

        // Adding the encrypted .env file and key to the zip
        $zip->addFile($file, ($envFileSuffix ? '.env.'.$envFileSuffix.'.encrypted' : '.env.encrypted'));
        $zip->addFile($key, 'key.encrypted');
        $zip->close();

        // Removing local files after zipping
        if (file_exists($file)) {
            unlink($file);
        }
        if (file_exists($key)) {
            unlink($key);
        }

        $zipDestinationPath = sprintf(
            '%s%s%s',
            (str(config('occulta.destination_path', 'dotenv/'))->endsWith('/') ? config('occulta.destination_path', 'dotenv/') : config('occulta.destination_path', 'dotenv/').'/'),
            Carbon::now()->format('YmdHis'),
            ($envFileSuffix ? '.env.'.$envFileSuffix.'.zip' : '.env.zip')
        );

        // Pushing the zip file to the configured storage disk
        Storage::disk(config('occulta.destination_disk'))->put(
            path: $zipDestinationPath,
            contents: file_get_contents($zipPath),
            options: [
                'recursive' => true,
            ]
        );

        // Removing the zip file after storing it
        unlink($zipPath);

        $this->info('File encrypted successfully: ');
        $this->line(
            Storage::disk(config('occulta.destination_disk'))->path($zipDestinationPath)
        );

        return self::SUCCESS;
    }
}
