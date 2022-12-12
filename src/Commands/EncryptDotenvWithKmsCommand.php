<?php

namespace Code16\Occulta\Commands;

use Carbon\Carbon;
use Code16\Occulta\Occulta;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class EncryptDotenvWithKmsCommand extends Command
{
    public $signature = 'occulta:encrypt';

    public $description = 'Store in s3 an encrypted version of current .env';

    public function handle(): int
    {
        $service = new Occulta();

        Storage::disk(
            config('occulta.destination_disk')
        )->put(
            'dotenv/' . Carbon::now()->format('YmdHis') . '.env.kms',
            $service->encrypt(
                config('occulta.should_compress')
                    ? gzencode(file_get_contents(base_path('.env')))
                    : file_get_contents(base_path('.env'))
            )
        );

        return self::SUCCESS;
    }
}
