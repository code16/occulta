<?php

namespace Code16\Occulta\Commands;

use Carbon\Carbon;
use Code16\Occulta\Occulta;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class EncryptFileWithKmsCommand extends Command
{
    public $signature = 'occulta:encrypt-file';

    public $description = 'Store in s3 an encrypted version of current .env';

    public function handle(): int
    {
        $service = new Occulta();
        $envFileSuffix = config('occulta.env_suffix', null);
        $envFilePath = base_path('.env');

        if ($envFileSuffix) {
            if(preg_match('/([\s ])/m', $envFileSuffix)) {
                $this->error('Environment suffix contains whitespaces.');
                return self::FAILURE;
            }

            $this->line('Using environment file: .env.'.$envFileSuffix);
            $envFilePath = base_path('.env.'.$envFileSuffix);
        }

        $files = $service->encryptFile($envFilePath);

        if(is_array($files) && isset($files['file']) && isset($files['key'])) {
            $file = $files['file'];
            $key = $files['key'];

            $zip = new ZipArchive();
            $zipPath = base_path($envFileSuffix ? '.env.'.$envFileSuffix.'.encrypted.zip' : '.env.encrypted.zip');
            if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {;
                $this->error('Failed to create zip file.');
                return self::FAILURE;
            }

            $zip->addFile($file, 'file.encrypted');
            $zip->addFile($key, 'key.encrypted');
            $zip->close();

            // Removing local files after zipping
            unlink(base_path('.env.encrypted'));
            unlink(base_path('.env.key.encrypted'));

            $zipDestinationPath = config('occulta.destination_path', 'dotenv/') . Carbon::now()->format('YmdHis') . ($envFileSuffix ? '.env.'.$envFileSuffix.'.zip' : '.env.zip');
            // Pushing the zip file to the configured storage disk
            Storage::disk(config('occulta.destination_disk'))->put(
                path: $zipDestinationPath,
                contents: file_get_contents(base_path('.env.encrypted.zip')),
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
        } else {
            $this->error('Encryption failed or returned unexpected format.');
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
