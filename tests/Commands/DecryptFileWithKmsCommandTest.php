<?php

namespace Code16\Occulta\Tests\Commands;

use Code16\Occulta\Commands\DecryptFileWithKmsCommand;
use Code16\Occulta\Occulta;
use Code16\Occulta\Tests\TestCase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use ZipArchive;

class DecryptFileWithKmsCommandTest extends TestCase
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
    public function it_decrypts_env_file_from_zip()
    {
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
    }

    #[Test]
    public function it_fails_when_zip_file_does_not_exist()
    {
        $nonExistentZipPath = base_path('non-existent.zip');

        $this->artisan(DecryptFileWithKmsCommand::class, ['encryptedEnvZipPath' => $nonExistentZipPath])
            ->assertExitCode(1)
            ->assertFailed();
    }

    #[Test]
    public function it_fails_when_zip_file_has_wrong_number_of_files()
    {
        // Create a zip with wrong number of files
        $wrongZipPath = base_path('wrong.zip');
        $zip = new ZipArchive();

        if ($zip->open($wrongZipPath, ZipArchive::CREATE) === true) {
            $zip->addFromString('.env.encrypted', 'encrypted-content');
            // Missing key file
            $zip->close();
        }

        $this->artisan(DecryptFileWithKmsCommand::class, ['encryptedEnvZipPath' => $wrongZipPath])
            ->assertExitCode(1)
            ->assertFailed();

        // Clean up
        if (file_exists($wrongZipPath)) {
            unlink($wrongZipPath);
        }
    }

    #[Test]
    public function it_fails_when_zip_file_has_wrong_file_names()
    {
        // Create a zip with wrong file names
        $wrongZipPath = base_path('wrong.zip');
        $zip = new ZipArchive();

        if ($zip->open($wrongZipPath, ZipArchive::CREATE) === true) {
            $zip->addFromString('wrong.env', 'encrypted-content');
            $zip->addFromString('wrong.key', 'encrypted-key');
            $zip->close();
        }

        $this->artisan(DecryptFileWithKmsCommand::class, ['encryptedEnvZipPath' => $wrongZipPath])
            ->assertExitCode(1)
            ->assertFailed();

        // Clean up
        if (file_exists($wrongZipPath)) {
            unlink($wrongZipPath);
        }
        if (file_exists(base_path('wrong.env'))) {
            unlink(base_path('wrong.env'));
        }
        if (file_exists(base_path('wrong.key'))) {
            unlink(base_path('wrong.key'));
        }
    }

    #[Test]
    public function it_fails_when_decryption_throws_exception()
    {
        // Mock the Occulta service to throw an exception
        $mockOcculta = Mockery::mock(Occulta::class);
        $mockOcculta->shouldReceive('decrypt')
            ->once()
            ->with('encrypted-key-data', base_path('.env.encrypted'))
            ->andThrow(new \Exception('Decryption failed'));

        // Run the command
        $this->app->instance(Occulta::class, $mockOcculta);
        $this->artisan(DecryptFileWithKmsCommand::class, ['encryptedEnvZipPath' => $this->zipPath])
            ->assertExitCode(1)
            ->assertFailed();
    }

    #[Test]
    public function it_cleans_up_artifacts_after_successful_decryption()
    {
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

        // Verify that artifacts were cleaned up
        $this->assertFileDoesNotExist(base_path('.env.encrypted'));
        $this->assertFileDoesNotExist(base_path('key.encrypted'));
    }

    #[Test]
    public function it_cleans_up_artifacts_after_failed_decryption()
    {
        // Mock the Occulta service to throw an exception
        $mockOcculta = Mockery::mock(Occulta::class);
        $mockOcculta->shouldReceive('decrypt')
            ->once()
            ->with('encrypted-key-data', base_path('.env.encrypted'))
            ->andThrow(new \Exception('Decryption failed'));

        // Run the command
        $this->app->instance(Occulta::class, $mockOcculta);
        $this->artisan(DecryptFileWithKmsCommand::class, ['encryptedEnvZipPath' => $this->zipPath])
            ->assertExitCode(1)
            ->assertFailed();

        // Verify that artifacts were cleaned up
        $this->assertFileDoesNotExist(base_path('.env.encrypted'));
        $this->assertFileDoesNotExist(base_path('key.encrypted'));
    }
}
