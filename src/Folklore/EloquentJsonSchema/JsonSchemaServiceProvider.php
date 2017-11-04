<?php namespace Folklore\EloquentJsonSchema;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class JsonSchemaServiceProvider extends BaseServiceProvider
{

    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    protected function getRouter()
    {
        return $this->app['router'];
    }

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->bootPublishes();
        $this->bootValidator();
    }

    public function bootPublishes()
    {
        // Config file path
        $configPath = __DIR__ . '/../../config/config.php';

        // Merge files
        $this->mergeConfigFrom($configPath, 'json-schema');

        // Publish
        $this->publishes([
            $configPath => config_path('json-schema.php')
        ], 'config');
    }

    public function bootValidator()
    {
        $this->app['validator']->extend(
            'json_schema',
            \Folklore\EloquentJsonSchema\Contracts\JsonSchemaValidator::class.'@validate'
        );
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        // Schema
        $this->app->bind(
            \Folklore\EloquentJsonSchema\Contracts\JsonSchema::class,
            \Folklore\EloquentJsonSchema\Support\JsonSchema::class
        );

        // Observer
        $this->app->bind(
            \Folklore\EloquentJsonSchema\Contracts\JsonSchemaObserver::class,
            \Folklore\EloquentJsonSchema\JsonSchemaObserver::class
        );

        // Validator
        $this->app->bind(
            \Folklore\EloquentJsonSchema\Contracts\JsonSchemaValidator::class,
            \Folklore\EloquentJsonSchema\JsonSchemaValidator::class
        );
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [];
    }
}
