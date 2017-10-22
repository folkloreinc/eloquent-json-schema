Installation
================================================

#### Dependencies

* [Laravel 5.x](https://github.com/laravel/laravel) or [Lumen](https://github.com/laravel/lumen)
* [JSON Schema](https://github.com/justinrainbow/json-schema)


**1-** Require the package via Composer in your `composer.json`.
```json
{
	"require": {
		"folklore/eloquent-json-schema": "~0.9.0"
	}
}
```

**2-** Run Composer to install or update the new requirement.

```bash
$ composer install
```

or

```bash
$ composer update
```

### Laravel 5.5

**1-** Publish the configuration file

```bash
$ php artisan vendor:publish --provider="Folklore\EloquentJsonSchema\JsonSchemaServiceProvider"
```

**2-** Review the configuration file

```
config/json-schema.php
```

### Laravel <= 5.4.x

**1-** Add the service provider to your `config/app.php` file
```php
Folklore\EloquentJsonSchema\JsonSchemaServiceProvider::class,
```

**2-** Publish the configuration file

```bash
$ php artisan vendor:publish --provider="Folklore\EloquentJsonSchema\JsonSchemaServiceProvider"
```

**3-** Review the configuration file

```
config/json-schema.php
```

### Lumen

**1-** Load the service provider in `bootstrap/app.php`
```php
$app->register(Folklore\EloquentJsonSchema\JsonSchemaServiceProvider::class);
```

**2-** Publish the configuration file

```bash
$ php artisan json-schema:publish
```

**3-** Load configuration file in `bootstrap/app.php`

*Important*: this command needs to be executed before the registration of the service provider

```php
$app->configure('json-schema');
...
$app->register(Folklore\EloquentJsonSchema\JsonSchemaServiceProvider::class)
```

**4-** Review the configuration file

```
config/json-schema.php
```
