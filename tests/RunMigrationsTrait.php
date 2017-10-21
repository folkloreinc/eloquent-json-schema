<?php

trait RunMigrationsTrait
{
    public function runMigrations()
    {
        $migrator = $this->getMigrator();

        if (!$migrator->repositoryExists()) {
            $this->artisan('migrate:install', [
                '--database' => 'testbench'
            ]);
        }

        $paths = $this->getMigrationPaths();
        foreach ($paths as $path) {
            $migrator->run($path);
        }

        $this->beforeApplicationDestroyed(function () {
            $this->rollbackMigrations();
        });
    }

    public function rollbackMigrations()
    {
        $migrator = $this->getMigrator();
        $paths = $this->getMigrationPaths();
        foreach ($paths as $path) {
            $migrator->rollback($path);
        }
    }

    protected function getMigrator()
    {
        $migrator = $this->app['migrator'];
        $migrator->setConnection('testbench');
        return $migrator;
    }

    protected function getMigrationPaths()
    {
        return [
            realpath(__DIR__.'/fixture/migrations')
        ];
    }
}
