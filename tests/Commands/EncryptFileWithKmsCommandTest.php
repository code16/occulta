<?php

namespace Code16\Occulta\Tests\Commands;

use Code16\Occulta\Commands\EncryptFileWithKmsCommand;
use Code16\Occulta\Occulta;
use Code16\Occulta\Tests\TestCase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\Test;

class EncryptFileWithKmsCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Create a fake .env file
        file_put_contents(base_path('.env'), 'APP_ENV=testing');

        // Set up storage disk
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        // Clean up the .env file
        if (file_exists(base_path('.env'))) {
            unlink(base_path('.env'));
        }

        parent::tearDown();
    }

    #[Test]
    public function it_encrypts_env_file_and_stores_it()
    {
        // Mock the Occulta service
        $mockOcculta = Mockery::mock(Occulta::class);
        $mockOcculta->shouldReceive('encryptFile')
            ->once()
            ->with(base_path('.env'))
            ->andReturn([
                'file' => base_path('.env.encrypted'),
                'key' => base_path('.env.key.encrypted'),
            ]);

        // Create the encrypted files for the test
        file_put_contents(base_path('.env.encrypted'), 'encrypted-content');
        file_put_contents(base_path('.env.key.encrypted'), 'encrypted-key');

        // Run the command
        $this->app->instance(Occulta::class, $mockOcculta);
        $this->artisan(EncryptFileWithKmsCommand::class)
            ->assertExitCode(0)
            ->assertSuccessful();

        // Verify that the files were stored in the storage disk
        Storage::disk('local')->assertExists('dotenv/'.date('YmdHis').'.env.zip');

        // Clean up
        if (file_exists(base_path('.env.encrypted'))) {
            unlink(base_path('.env.encrypted'));
        }
        if (file_exists(base_path('.env.key.encrypted'))) {
            unlink(base_path('.env.key.encrypted'));
        }
    }

    #[Test]
    public function it_encrypts_env_file_with_suffix_and_stores_it()
    {
        // Set up configuration with suffix
        Config::set('occulta.env_suffix', 'production');

        // Create a fake .env.production file
        file_put_contents(base_path('.env.production'), 'APP_ENV=production');

        // Mock the Occulta service
        $mockOcculta = Mockery::mock(Occulta::class);
        $mockOcculta->shouldReceive('encryptFile')
            ->once()
            ->with(base_path('.env.production'))
            ->andReturn([
                'file' => base_path('.env.production.encrypted'),
                'key' => base_path('.env.production.key.encrypted'),
            ]);

        // Create the encrypted files for the test
        file_put_contents(base_path('.env.production.encrypted'), 'encrypted-content');
        file_put_contents(base_path('.env.production.key.encrypted'), 'encrypted-key');

        // Run the command
        $this->app->instance(Occulta::class, $mockOcculta);
        $this->artisan(EncryptFileWithKmsCommand::class)
            ->assertExitCode(0)
            ->assertSuccessful();

        // Verify that the files were stored in the storage disk
        Storage::disk('local')->assertExists('dotenv/'.date('YmdHis').'.env.production.zip');

        // Clean up
        if (file_exists(base_path('.env.production'))) {
            unlink(base_path('.env.production'));
        }
        if (file_exists(base_path('.env.production.encrypted'))) {
            unlink(base_path('.env.production.encrypted'));
        }
        if (file_exists(base_path('.env.production.key.encrypted'))) {
            unlink(base_path('.env.production.key.encrypted'));
        }
    }

    #[Test]
    public function it_fails_when_env_suffix_contains_invalid_characters()
    {
        // Set up configuration with invalid suffix
        Config::set('occulta.env_suffix', 'invalid/suffix');

        // Run the command
        $this->artisan(EncryptFileWithKmsCommand::class)
            ->assertExitCode(1)
            ->assertFailed();
    }

    #[Test]
    public function it_fails_when_encryption_throws_exception()
    {
        // Mock the Occulta service to throw an exception
        $mockOcculta = Mockery::mock(Occulta::class);
        $mockOcculta->shouldReceive('encryptFile')
            ->once()
            ->with(base_path('.env'))
            ->andThrow(new \Exception('Encryption failed'));

        // Run the command
        $this->app->instance(Occulta::class, $mockOcculta);
        $this->artisan(EncryptFileWithKmsCommand::class)
            ->assertExitCode(1)
            ->assertFailed();
    }

    #[Test]
    public function it_fails_when_encryption_returns_unexpected_format()
    {
        // Mock the Occulta service to return invalid format
        $mockOcculta = Mockery::mock(Occulta::class);
        $mockOcculta->shouldReceive('encryptFile')
            ->once()
            ->with(base_path('.env'))
            ->andReturn('invalid-format');

        // Run the command
        $this->app->instance(Occulta::class, $mockOcculta);
        $this->artisan(EncryptFileWithKmsCommand::class)
            ->assertExitCode(1)
            ->assertFailed();
    }
}
