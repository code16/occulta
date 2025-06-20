<?php

namespace Code16\Occulta\Tests;

use Illuminate\Support\Facades\Config;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            'Code16\Occulta\OccultaServiceProvider',
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Set up common configuration
        Config::set('occulta.key_id', 'test-key-id');
        Config::set('occulta.context', ['app' => 'testing']);
        Config::set('occulta.destination_disk', 'local');
        Config::set('occulta.destination_path', 'dotenv/');
        Config::set('occulta.env_suffix', null);
        Config::set('occulta.number_of_encrypted_dotenv_to_keep_when_cleaning_up', 3);
        Config::set('services.kms.key', 'test-key');
        Config::set('services.kms.secret', 'test-secret');
        Config::set('services.kms.region', 'us-west-1');
    }
}
