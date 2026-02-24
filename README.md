# Simple Settings for Laravel

Lightweight settings manager for Laravel with group namespacing, caching, and automatic type casting.

## Requirements

- PHP 8.2+
- Laravel 12.x

## Installation

```bash
composer require timurturdyev/simple-settings
```

Publish the config and migration:

```bash
php artisan vendor:publish --provider="TimurTurdyev\SimpleSettings\Providers\SettingServiceProvider"
```

Run the migration:

```bash
php artisan migrate
```

## Usage

### Facade

```php
use TimurTurdyev\SimpleSettings\Facades\Setting;

Setting::set('site_name', 'My App');

$name = Setting::get('site_name');           // 'My App'
$name = Setting::get('missing', 'default'); // 'default'

Setting::has('site_name'); // true
Setting::remove('site_name');
Setting::all();       // Collection of all settings in current group
Setting::flushCache();
```

### Service container

```php
use TimurTurdyev\SimpleSettings\Contracts\SettingStorageInterface;

$settings = app(SettingStorageInterface::class);
$settings->set('site_name', 'My App');
```

## Groups

Settings are namespaced into groups. The default group is `global`.

```php
// forGroup() returns a new isolated instance
$email = Setting::forGroup('email');
$email->set('host', 'smtp.example.com');
$email->get('host'); // 'smtp.example.com'

Setting::get('host'); // null — different group

// group() mutates the current instance
Setting::group('email')->get('host');
```

## Type casting

Types are detected automatically and restored on read:

```php
Setting::set('count', 42);            // integer
Setting::set('price', 9.99);          // float
Setting::set('enabled', true);        // boolean
Setting::set('tags', ['a', 'b']);     // array  (stored as JSON)
Setting::set('key', null);            // null
```

## Bulk set

```php
Setting::set([
    'site_name' => 'My App',
    'site_url'  => 'https://example.com',
    'per_page'  => 15,
]);
```

Cache is flushed once after all values are written.

## Bypass cache

```php
$value = Setting::get('key', null, fresh: true);
$all   = Setting::all(fresh: true);
```

## Artisan commands

```bash
# Get a setting
php artisan setting:get site_name
php artisan setting:get host --group=email
php artisan setting:get host --group=email --fresh

# Set a setting (type is inferred automatically)
php artisan setting:set site_name "My App"
php artisan setting:set enabled true
php artisan setting:set per_page 15
php artisan setting:set tags '["a","b"]'
php artisan setting:set host smtp.example.com --group=email

# List settings
php artisan setting:list
php artisan setting:list --group=email

# Clear cache
php artisan setting:clear
php artisan setting:clear --group=email

# Delete a setting
php artisan setting:delete site_name
php artisan setting:delete host --group=email

# Delete all settings in a group
php artisan setting:delete --group=email
```

## Events

| Event | Fired when |
|-------|-----------|
| `SettingRetrieved` | `get()` is called |
| `SettingSaved` | `set()` writes a value |
| `SettingDeleted` | `remove()` deletes a specific key |

```php
use TimurTurdyev\SimpleSettings\Events\SettingSaved;
use Illuminate\Support\Facades\Event;

Event::listen(SettingSaved::class, function (SettingSaved $event) {
    // $event->key
    // $event->value
    // $event->group
});
```

## Validation

Add validation rules to `config/simple-settings.php`:

```php
'validation_rules' => [
    'email'   => 'email',
    'per_page' => 'integer|min:1|max:200',
    'enabled' => 'boolean',
],
```

`set()` will throw `InvalidArgumentException` if a rule fails.

## Configuration

```php
// config/simple-settings.php
return [
    'table_name'       => 'simple_settings',
    'path_cache_key'   => 'simple_settings',
    'validation_rules' => [],
];
```

## Database schema

```
simple_settings
├── id
├── group   string
├── name    string
├── val     text
├── type    char(20)
├── created_at
└── updated_at

UNIQUE (group, name)
```

## API reference

| Method | Description |
|--------|-------------|
| `get(string $key, mixed $default = null, bool $fresh = false)` | Get a setting value |
| `set(string\|array $key, mixed $val = null)` | Set one or multiple values |
| `has(string $key)` | Check if a key exists |
| `remove(?string $key = null)` | Delete a key, or all keys in the group if null |
| `all(bool $fresh = false)` | Get all settings in the group as a Collection |
| `flushCache()` | Clear the cache for the current group |
| `group(string $group)` | Switch group on the current instance |
| `forGroup(string $group)` | Return a new instance scoped to a group |
