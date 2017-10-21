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
    }

    public function bootPublishes()
    {
        // Config file path
        $configPath = __DIR__ . '/../../config/config.php';
        $viewsPath = __DIR__ . '/../../resources/views/';
        $langPath = __DIR__ . '/../../resources/lang/';

        // Merge files
        $this->mergeConfigFrom($configPath, 'json-schema');

        // Publish
        $this->publishes([
            $configPath => config_path('json-schema.php')
        ], 'config');

        $this->publishes([
            $viewsPath => base_path('resources/views/vendor/folklore/eloquent-json-schema')
        ], 'views');

        $this->publishes([
            $langPath => base_path('resources/lang/vendor/folklore/eloquent-json-schema')
        ], 'lang');
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
