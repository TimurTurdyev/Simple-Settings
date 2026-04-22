<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleSettings\Tests;

use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Support\Facades\Schema;
use TimurTurdyev\SimpleSettings\Models\SimpleSetting;
use TimurTurdyev\SimpleSettings\Models\SimpleSettingChange;
use TimurTurdyev\SimpleSettings\SettingStorage;

class AuditLogTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('simple-settings.events', true);
        $app['config']->set('simple-settings.audit.enabled', true);
    }

    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();

        if (!Schema::hasTable('users')) {
            Schema::create('users', function ($table) {
                $table->id();
                $table->string('name')->nullable();
            });
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        SimpleSetting::query()->delete();
        SimpleSettingChange::query()->delete();
    }

    public function test_created_event_is_logged(): void
    {
        $storage = new SettingStorage('test');

        $storage->set('site_name', 'My App');

        $changes = SimpleSettingChange::query()->get();
        $this->assertCount(1, $changes);

        $change = $changes->first();
        $this->assertEquals('test', $change->group);
        $this->assertEquals('site_name', $change->name);
        $this->assertEquals('created', $change->event);
        $this->assertNull($change->old_payload);
        $this->assertEquals('My App', $change->new_payload);
    }

    public function test_updated_event_is_logged_with_old_value(): void
    {
        $storage = new SettingStorage('test');
        $storage->set('per_page', 10);
        SimpleSettingChange::query()->delete();

        $storage->set('per_page', 25);

        $change = SimpleSettingChange::query()->first();
        $this->assertEquals('updated', $change->event);
        $this->assertEquals(10, $change->old_payload);
        $this->assertEquals(25, $change->new_payload);
    }

    public function test_deleted_event_is_logged_with_old_value(): void
    {
        $storage = new SettingStorage('test');
        $storage->set('enabled', true);
        SimpleSettingChange::query()->delete();

        $storage->remove('enabled');

        $change = SimpleSettingChange::query()->first();
        $this->assertEquals('deleted', $change->event);
        $this->assertEquals(true, $change->old_payload);
        $this->assertNull($change->new_payload);
    }

    public function test_set_null_over_existing_null_is_updated_not_created(): void
    {
        $storage = new SettingStorage('test');
        $storage->set('maybe', null);
        SimpleSettingChange::query()->delete();

        $storage->set('maybe', null);

        $change = SimpleSettingChange::query()->first();
        $this->assertEquals('updated', $change->event);
        $this->assertNull($change->old_payload);
        $this->assertNull($change->new_payload);
    }

    public function test_array_values_round_trip_through_audit(): void
    {
        $storage = new SettingStorage('test');

        $storage->set('tags', ['alpha', 'beta']);

        $change = SimpleSettingChange::query()->first();
        $this->assertEquals('created', $change->event);
        $this->assertEquals(['alpha', 'beta'], $change->new_payload);
    }

    public function test_causer_captured_from_auth(): void
    {
        $user = new class extends AuthUser {
            protected $table = 'users';
            protected $guarded = [];
        };
        $user->forceFill(['id' => 42, 'name' => 'Alice']);
        $user->exists = true;

        $this->actingAs($user);

        (new SettingStorage('test'))->set('key', 'value');

        $change = SimpleSettingChange::query()->first();
        $this->assertEquals($user::class, $change->causer_type);
        $this->assertEquals(42, $change->causer_id);
    }

    public function test_causer_is_null_without_auth(): void
    {
        (new SettingStorage('test'))->set('key', 'value');

        $change = SimpleSettingChange::query()->first();
        $this->assertNull($change->causer_type);
        $this->assertNull($change->causer_id);
    }

    public function test_without_events_bypasses_audit(): void
    {
        $storage = (new SettingStorage('test'))->withoutEvents();

        $storage->set('key', 'value');
        $storage->remove('key');

        $this->assertEquals(0, SimpleSettingChange::query()->count());
    }

    public function test_for_setting_scope_filters_changes(): void
    {
        $storage = new SettingStorage('test');
        $storage->set('keep', 'a');
        $storage->set('drop', 'b');

        $results = SimpleSettingChange::query()
            ->forSetting('test', 'keep')
            ->get();

        $this->assertCount(1, $results);
        $this->assertEquals('keep', $results->first()->name);
    }

    public function test_event_scope_filters_changes(): void
    {
        $storage = new SettingStorage('test');
        $storage->set('a', 1);
        $storage->set('a', 2);
        $storage->remove('a');

        $this->assertEquals(1, SimpleSettingChange::query()->event('created')->count());
        $this->assertEquals(1, SimpleSettingChange::query()->event('updated')->count());
        $this->assertEquals(1, SimpleSettingChange::query()->event('deleted')->count());
    }
}
