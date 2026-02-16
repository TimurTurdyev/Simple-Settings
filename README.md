# Simple-Settings для Laravel

Легкий менеджер настроек для Laravel с поддержкой групп, кэшированием и автоматическим преобразованием типов.

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

// Получить настройку
$value = Setting::get('key', 'default');

// Установить настройку
Setting::set('key', 'value');

// Проверить существование
if (Setting::has('key')) {
    // ...
}

// Удалить настройку
Setting::remove('key');

// Получить все настройки
$all = Setting::all();

// Очистить кэш
Setting::flushCache();
```

### Через сервис-контейнер

```php
use TimurTurdyev\SimpleSettings\Contracts\SettingStorageInterface;

$settings = app(SettingStorageInterface::class);
$settings->set('key', 'value');
```

### Использование групп

```php
// Через forGroup() - возвращает новый экземпляр
$emailSettings = Setting::forGroup('email');
$smtpHost = $emailSettings->get('smtp_host');

// Через group() - модифицирует текущий экземпляр
Setting::group('email')->get('smtp_host');
```

### Поддерживаемые типы данных

Пакет автоматически определяет и приводит типы:

```php
Setting::set('count', 42);           // integer
Setting::set('enabled', true);        // boolean
Setting::set('options', ['a', 'b']);  // array (сохраняется как JSON)
```

### Массив настроек

```php
Setting::set([
    'site_name' => 'My Site',
    'site_url' => 'https://example.com',
]);
```

### Кэширование

Настройки кэшируются по умолчанию. Чтобы получить значение напрямую из БД:

```php
$value = Setting::get('key', null, true); // fresh из БД
```

## Artisan команды

```bash
# Получить настройку
php artisan setting:get key --group=global

# Установить настройку
php artisan setting:set key value --group=global

# Список всех настроек
php artisan setting:list
php artisan setting:list --group=email

# Очистить кэш
php artisan setting:clear
php artisan setting:clear --group=email

# Удалить настройку
php artisan setting:delete key --group=global
```

## События

- `SettingRetrieved` - при получении настройки
- `SettingSaved` - при сохранении настройки
- `SettingDeleted` - при удалении настройки

```php
use TimurTurdyev\SimpleSettings\Events\SettingSaved;

Event::listen(SettingSaved::class, function ($event) {
    // $event->key
    // $event->value
    // $event->group
});
```

## Валидация

Добавьте правила валидации в `config/simple-settings.php`:

```php
'validation_rules' => [
    'email' => 'email',
    'count' => 'integer|min:0',
    'enabled' => 'boolean',
],
```

## Конфигурация

```php
// config/simple-settings.php
return [
    'table_name' => 'simple_settings',      // Название таблицы
    'path_cache_key' => 'simple_settings', // Префикс ключей кэша
    'validation_rules' => [],               // Правила валидации
];
```

## API

| Метод                  | Описание                           |
|------------------------|------------------------------------|
| `group(string $name)` | Выбор группы настроек              |
| `forGroup(string $name)` | Создать экземпляр для группы    |
| `get(string $key)`     | Получить значение параметра       |
| `set($key, $value)`   | Установить значение                |
| `all()`                | Все настройки группы               |
| `has(string $key)`     | Проверка существования параметра   |
| `remove()`             | Удаление параметра/всей группы    |
| `flushCache()`         | Очистка кэша группы                |

## Структура БД

```php 
Schema::create(config('simple-settings.table_name', 'simple_settings'), function (Blueprint $table) {
    $table->id();
    $table->string('group');    // Группа настроек
    $table->string('name');     // Ключ параметра
    $table->text('val');        // Значение (сериализованное)
    $table->char('type', 20);  // Тип данных (string, array и т.д.)
    $table->timestamps();
    
    $table->unique(['group', 'name']);
});
```
