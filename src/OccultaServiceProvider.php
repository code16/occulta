<?php

namespace Code16\Occulta;

use Code16\Occulta\Commands\CleanupEncryptedDotenvsCommand;
use Code16\Occulta\Commands\EncryptDotenvWithKmsCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Code16\Occulta\Commands\OccultaCommand;

class OccultaServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('occulta')
            ->hasConfigFile()
            ->hasCommand(EncryptDotenvWithKmsCommand::class)
            ->hasCommand(CleanupEncryptedDotenvsCommand::class);;
    }
}
