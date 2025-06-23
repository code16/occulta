# Occulta

## Purpose
Save a versioned and encrypted copy of .env on a storage disk (eg: S3)

## How it works
Occulta uses [AWS KMS](https://aws.amazon.com/kms/) and [Envelope encryption strategy](https://docs.aws.amazon.com/kms/latest/developerguide/kms-cryptography.html#enveloping) to encrypt your `.env` file and store it on a given laravel disk (eg: S3). 
It also keeps a versioned history of your encrypted `.env` files, so you can restore previous versions if needed.
<br>
Occulta will create an archive containing your encrypted environment file and an encrypted key file, which will be used by occulta to decrypt your env when needed.


## Installation
This package requires Laravel 11.x or higher, php's extensions openssl and zip.

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
return [
        // kms key id as seen in aws's kms dashboard (usually it looks like an uuid)
        'key_id' => '0904c439-ff1f-4e9d-8a26-4e32ced6fe0x',
        
        [...]
        
        'destination_disk' => 's3_backup',
        'destination_path' => null, // defaults to 'dotenv/'
    
        // If you want to backup an env file with a suffix such as .env.production, you can set this to your desired suffix
        'env_suffix' => null, // eg: 'production'
        
        [...]
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

Now you should schedule tasks for backup and cleanup in `app/Console/Kernel.php` (`bootstrap/app.php` since Laravel 11) :

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

Occulta will use your KMS configuration and AWS access and secret keys to decrypt your env file.
<br>
> [!IMPORTANT]  
> It is likely that these credentials where in your lost .env, then, you can follow the [recovery procedure](docs/RECOVERY.md) to restore your environment.


## Testing

The package comes with a comprehensive test suite. To run the tests, you can use the following command:

```bash
composer test
```

The tests cover:

- The main `Occulta` class functionality for encrypting and decrypting values and files
- The `EncryptFileWithKmsCommand` for encrypting .env files and storing them
- The `DecryptFileWithKmsCommand` for extracting and decrypting .env files from zip archives
- The `CleanupEncryptedDotenvsCommand` for managing the history of encrypted .env files

The tests use mocks for AWS KMS to avoid actual AWS calls during testing.
