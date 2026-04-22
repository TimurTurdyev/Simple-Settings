<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleSettings\Tests\Console;

use TimurTurdyev\SimpleSettings\Models\SimpleSetting;
use TimurTurdyev\SimpleSettings\SettingStorage;
use TimurTurdyev\SimpleSettings\Tests\TestCase;

class SettingExportImportTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();

        SimpleSetting::query()->delete();
        $this->tmp = tempnam(sys_get_temp_dir(), 'simple-settings-export-');
    }

    protected function tearDown(): void
    {
        if (is_file($this->tmp)) {
            unlink($this->tmp);
        }

        SimpleSetting::query()->delete();

        parent::tearDown();
    }

    public function test_export_writes_all_settings_to_file(): void
    {
        (new SettingStorage('global'))->set('app_name', 'My App');
        (new SettingStorage('email'))->set('host', 'smtp.example.com');

        $this->artisan('setting:export', ['file' => $this->tmp])
            ->expectsOutputToContain('Exported 2 setting(s)')
            ->assertSuccessful();

        $rows = json_decode((string) file_get_contents($this->tmp), true);
        $this->assertCount(2, $rows);
        $names = array_column($rows, 'name');
        $this->assertContains('app_name', $names);
        $this->assertContains('host', $names);
    }

    public function test_export_filters_by_group(): void
    {
        (new SettingStorage('global'))->set('app_name', 'My App');
        (new SettingStorage('email'))->set('host', 'smtp.example.com');

        $this->artisan('setting:export', ['file' => $this->tmp, '--group' => 'email'])
            ->assertSuccessful();

        $rows = json_decode((string) file_get_contents($this->tmp), true);
        $this->assertCount(1, $rows);
        $this->assertEquals('email', $rows[0]['group']);
    }

    public function test_round_trip_preserves_typed_values(): void
    {
        $storage = new SettingStorage('global');
        $storage->set([
            'site_name' => 'Acme',
            'per_page' => 25,
            'rate' => 3.14,
            'enabled' => true,
            'tags' => ['a', 'b'],
            'maybe' => null,
        ]);

        $this->artisan('setting:export', ['file' => $this->tmp])->assertSuccessful();

        SimpleSetting::query()->delete();
        $this->assertEquals(0, SimpleSetting::query()->count());

        $this->artisan('setting:import', ['file' => $this->tmp])->assertSuccessful();

        $restored = new SettingStorage('global');
        $this->assertSame('Acme', $restored->get('site_name'));
        $this->assertSame(25, $restored->get('per_page'));
        $this->assertSame(3.14, $restored->get('rate'));
        $this->assertSame(true, $restored->get('enabled'));
        $this->assertSame(['a', 'b'], $restored->get('tags'));
        $this->assertNull($restored->get('maybe'));
    }

    public function test_import_merges_by_default(): void
    {
        $storage = new SettingStorage('global');
        $storage->set('keep_me', 'original');

        file_put_contents($this->tmp, json_encode([
            ['group' => 'global', 'name' => 'new_key', 'type' => 'string', 'val' => 'new_value'],
        ]));

        $this->artisan('setting:import', ['file' => $this->tmp])->assertSuccessful();

        $this->assertSame('original', $storage->get('keep_me', null, true));
        $this->assertSame('new_value', $storage->get('new_key', null, true));
    }

    public function test_import_replace_wipes_existing_in_affected_groups(): void
    {
        $storage = new SettingStorage('global');
        $storage->set('to_be_dropped', 'gone');

        file_put_contents($this->tmp, json_encode([
            ['group' => 'global', 'name' => 'fresh', 'type' => 'string', 'val' => 'value'],
        ]));

        $this->artisan('setting:import', ['file' => $this->tmp, '--replace' => true])
            ->expectsConfirmation('Replace all existing settings in groups [global]?', 'yes')
            ->assertSuccessful();

        $fresh = new SettingStorage('global');
        $this->assertNull($fresh->get('to_be_dropped'));
        $this->assertSame('value', $fresh->get('fresh'));
    }

    public function test_import_fails_on_invalid_json(): void
    {
        file_put_contents($this->tmp, '{not valid json');

        $this->artisan('setting:import', ['file' => $this->tmp])
            ->expectsOutputToContain('Invalid JSON')
            ->assertFailed();
    }

    public function test_import_fails_on_bad_structure(): void
    {
        file_put_contents($this->tmp, json_encode([
            ['group' => 'global', 'name' => 'broken'],
        ]));

        $this->artisan('setting:import', ['file' => $this->tmp])
            ->expectsOutputToContain('Invalid entry at index 0')
            ->assertFailed();
    }

    public function test_import_filtered_by_group(): void
    {
        file_put_contents($this->tmp, json_encode([
            ['group' => 'global', 'name' => 'a', 'type' => 'string', 'val' => 'A'],
            ['group' => 'email', 'name' => 'b', 'type' => 'string', 'val' => 'B'],
        ]));

        $this->artisan('setting:import', ['file' => $this->tmp, '--group' => 'email'])
            ->assertSuccessful();

        $this->assertNull((new SettingStorage('global'))->get('a'));
        $this->assertSame('B', (new SettingStorage('email'))->get('b'));
    }
}
