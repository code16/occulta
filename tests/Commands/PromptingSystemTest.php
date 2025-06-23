<?php

namespace Code16\Occulta\Tests\Commands;

use Code16\Occulta\Commands\DecryptFileWithKmsCommand;
use Code16\Occulta\Occulta;
use Code16\Occulta\Tests\TestCase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use ZipArchive;

class PromptingSystemTest extends TestCase
{
    protected $zipPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test zip file with encrypted .env and key
        $this->zipPath = base_path('.env.encrypted.zip');
        $zip = new ZipArchive();

        if ($zip->open($this->zipPath, ZipArchive::CREATE) === true) {
            $zip->addFromString('.env.encrypted', 'encrypted-content');
            $zip->addFromString('key.encrypted', base64_encode('encrypted-key-data'));
            $zip->close();
        }
    }

    protected function tearDown(): void
    {
        // Clean up the zip file
        if (file_exists($this->zipPath)) {
            unlink($this->zipPath);
        }

        // Clean up any extracted files
        if (file_exists(base_path('.env.encrypted'))) {
            unlink(base_path('.env.encrypted'));
        }
        if (file_exists(base_path('key.encrypted'))) {
            unlink(base_path('key.encrypted'));
        }
        if (file_exists(base_path('.env.decrypted'))) {
            unlink(base_path('.env.decrypted'));
        }

        parent::tearDown();
    }

    #[Test]
    public function it_prompts_for_aws_credentials_when_not_configured()
    {
        // Unset the AWS credentials and KMS key ID
        config()->set('services.kms.key', null);
        config()->set('services.kms.secret', null);
        config()->set('occulta.key_id', null);
        config()->set('services.kms.region', null);

        // Mock the Occulta service
        $mockOcculta = Mockery::mock(Occulta::class);
        $mockOcculta->shouldReceive('decrypt')
            ->once()
            ->with('encrypted-key-data', base_path('.env.encrypted'))
            ->andReturn(base_path('.env.decrypted'));

        // Run the command
        $this->app->instance(Occulta::class, $mockOcculta);
        $this->artisan(DecryptFileWithKmsCommand::class, ['encryptedEnvZipPath' => $this->zipPath])
            ->expectsQuestion('Please enter your KMS key id.', 'test-prompted-key-id')
            ->expectsQuestion('Please enter an AWS access key for a user with KMS decrypt permissions on your KMS key.', 'test-prompted-access-key')
            ->expectsQuestion('Please enter the AWS secret key corresponding to your access key.', 'test-prompted-secret-key')
            ->expectsQuestion('Please enter the AWS region corresponding to your key.', 'test-prompted-region')
            ->assertExitCode(0)
            ->assertSuccessful();

        // Verify that the configuration was updated with the prompted values
        $this->assertEquals('test-prompted-key-id', config('occulta.key_id'));
        $this->assertEquals('test-prompted-access-key', config('services.kms.key'));
        $this->assertEquals('test-prompted-secret-key', config('services.kms.secret'));
        $this->assertEquals('test-prompted-region', config('services.kms.region'));
    }

    #[Test]
    public function it_prompts_for_aws_credentials_when_only_key_id_is_configured()
    {
        // Set the KMS key ID but unset the AWS credentials
        config()->set('occulta.key_id', 'test-key-id');
        config()->set('services.kms.key', null);
        config()->set('services.kms.secret', null);
        config()->set('services.kms.region', null);

        // Mock the Occulta service
        $mockOcculta = Mockery::mock(Occulta::class);
        $mockOcculta->shouldReceive('decrypt')
            ->once()
            ->with('encrypted-key-data', base_path('.env.encrypted'))
            ->andReturn(base_path('.env.decrypted'));

        // Run the command
        $this->app->instance(Occulta::class, $mockOcculta);
        $this->artisan(DecryptFileWithKmsCommand::class, ['encryptedEnvZipPath' => $this->zipPath])
            ->expectsQuestion('Please enter an AWS access key for a user with KMS decrypt permissions on your KMS key.', 'test-prompted-access-key')
            ->expectsQuestion('Please enter the AWS secret key corresponding to your access key.', 'test-prompted-secret-key')
            ->expectsQuestion('Please enter the AWS region corresponding to your key.', 'test-prompted-region')
            ->assertExitCode(0)
            ->assertSuccessful();

        // Verify that the configuration was updated with the prompted values
        $this->assertEquals('test-key-id', config('occulta.key_id')); // This should remain unchanged
        $this->assertEquals('test-prompted-access-key', config('services.kms.key'));
        $this->assertEquals('test-prompted-secret-key', config('services.kms.secret'));
        $this->assertEquals('test-prompted-region', config('services.kms.region'));
    }

    #[Test]
    public function it_prompts_for_aws_credentials_when_only_region_is_configured()
    {
        // Set the KMS region but unset the AWS credentials and key ID
        config()->set('occulta.key_id', null);
        config()->set('services.kms.key', null);
        config()->set('services.kms.secret', null);
        config()->set('services.kms.region', 'test-region');

        // Mock the Occulta service
        $mockOcculta = Mockery::mock(Occulta::class);
        $mockOcculta->shouldReceive('decrypt')
            ->once()
            ->with('encrypted-key-data', base_path('.env.encrypted'))
            ->andReturn(base_path('.env.decrypted'));

        // Run the command
        $this->app->instance(Occulta::class, $mockOcculta);
        $this->artisan(DecryptFileWithKmsCommand::class, ['encryptedEnvZipPath' => $this->zipPath])
            ->expectsQuestion('Please enter your KMS key id.', 'test-prompted-key-id')
            ->expectsQuestion('Please enter an AWS access key for a user with KMS decrypt permissions on your KMS key.', 'test-prompted-access-key')
            ->expectsQuestion('Please enter the AWS secret key corresponding to your access key.', 'test-prompted-secret-key')
            ->assertExitCode(0)
            ->assertSuccessful();

        // Verify that the configuration was updated with the prompted values
        $this->assertEquals('test-prompted-key-id', config('occulta.key_id'));
        $this->assertEquals('test-prompted-access-key', config('services.kms.key'));
        $this->assertEquals('test-prompted-secret-key', config('services.kms.secret'));
        $this->assertEquals('test-region', config('services.kms.region')); // This should remain unchanged
    }

    #[Test]
    public function it_does_not_prompt_for_aws_credentials_when_already_configured()
    {
        // Set all the required configuration values
        config()->set('occulta.key_id', 'test-key-id');
        config()->set('services.kms.key', 'test-access-key');
        config()->set('services.kms.secret', 'test-secret-key');
        config()->set('services.kms.region', 'test-region');

        // Mock the Occulta service
        $mockOcculta = Mockery::mock(Occulta::class);
        $mockOcculta->shouldReceive('decrypt')
            ->once()
            ->with('encrypted-key-data', base_path('.env.encrypted'))
            ->andReturn(base_path('.env.decrypted'));

        // Run the command
        $this->app->instance(Occulta::class, $mockOcculta);
        $this->artisan(DecryptFileWithKmsCommand::class, ['encryptedEnvZipPath' => $this->zipPath])
            ->assertExitCode(0)
            ->assertSuccessful();

        // Verify that the configuration values remain unchanged
        $this->assertEquals('test-key-id', config('occulta.key_id'));
        $this->assertEquals('test-access-key', config('services.kms.key'));
        $this->assertEquals('test-secret-key', config('services.kms.secret'));
        $this->assertEquals('test-region', config('services.kms.region'));
    }
}
