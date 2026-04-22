<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleSettings\Tests;

use Illuminate\Support\Facades\Event;
use TimurTurdyev\SimpleSettings\Events\SettingDeleted;
use TimurTurdyev\SimpleSettings\Events\SettingRetrieved;
use TimurTurdyev\SimpleSettings\Events\SettingSaved;
use TimurTurdyev\SimpleSettings\Events\SettingsFlushed;
use TimurTurdyev\SimpleSettings\Models\SimpleSetting;
use TimurTurdyev\SimpleSettings\SettingStorage;

class SettingStorageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        SimpleSetting::query()->delete();
    }

    protected function tearDown(): void
    {
        SimpleSetting::query()->delete();
        
        parent::tearDown();
    }

    public function test_can_set_and_get_string_value(): void
    {
        $storage = new SettingStorage('test');
        
        $storage->set('name', 'John Doe');
        
        $this->assertEquals('John Doe', $storage->get('name'));
    }

    public function test_can_set_and_get_integer_value(): void
    {
        $storage = new SettingStorage('test');
        
        $storage->set('count', 42);
        
        $this->assertEquals(42, $storage->get('count'));
    }

    public function test_can_set_and_get_boolean_value(): void
    {
        $storage = new SettingStorage('test');
        
        $storage->set('enabled', true);
        
        $this->assertTrue($storage->get('enabled'));
        
        $storage->set('disabled', false);
        
        $this->assertFalse($storage->get('disabled'));
    }

    public function test_can_set_and_get_array_value(): void
    {
        $storage = new SettingStorage('test');
        
        $storage->set('options', ['a', 'b', 'c']);
        
        $this->assertEquals(['a', 'b', 'c'], $storage->get('options'));
    }

    public function test_can_set_multiple_values_at_once(): void
    {
        $storage = new SettingStorage('test');
        
        $storage->set([
            'name' => 'John',
            'age' => 30,
        ]);
        
        $this->assertEquals('John', $storage->get('name'));
        $this->assertEquals(30, $storage->get('age'));
    }

    public function test_returns_default_value_when_key_not_found(): void
    {
        $storage = new SettingStorage('test');
        
        $this->assertEquals('default', $storage->get('nonexistent', 'default'));
    }

    public function test_can_check_if_key_exists(): void
    {
        $storage = new SettingStorage('test');
        
        $storage->set('exists', 'value');
        
        $this->assertTrue($storage->has('exists'));
        $this->assertFalse($storage->has('nonexistent'));
    }

    public function test_can_remove_a_setting(): void
    {
        $storage = new SettingStorage('test');
        
        $storage->set('to_remove', 'value');
        $this->assertTrue($storage->has('to_remove'));
        
        $storage->remove('to_remove');
        $this->assertFalse($storage->has('to_remove'));
    }

    public function test_can_remove_all_settings_in_group(): void
    {
        $storage = new SettingStorage('test');

        $storage->set('key1', 'value1');
        $storage->set('key2', 'value2');
        $this->assertCount(2, $storage->all());

        $deleted = $storage->removeAll();

        $this->assertEquals(2, $deleted);
        $this->assertCount(0, $storage->all(true));
    }

    public function test_remove_all_does_not_affect_other_groups(): void
    {
        $storage = new SettingStorage('test');
        $other = new SettingStorage('other');

        $storage->set('key', 'value');
        $other->set('key', 'value');

        $storage->removeAll();

        $this->assertCount(0, $storage->all(true));
        $this->assertTrue($other->has('key'));
    }

    public function test_can_get_all_settings_in_group(): void
    {
        $storage = new SettingStorage('test');
        
        $storage->set('key1', 'value1');
        $storage->set('key2', 'value2');
        
        $all = $storage->all();
        
        $this->assertEquals('value1', $all->get('key1'));
        $this->assertEquals('value2', $all->get('key2'));
    }

    public function test_for_group_creates_new_instance_with_different_group(): void
    {
        $storage = new SettingStorage('global');
        $storage->set('key', 'global_value');
        
        $emailStorage = $storage->forGroup('email');
        $emailStorage->set('key', 'email_value');
        
        $this->assertEquals('global_value', $storage->get('key'));
        $this->assertEquals('email_value', $emailStorage->get('key'));
    }

    public function test_get_fresh_bypasses_cache(): void
    {
        $storage = new SettingStorage('test');
        
        $storage->set('key', 'value1');
        $this->assertEquals('value1', $storage->get('key'));
        
        SimpleSetting::where('name', 'key')->update(['val' => 'value2']);
        
        $this->assertEquals('value1', $storage->get('key'));
        $this->assertEquals('value2', $storage->get('key', null, true));
    }

    public function test_set_stores_timestamps_on_create(): void
    {
        $storage = new SettingStorage('test');

        $before = now()->startOfSecond();
        $storage->set('key', 'value');
        $after = now()->startOfSecond()->addSecond();

        $record = SimpleSetting::query()
            ->where('group', 'test')
            ->where('name', 'key')
            ->first();

        $this->assertTrue($record->created_at->between($before, $after));
        $this->assertTrue($record->updated_at->between($before, $after));
    }

    public function test_set_updates_updated_at_but_preserves_created_at_on_overwrite(): void
    {
        $storage = new SettingStorage('test');
        $storage->set('key', 'original');

        $createdAt = SimpleSetting::query()
            ->where('group', 'test')
            ->where('name', 'key')
            ->first()
            ->created_at;

        $this->travel(1)->minutes();

        $storage->set('key', 'updated');

        $record = SimpleSetting::query()
            ->where('group', 'test')
            ->where('name', 'key')
            ->first();

        $this->assertTrue($record->created_at->equalTo($createdAt));
        $this->assertTrue($record->updated_at->gt($createdAt));
    }

    public function test_flush_cache_clears_cached_settings(): void
    {
        $storage = new SettingStorage('test');

        $storage->set('key', 'value');
        $this->assertEquals('value', $storage->get('key'));

        $storage->flushCache();

        SimpleSetting::where('name', 'key')->update(['val' => 'new_value']);

        $this->assertEquals('new_value', $storage->get('key'));
    }

    public function test_events_are_disabled_by_default(): void
    {
        Event::fake();

        $storage = new SettingStorage('test');
        $storage->set('key', 'value');
        $storage->get('key');
        $storage->remove('key');

        Event::assertNotDispatched(SettingSaved::class);
        Event::assertNotDispatched(SettingRetrieved::class);
        Event::assertNotDispatched(SettingDeleted::class);
    }

    public function test_with_events_enables_event_dispatching(): void
    {
        Event::fake();

        $storage = (new SettingStorage('test'))->withEvents();
        $storage->set('key', 'value');
        $storage->get('key');
        $storage->remove('key');

        Event::assertDispatched(SettingSaved::class, fn($e) => $e->key === 'key' && $e->group === 'test');
        Event::assertDispatched(SettingRetrieved::class, fn($e) => $e->key === 'key' && $e->group === 'test');
        Event::assertDispatched(SettingDeleted::class, fn($e) => $e->key === 'key' && $e->group === 'test');
    }

    public function test_without_events_disables_event_dispatching(): void
    {
        Event::fake();

        $storage = (new SettingStorage('test', true))->withoutEvents();
        $storage->set('key', 'value');
        $storage->get('key');
        $storage->remove('key');

        Event::assertNotDispatched(SettingSaved::class);
        Event::assertNotDispatched(SettingRetrieved::class);
        Event::assertNotDispatched(SettingDeleted::class);
    }

    public function test_events_enabled_via_config(): void
    {
        Event::fake();

        config(['simple-settings.events' => true]);

        $storage = new SettingStorage('test');
        $storage->set('key', 'value');

        Event::assertDispatched(SettingSaved::class);
    }

    public function test_group_returns_new_instance_without_mutating_original(): void
    {
        $storage = new SettingStorage('global');
        $storage->set('key', 'global_value');

        $emailStorage = $storage->group('email');
        $emailStorage->set('key', 'email_value');

        $this->assertEquals('global_value', $storage->get('key'));
        $this->assertEquals('email_value', $emailStorage->get('key'));
    }

    public function test_list_returns_all_settings_across_groups(): void
    {
        $global = new SettingStorage('global');
        $email = new SettingStorage('email');

        $global->set('app_name', 'MyApp');
        $email->set('host', 'smtp.example.com');

        $all = $global->list();

        $this->assertCount(2, $all);
    }

    public function test_list_filters_by_group(): void
    {
        $global = new SettingStorage('global');
        $email = new SettingStorage('email');

        $global->set('app_name', 'MyApp');
        $email->set('host', 'smtp.example.com');

        $filtered = $global->list('email');

        $this->assertCount(1, $filtered);
        $this->assertEquals('host', $filtered->first()->name);
    }

    public function test_groups_returns_distinct_group_names(): void
    {
        $global = new SettingStorage('global');
        $email = new SettingStorage('email');

        $global->set('key', 'value');
        $email->set('key', 'value');

        $groups = $global->groups();

        $this->assertCount(2, $groups);
        $this->assertContains('global', $groups);
        $this->assertContains('email', $groups);
    }

    public function test_set_returns_void(): void
    {
        $storage = new SettingStorage('test');

        $result = $storage->set('key', 'value');

        $this->assertNull($result);
    }

    public function test_float_value_stored_as_float_type(): void
    {
        $storage = new SettingStorage('test');

        $storage->set('rate', 3.14);

        $record = SimpleSetting::query()
            ->where('group', 'test')
            ->where('name', 'rate')
            ->first();

        $this->assertEquals('float', $record->type);
        $this->assertEquals(3.14, $storage->get('rate'));
    }

    public function test_legacy_double_type_is_read_correctly(): void
    {
        SimpleSetting::create([
            'group' => 'test',
            'name' => 'old_rate',
            'val' => '2.71',
            'type' => 'double',
        ]);

        $storage = new SettingStorage('test');

        $this->assertEquals(2.71, $storage->get('old_rate'));
        $this->assertIsFloat($storage->get('old_rate'));
    }

    public function test_for_group_inherits_events_state(): void
    {
        Event::fake();

        $storage = (new SettingStorage('global'))->withEvents();
        $emailStorage = $storage->forGroup('email');
        $emailStorage->set('host', 'smtp.example.com');

        Event::assertDispatched(SettingSaved::class, fn($e) => $e->group === 'email');
    }

    public function test_with_events_returns_new_instance_without_mutating_original(): void
    {
        Event::fake();

        $original = new SettingStorage('test');
        $withEvents = $original->withEvents();

        $this->assertNotSame($original, $withEvents);

        $original->set('key', 'value');
        Event::assertNotDispatched(SettingSaved::class);

        $withEvents->set('key', 'value');
        Event::assertDispatched(SettingSaved::class);
    }

    public function test_without_events_returns_new_instance_without_mutating_original(): void
    {
        Event::fake();

        $original = (new SettingStorage('test'))->withEvents();
        $withoutEvents = $original->withoutEvents();

        $this->assertNotSame($original, $withoutEvents);

        $withoutEvents->set('key', 'value');
        Event::assertNotDispatched(SettingSaved::class);

        $original->set('key2', 'value');
        Event::assertDispatched(SettingSaved::class);
    }

    public function test_null_value_round_trip(): void
    {
        $storage = new SettingStorage('test');

        $storage->set('nothing', null);

        $this->assertNull($storage->get('nothing'));

        $record = SimpleSetting::query()
            ->where('group', 'test')
            ->where('name', 'nothing')
            ->first();

        $this->assertEquals('null', $record->type);
    }

    public function test_has_returns_true_for_null_value(): void
    {
        $storage = new SettingStorage('test');

        $storage->set('nothing', null);

        $this->assertTrue($storage->has('nothing'));
        $this->assertFalse($storage->has('never_set'));
    }

    public function test_validation_passes_when_value_is_valid(): void
    {
        config(['simple-settings.validation_rules' => [
            'per_page' => 'integer|min:1|max:200',
        ]]);

        $storage = new SettingStorage('test');

        $storage->set('per_page', 50);

        $this->assertEquals(50, $storage->get('per_page'));
    }

    public function test_validation_throws_when_value_is_invalid(): void
    {
        config(['simple-settings.validation_rules' => [
            'per_page' => 'integer|min:1|max:200',
        ]]);

        $storage = new SettingStorage('test');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/per_page/');

        $storage->set('per_page', 999);
    }

    public function test_bulk_set_aborts_on_validation_failure(): void
    {
        config(['simple-settings.validation_rules' => [
            'per_page' => 'integer|min:1|max:200',
        ]]);

        $storage = new SettingStorage('test');

        try {
            $storage->set([
                'app_name' => 'Valid',
                'per_page' => 999,
                'site_url' => 'never_reached',
            ]);
            $this->fail('Expected InvalidArgumentException was not thrown.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('per_page', $e->getMessage());
        }

        $this->assertEquals('Valid', $storage->get('app_name', null, true));
        $this->assertNull($storage->get('per_page', null, true));
        $this->assertNull($storage->get('site_url', null, true));
    }

    public function test_can_store_realistic_settings_payload(): void
    {
        $storage = new SettingStorage('test');

        $catalog = array_fill(0, 200, [
            'id' => 12345,
            'name' => 'Категория детских товаров',
            'slug' => 'detskie-tovary',
            'active' => true,
        ]);

        $storage->set('featured_categories', $catalog);

        $this->assertEquals($catalog, $storage->get('featured_categories', null, true));
    }

    public function test_cache_key_prefix_used_when_set(): void
    {
        config(['simple-settings.cache_key_prefix' => 'custom_prefix']);

        $storage = new SettingStorage('global');

        $this->assertEquals('custom_prefix', $this->readCacheKey($storage));
    }

    public function test_default_cache_key_used_when_unset(): void
    {
        config(['simple-settings.cache_key_prefix' => null]);

        $storage = new SettingStorage('global');

        $this->assertEquals('simple_settings', $this->readCacheKey($storage));
    }

    private function readCacheKey(SettingStorage $storage): string
    {
        $reflection = new \ReflectionClass($storage);
        $property = $reflection->getProperty('cacheKey');
        $property->setAccessible(true);

        return $property->getValue($storage);
    }

    public function test_settings_flushed_event_is_dispatched_on_remove_all(): void
    {
        Event::fake();

        $storage = (new SettingStorage('test'))->withEvents();
        $storage->set('a', 1);
        $storage->removeAll();

        Event::assertDispatched(
            SettingsFlushed::class,
            fn($e) => $e->group === 'test'
        );
    }

    public function test_settings_flushed_event_is_not_dispatched_when_events_disabled(): void
    {
        Event::fake();

        (new SettingStorage('test'))->removeAll();

        Event::assertNotDispatched(SettingsFlushed::class);
    }
}
