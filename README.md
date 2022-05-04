## Purpose

Save a versioned and encrypted copy of .env on aws s3

## Installation

This package requires Laravel 8.x or higher.

You can install the package via composer:

```bash
composer require code16/occulta
```

Next you should publish the config file :

```bash
php artisan vendor:publish --provider="Code16\Occulta\OccultaServiceProvider"
```

and setup your values (especially the kms `key_id` and `destination disk`) in your `config/occulta.php` file :

```php

    'key_id' => '0904c439-ff1f-4e9d-8a26-4e32ced6fe0x',

    'destination_disk' => 's3_backup',
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

Nom you should schedule tasks for backup and cleanup in `app/Console/Kernel.php` :

```php
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('occulta:encrypt')->dailyAt('01:00');
        $schedule->command('occulta:clean')->dailyAt('02:00');
    }
```
