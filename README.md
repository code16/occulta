# Occulta

## Purpose
Save a versioned and encrypted copy of .env on aws s3

## How it works
Occulta uses [AWS KMS](https://aws.amazon.com/kms/) and [Envelope encryption concept](https://docs.aws.amazon.com/kms/latest/developerguide/kms-cryptography.html#enveloping) to encrypt your `.env` file and store it on a given laravel disk (eg: S3). 
It also keeps a versioned history of your encrypted `.env` files, so you can restore previous versions if needed.
<br>
Occulta will create an archive containing your encrypted environment file and an encrypted key file, which will be used by occulta to decrypt your env when needed.


## Installation
This package requires Laravel 9.x or higher, php's extensions openssl and zip.

You can install the package via composer:

```bash
composer require code16/occulta
```

Next you should publish the config file :

```bash
php artisan vendor:publish --tag=occulta-config
```

and setup your values (especially the kms `key_id` and `destination disk`) in your `config/occulta.php` file :

```php

    'key_id' => '0904c439-ff1f-4e9d-8a26-4e32ced6fe0x',

    'destination_disk' => 's3_backup',
    'destination_path' => null, // defaults to 'dotenv/'
];
```

Then, you should setup credentials to the proper aws user [allowed](https://docs.aws.amazon.com/kms/latest/developerguide/key-policies.html#key-policy-default-allow-users) to "use" the given kms key, by adding a kms section in your `config/services.php` file :

```php
    'kms' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => 'eu-central-1',
    ],
```

Nom you should schedule tasks for backup and cleanup in `app/Console/Kernel.php` (`bootstrap/app.php` since Laravel 11) :

```php
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('occulta:encrypt')->dailyAt('01:00');
        $schedule->command('occulta:clean')->dailyAt('02:00');
    }
```

### Decrypting an encrypted env archive
If you need to decrypt an encrypted env archive, you can use the `occulta:decrypt` command:

```bash
php artisan occulta:decrypt path/to/encrypted/archive.zip
```

Occulta will use your KMS configuration to decrypt the key file and the use the key to decrypt your env file.
