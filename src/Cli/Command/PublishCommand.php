<?php

declare(strict_types=1);

namespace MonkeysLegion\Notifications\Cli\Command;

use MonkeysLegion\Cli\Console\Attributes\Command as CommandAttr;
use MonkeysLegion\Cli\Console\Command;
use MonkeysLegion\Cli\Console\Traits\Cli;
use MonkeysLegion\Mlc\Config;

#[CommandAttr('notifications:publish', 'Publish the notification system configuration and migrations')]
final class PublishCommand extends Command
{
    use Cli;

    public function __construct(
        protected Config $config
    ) {
        parent::__construct();
    }

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
        $format = strtolower(trim((string) ($this->argument(0) ?? '')));
        if ($format === '') {
            $format = strtolower(trim((string) $this->ask('Choose config format to publish [mlc/php] (default: mlc): ')));
        }

        if ($format === '') {
            $format = 'mlc';
        }

        if (!in_array($format, ['mlc', 'php'], true)) {
            $this->cliLine()
                ->error(' ✖ ')
                ->add('Invalid config format. Use "mlc" or "php".', 'red')
                ->print();
            return self::FAILURE;
        }

        $configSrc = realpath(__DIR__ . "/../../../config/notifications.{$format}");
        $configDest = "config/notifications.{$format}";

        if ($configSrc) {
            $this->ensureDirectoryExists('config');
            $this->publish($configSrc, $configDest);
        } else {
            $this->cliLine()
                ->error(' ✖ ')
                ->add("Notifications config source file not found for format: {$format}", 'red')
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

                // Special handling for the SQL migration to replace the table name placeholder
                if ($file === 'create_notifications_table.sql') {
                    $this->publishMigrationWithReplacement($srcPath, $destPath);
                } else {
                    $this->publish($srcPath, $destPath);
                }
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
     * Handle publishing a migration file with placeholder replacement.
     *
     * @param string $src
     * @param string $dest
     * @return void
     */
    protected function publishMigrationWithReplacement(string $src, string $dest): void
    {
        $tableNameConfig = $this->config->get('notifications.table', 'notifications');
        $tableName = is_string($tableNameConfig) ? $tableNameConfig : 'notifications';
        $content = file_get_contents($src);
        
        if ($content === false) {
            $this->error("Could not read migration file: {$src}");
            return;
        }

        $content = str_replace('{{notifications_table}}', $tableName, $content);

        // We'll write to a temp file and then use the publish() helper
        $tmpFile = tempnam(sys_get_temp_dir(), 'notifications_migration_');
        file_put_contents($tmpFile, $content);

        try {
            $this->publish($tmpFile, $dest);
        } finally {
            if (file_exists($tmpFile)) {
                unlink($tmpFile);
            }
        }
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
