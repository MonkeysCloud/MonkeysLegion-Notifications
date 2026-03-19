<?php

declare(strict_types=1);

namespace MonkeysLegion\Notifications\Cli\Command;

use MonkeysLegion\Cli\Console\Attributes\Command as CommandAttr;
use MonkeysLegion\Cli\Console\Command;

#[CommandAttr('notifications:publish', 'Publish the notification system configuration and migrations')]
final class PublishCommand extends Command
{
    /**
     * Handle the command execution.
     *
     * @return int
     */
    protected function handle(): int
    {
        $this->cliLine()
            ->add('📦 ', 'cyan', 'bold')
            ->add('Publishing MonkeysLegion Notifications assets...', 'white', 'bold')
            ->print();

        // 1. Publish Configuration
        $configSrc = realpath(__DIR__ . '/../../../config/notifications.mlc');
        $configDest = 'config/notifications.mlc';

        if ($configSrc) {
            $this->ensureDirectoryExists('config');
            $this->publish($configSrc, $configDest);
        } else {
            $this->cliLine()
                ->error(' ✖ ')
                ->add('Notifications config source file not found', 'red')
                ->print();
        }

        // 2. Publish Migrations
        $migrationSrcDir = realpath(__DIR__ . '/../../../database/migrations');
        $migrationDestDir = 'var/migrations';

        if ($migrationSrcDir && is_dir($migrationSrcDir)) {
            $this->ensureDirectoryExists($migrationDestDir);
            
            $files = scandir($migrationSrcDir);
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }

                $srcPath = $migrationSrcDir . '/' . $file;
                $destPath = $migrationDestDir . '/' . $file;

                $this->publish($srcPath, $destPath);
            }
        } else {
             $this->cliLine()
                ->error(' ✖ ')
                ->add('Notifications migrations directory not found', 'red')
                ->print();
        }

        $this->cliLine()
            ->success(' ✨ ')
            ->add('Publishing complete!', 'green', 'bold')
            ->print();

        return self::SUCCESS;
    }

    /**
     * Ensure the target directory exists in the base path.
     *
     * @param string $path
     * @return void
     */
    private function ensureDirectoryExists(string $path): void
    {
        $fullPath = str_starts_with($path, '/') ? $path : \base_path($path);

        if (!is_dir($fullPath)) {
            mkdir($fullPath, 0755, true);
            $this->cliLine()
                ->muted("Created directory: ")
                ->add($fullPath, 'yellow')
                ->print();
        }
    }
}
