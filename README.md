# Simple-Settings для Laravel

Легкий менеджер настроек для Laravel с поддержкой групп, кэшированием и автоматическим преобразованием типов.

## 📦 Установка

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

## 🚀 Использование

```php
use TimurTurdyev\SimpleSettings\SettingStorage;

// Глобальная группа
$settings = new SettingStorage(/* $group = 'global' */);

$settings->set('key', 'value');

// С указанием группы
app(SettingStorage::class)
    ->group('mail')
    ->set('driver', 'smtp');

```

Получение значений

```php

// Получить все настройки группы
$settings = setting()->group('mail')->all();

// Получить конкретный параметр
$driver = setting()->group('mail')->get('driver');

// Получить конкретный параметр с установкой значения по умолчанию
$driver = setting()->group('mail')->get('driver', 'smtp');

```

## 🌟 Особенности

Поддерживаемые типы данных

```php
setting()->group('app')->set([
    'int' => 42,          // integer
    'bool' => true,       // boolean
    'array' => ['a', 'b'],// array
    'null' => null,       // null
    'object' => new stdClass() // object
]);
```

Кэширование

* Автоматическое кэширование на уровне группы
* Сброс кэша при изменении настроек
* Принудительное обновление:

```php
$freshSettings = setting()->group('mail')->all(fresh: true);
```

## ⚙️ Конфигурация

```php
return [
    'table_name' => 'simple_settings',   // Название таблицы
    'path_cache_key' => 'simple_settings', // Префикс ключей кэша
];
```

## 🛠 API

| Метод	              | Описание                         |
|---------------------|----------------------------------|
| group(string $name) | 	Выбор группы настроек           |
| get(string $key)	   | Получить значение параметра      |
| set($key, $value)	  | Установить значение              |
| all()               | Все настройки группы             |
| has(string $key)    | Проверка существования параметра |
| remove()            | Удаление параметра/всей группы   |
| flushCache()        | Очистка кэша группы              |

## 📖 Структура БД

```php 

Schema::create(config('simple-settings.table_name', 'simple_settings'), function (Blueprint $table) {
    $table->id();
    $table->string('group');    // Группа настроек
    $table->string('name');     // Ключ параметра
    $table->text('val');        // Значение (сериализованное)
    $table->char('type', 20);   // Тип данных (string, array и т.д.)
    $table->timestamps();
    
    $table->unique(['group', 'name']);
});

```

## 📄 Лицензия

MIT License
