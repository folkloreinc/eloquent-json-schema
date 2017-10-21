<?php

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Orchestra\Testbench\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        $app->instance('path.public', __DIR__.'/fixture');

        // Database
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
    }

    protected function getPackageProviders($app)
    {
        return [
            \Folklore\EloquentJsonSchema\JsonSchemaServiceProvider::class
        ];
    }

    protected function getPackageAliases($app)
    {
        return [

        ];
    }
}
