# Simple Settings для Laravel

<p align="center">
  <img src="art/banner.svg" alt="Simple Settings for Laravel" width="100%">
</p>

Лёгкий менеджер настроек для Laravel с поддержкой групп, кэшированием и автоматическим приведением типов.

[English](#english)

---

## Требования

- PHP 8.2+
- Laravel 12.x

## Установка

```bash
composer require timurturdyev/simple-settings
```

Опубликуйте конфиг и миграцию:

```bash
php artisan vendor:publish --provider="TimurTurdyev\SimpleSettings\Providers\SettingServiceProvider"
```

Запустите миграцию:

```bash
php artisan migrate
```

## Использование

### Через Facade

```php
use TimurTurdyev\SimpleSettings\Facades\Setting;

Setting::set('site_name', 'My App');

$name = Setting::get('site_name');              // 'My App'
$name = Setting::get('missing', 'default');    // 'default'

Setting::has('site_name'); // true
Setting::remove('site_name');   // удалить конкретный ключ
Setting::removeAll();           // удалить все настройки группы
Setting::all();        // Collection всех настроек текущей группы
Setting::flushCache();
```

### Через сервис-контейнер

```php
use TimurTurdyev\SimpleSettings\Contracts\SettingStorageInterface;

$settings = app(SettingStorageInterface::class);
$settings->set('site_name', 'My App');
```

## Группы

Настройки разделены по группам. Группа по умолчанию — `global`.

```php
// forGroup() — возвращает новый изолированный экземпляр
$email = Setting::forGroup('email');
$email->set('host', 'smtp.example.com');
$email->get('host'); // 'smtp.example.com'

Setting::get('host'); // null — другая группа

// group() — алиас для forGroup(), тоже возвращает новый экземпляр
Setting::group('email')->get('host');
```

## Типы данных

Типы определяются и восстанавливаются автоматически:

```php
Setting::set('count',   42);          // integer
Setting::set('price',   9.99);        // float
Setting::set('enabled', true);        // boolean
Setting::set('tags',    ['a', 'b']);  // array (хранится как JSON)
Setting::set('key',     null);        // null
```

## Массовая запись

```php
Setting::set([
    'site_name' => 'My App',
    'site_url'  => 'https://example.com',
    'per_page'  => 15,
]);
```

Кэш сбрасывается один раз после записи всех значений.

## Обход кэша

```php
$value = Setting::get('key', null, fresh: true);
$all   = Setting::all(fresh: true);
```

## Artisan-команды

```bash
# Получить настройку
php artisan setting:get site_name
php artisan setting:get host --group=email
php artisan setting:get host --group=email --fresh

# Установить настройку (тип определяется автоматически)
php artisan setting:set site_name "My App"
php artisan setting:set enabled true
php artisan setting:set per_page 15
php artisan setting:set tags '["a","b"]'
php artisan setting:set host smtp.example.com --group=email

# Список настроек
php artisan setting:list
php artisan setting:list --group=email

# Очистить кэш
php artisan setting:clear
php artisan setting:clear --group=email

# Удалить настройку
php artisan setting:delete site_name
php artisan setting:delete host --group=email

# Удалить все настройки группы (через removeAll)
php artisan setting:delete --group=email
```

## События

По умолчанию события **отключены**. Включить можно двумя способами:

**Через конфиг** (глобально для всего приложения):
```php
// config/simple-settings.php
'events' => true,
```

**Через метод** (точечно для конкретного вызова):
```php
Setting::withEvents()->set('key', 'value');
Setting::withEvents()->get('key');
Setting::withEvents()->remove('key');

// Отключить явно, даже если в конфиге включено:
Setting::withoutEvents()->set('key', 'value');

// Работает в связке с группами:
Setting::forGroup('email')->withEvents()->set('host', 'smtp.example.com');
```

`withEvents()` и `withoutEvents()` возвращают новый экземпляр — текущий не изменяется.

| Событие | Когда срабатывает |
|---------|------------------|
| `SettingRetrieved` | при вызове `get()` |
| `SettingSaved` | при записи через `set()` |
| `SettingDeleted` | при удалении конкретного ключа через `remove()` |

```php
use TimurTurdyev\SimpleSettings\Events\SettingSaved;
use Illuminate\Support\Facades\Event;

Event::listen(SettingSaved::class, function (SettingSaved $event) {
    // $event->key
    // $event->value
    // $event->group
});
```

## Валидация

Добавьте правила в `config/simple-settings.php`:

```php
'validation_rules' => [
    'email'    => 'email',
    'per_page' => 'integer|min:1|max:200',
    'enabled'  => 'boolean',
],
```

При нарушении правила `set()` выбрасывает `InvalidArgumentException`.

## Конфигурация

```php
// config/simple-settings.php
return [
    'table_name'        => 'simple_settings', // название таблицы
    'cache_key_prefix'  => 'simple_settings', // префикс ключей кэша
    'events'            => false,             // глобально включить события
    'validation_rules'  => [],                // правила валидации по ключу
];
```

## Лимиты значений

Колонка `val` создаётся как `TEXT` — на MySQL/MariaDB это **65 535 байт** (~64 KB), на PostgreSQL и SQLite ограничения нет. Для типовых настроек этого с большим запасом: список из 200 категорий-объектов влезет, массив из 10 000 коротких строк — тоже. Если упёрлись в лимит — это сигнал, что в одну настройку положили что-то «не то» (каталог товаров, лог, контент). Для таких данных нужна отдельная таблица или другое хранилище, не settings-таблица.

## Схема БД

```
simple_settings
├── group       string
├── name        string
├── val         text
├── type        char(20)
├── created_at
└── updated_at

PRIMARY KEY (group, name)
```

## Справочник API

| Метод | Описание |
|-------|----------|
| `get(string $key, mixed $default = null, bool $fresh = false)` | Получить значение настройки |
| `set(string\|array $key, mixed $val = null): void` | Установить одно или несколько значений |
| `has(string $key)` | Проверить существование ключа |
| `remove(string $key): int` | Удалить конкретный ключ |
| `removeAll(): int` | Удалить все настройки текущей группы |
| `all(bool $fresh = false)` | Получить все настройки группы как Collection |
| `list(?string $group = null)` | Получить все записи (raw) с фильтром по группе |
| `groups(): array` | Получить список всех групп |
| `flushCache()` | Сбросить кэш текущей группы |
| `group(string $group)` | Алиас для `forGroup()` — новый экземпляр |
| `forGroup(string $group)` | Вернуть новый экземпляр для указанной группы |
| `withEvents()` | Вернуть новый экземпляр с включёнными событиями |
| `withoutEvents()` | Вернуть новый экземпляр с отключёнными событиями |

## Преимущества

Простой key-value для настроек приложения. Не нужно описывать PHP-класс под каждую группу настроек и не нужна миграция на каждый новый ключ — всё хранится в одной таблице `simple_settings`, дубликаты исключены составным первичным ключом `(group, name)`. Тип значения (`string`, `int`, `float`, `bool`, `array`, `null`) сохраняется и восстанавливается автоматически. Из зависимостей — только `illuminate/database` и `illuminate/support`.

---

## English

Lightweight settings manager for Laravel with group namespacing, caching, and automatic type casting.

**Requirements:** PHP 8.2+, Laravel 12.x

**Install:**
```bash
composer require timurturdyev/simple-settings
php artisan vendor:publish --provider="TimurTurdyev\SimpleSettings\Providers\SettingServiceProvider"
php artisan migrate
```

**Basic usage:**
```php
use TimurTurdyev\SimpleSettings\Facades\Setting;

Setting::set('key', 'value');
Setting::get('key');                        // 'value'
Setting::get('missing', 'default');        // 'default'
Setting::set(['key1' => 1, 'key2' => 2]); // bulk
Setting::forGroup('email')->set('host', 'smtp.example.com');
Setting::all(fresh: true);                 // bypass cache
```

Types (`integer`, `float`, `boolean`, `array`, `null`) are detected and restored automatically.

**Configuration keys:** `table_name`, `cache_key_prefix`, `events`, `validation_rules`.

**Value size limits:** the `val` column is `TEXT` — 64 KB on MySQL/MariaDB, unlimited on PostgreSQL/SQLite. Typical settings fit with plenty of room (200 catalog-like objects, 10 000 short strings). Hitting the cap usually means the data belongs in its own table, not in settings.

### Highlights

Plain key-value storage for app settings. No PHP class per settings group, no migration per new key — everything lives in a single `simple_settings` table, and the composite primary key `(group, name)` rules out duplicates. Value types (`string`, `int`, `float`, `bool`, `array`, `null`) are stored and restored automatically. The only dependencies are `illuminate/database` and `illuminate/support`.

For full documentation see the Russian section above.
