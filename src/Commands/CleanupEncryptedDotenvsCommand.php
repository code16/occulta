<?php

namespace Code16\Occulta\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupEncryptedDotenvsCommand extends Command
{
    public $signature = 'occulta:clean';

    public $description = 'Clean old encrypted dotenv';

    public function handle(): int
    {
        collect(
            Storage::disk(
                config('encrypt-env-kms.destination_disk')
            )->files('dotenv/')
        )
            ->sort()
            ->slice(config('encrypt-env-kms.number_of_encrypted_dotenv_to_keep_when_cleaning_up'))
            ->each(function ($filename) {
                Storage::disk(
                    config('encrypt-env-kms.destination_disk')
                )->delete($filename);
            });

        return self::SUCCESS;
    }
}
