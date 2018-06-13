<?php

namespace Vreap\Lva\Console;

use Illuminate\Console\Command;

class UninstallCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'lva:uninstall';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Uninstall the lva package';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        if (!$this->confirm('Are you sure to uninstall laravel-lva?')) {
            return;
        }

        $this->removeFilesAndDirectories();

        $this->line('<info>Uninstalling laravel-lva!</info>');
    }

    /**
     * Remove files and directories.
     *
     * @return void
     */
    protected function removeFilesAndDirectories()
    {
        $this->laravel['files']->deleteDirectory(config('lva.directory'));
        $this->laravel['files']->deleteDirectory(public_path('vendor/laravel-lva/'));
        $this->laravel['files']->delete(config_path('lva.php'));
    }
}
