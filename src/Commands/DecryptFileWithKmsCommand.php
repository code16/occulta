<?php

namespace Code16\Occulta\Commands;

use Code16\Occulta\Occulta;
use Illuminate\Console\Command;
use ZipArchive;

use function Laravel\Prompts\text;

class DecryptFileWithKmsCommand extends Command
{
    public $signature = 'occulta:decrypt {encryptedEnvZipPath : Path to the zip file containing the encrypted env and key files }';

    public $description = 'Decrypts a zip file containing an encrypted .env file and its key, and stores the decrypted .env file.';

    public function handle(): int
    {
        $service = app(Occulta::class);
        $zipPath = $this->argument('encryptedEnvZipPath');

        if (!file_exists($zipPath)) {
            $this->error("The specified zip file does not exist: {$zipPath}");

            return self::FAILURE;
        }

        if (!config('services.kms.key') || !config('services.kms.secret')) {
            if (!config('occulta.key_id')) {
                $kmsKeyId = text(
                    label: 'Please enter your KMS key id.',
                    placeholder: 'eg: 00264cd4-bf98-4b42-958b-496e7bbae7e6'
                );
                config()->set('occulta.key_id', $kmsKeyId);
            }

            $kmsAccessKey = text(
                label: 'Please enter an AWS access key for a user with KMS decrypt permissions on your KMS key.',
                placeholder: 'eg: AKIAIOSFODNN7EXAMPLE'
            );

            $kmsAccessSecret = text(
                label: 'Please enter the AWS secret key corresponding to your access key.',
                placeholder: 'eg: wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY'
            );

            if (!config('services.kms.region')) {
                $kmsKeyRegion = text(
                    label: 'Please enter the AWS region corresponding to your key.',
                    placeholder: 'eg: eu-central-1'
                );
                config()->set('services.kms.region', $kmsKeyRegion);
            }

            config()->set('services.kms.key', $kmsAccessKey);
            config()->set('services.kms.secret', $kmsAccessSecret);
        }

        $zip = new ZipArchive();
        $files = [];

        if ($zip->open($zipPath) === true) {
            if ($zip->numFiles !== 2) {
                $this->error('The zip file must contain exactly two files: the encrypted .env and the key.');

                return self::FAILURE;
            }

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $files[] = $zip->getNameIndex($i);
            }

            $zip->extractTo(base_path());
            $zip->close();

            $this->info('Extraction completed successfully.');
        } else {
            $this->error('Failed to open ZIP file.');

            return self::FAILURE;
        }

        $envFileName = '';
        $keyFileName = '';
        foreach ($files as $file) {
            if (str($file)->startsWith('.env')) {
                $envFileName = $file;
            } elseif ($file === 'key.encrypted') {
                $keyFileName = $file;
            }
        }

        if ($envFileName == '' || $keyFileName == '') {
            $this->error("The zip file must contain an encrypted .env file and a key file named 'key.encrypted'.");
            $this->cleanArtefacts($envFileName);

            return self::FAILURE;
        }

        $envFilePath = base_path($envFileName);
        $keyFilePath = base_path($keyFileName);

        if (!file_exists($envFilePath) || !file_exists($keyFilePath)) {
            $this->error('The required files were not found after extraction.');
            $this->cleanArtefacts($envFileName);

            return self::FAILURE;
        }

        $encryptedKeyBase64 = file_get_contents($keyFilePath);
        $ciphertextBlob = base64_decode($encryptedKeyBase64);

        try {
            $outputPath = $service->decrypt($ciphertextBlob, $envFilePath);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } finally {
            $this->cleanArtefacts($envFileName);
        }

        $this->info("Decrypted ! Env located at : {$outputPath}");

        return self::SUCCESS;
    }

    private function cleanArtefacts($envFileName): void
    {
        $envFile = $envFileName ? base_path($envFileName) : base_path('.env.encrypted');
        $keyFile = base_path('key.encrypted');

        if (file_exists($envFile)) {
            unlink($envFile);
        }

        if (file_exists($keyFile)) {
            unlink($keyFile);
        }
    }
}
