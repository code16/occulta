<?php

namespace Code16\Occulta\Tests\Commands;

use Code16\Occulta\Commands\CleanupEncryptedDotenvsCommand;
use Code16\Occulta\Tests\TestCase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;

class CleanupEncryptedDotenvsCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Set up storage disk
        Storage::fake('local');
    }

    #[Test]
    public function it_keeps_the_most_recent_files_and_deletes_older_ones()
    {
        // Create test files with different timestamps in the filename
        $files = [
            'dotenv/20220101000000.env.zip',
            'dotenv/20220102000000.env.zip',
            'dotenv/20220103000000.env.zip',
            'dotenv/20220104000000.env.zip',
            'dotenv/20220105000000.env.zip',
        ];

        foreach ($files as $file) {
            Storage::disk('local')->put($file, 'test content');
        }

        // Run the command
        $this->artisan(CleanupEncryptedDotenvsCommand::class)
            ->assertExitCode(0)
            ->assertSuccessful();

        // Verify that only the 3 most recent files are kept
        Storage::disk('local')->assertMissing('dotenv/20220101000000.env.zip');
        Storage::disk('local')->assertMissing('dotenv/20220102000000.env.zip');
        Storage::disk('local')->assertExists('dotenv/20220103000000.env.zip');
        Storage::disk('local')->assertExists('dotenv/20220104000000.env.zip');
        Storage::disk('local')->assertExists('dotenv/20220105000000.env.zip');
    }

    #[Test]
    public function it_handles_empty_directory()
    {
        // Run the command with no files in the directory
        $this->artisan(CleanupEncryptedDotenvsCommand::class)
            ->assertExitCode(0)
            ->assertSuccessful();
    }

    #[Test]
    public function it_handles_fewer_files_than_the_keep_limit()
    {
        // Create fewer files than the keep limit
        $files = [
            'dotenv/20220101000000.env.zip',
            'dotenv/20220102000000.env.zip',
        ];

        foreach ($files as $file) {
            Storage::disk('local')->put($file, 'test content');
        }

        // Run the command
        $this->artisan(CleanupEncryptedDotenvsCommand::class)
            ->assertExitCode(0)
            ->assertSuccessful();

        // Verify that all files are kept
        Storage::disk('local')->assertExists('dotenv/20220101000000.env.zip');
        Storage::disk('local')->assertExists('dotenv/20220102000000.env.zip');
    }

    #[Test]
    public function it_respects_custom_keep_limit()
    {
        // Set a custom keep limit
        Config::set('occulta.number_of_encrypted_dotenv_to_keep_when_cleaning_up', 2);

        // Create test files
        $files = [
            'dotenv/20220101000000.env.zip',
            'dotenv/20220102000000.env.zip',
            'dotenv/20220103000000.env.zip',
            'dotenv/20220104000000.env.zip',
        ];

        foreach ($files as $file) {
            Storage::disk('local')->put($file, 'test content');
        }

        // Run the command
        $this->artisan(CleanupEncryptedDotenvsCommand::class)
            ->assertExitCode(0)
            ->assertSuccessful();

        // Verify that only the 2 most recent files are kept
        Storage::disk('local')->assertMissing('dotenv/20220101000000.env.zip');
        Storage::disk('local')->assertMissing('dotenv/20220102000000.env.zip');
        Storage::disk('local')->assertExists('dotenv/20220103000000.env.zip');
        Storage::disk('local')->assertExists('dotenv/20220104000000.env.zip');
    }

    #[Test]
    public function it_respects_custom_destination_path()
    {
        // Set a custom destination path
        Config::set('occulta.destination_path', 'custom/path/');

        // Create test files in the custom path
        $files = [
            'custom/path/20220101000000.env.zip',
            'custom/path/20220102000000.env.zip',
            'custom/path/20220103000000.env.zip',
            'custom/path/20220104000000.env.zip',
        ];

        foreach ($files as $file) {
            Storage::disk('local')->put($file, 'test content');
        }

        // Run the command
        $this->artisan(CleanupEncryptedDotenvsCommand::class)
            ->assertExitCode(0)
            ->assertSuccessful();

        // Verify that only the 3 most recent files are kept
        Storage::disk('local')->assertMissing('custom/path/20220101000000.env.zip');
        Storage::disk('local')->assertExists('custom/path/20220102000000.env.zip');
        Storage::disk('local')->assertExists('custom/path/20220103000000.env.zip');
        Storage::disk('local')->assertExists('custom/path/20220104000000.env.zip');
    }
}
