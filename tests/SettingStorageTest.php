<?php

namespace TimurTurdyev\SimpleSettings\Tests;

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
}
