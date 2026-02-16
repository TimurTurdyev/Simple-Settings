<?php

namespace TimurTurdyev\SimpleSettings\Tests;

use TimurTurdyev\SimpleSettings\Models\SimpleSetting;

class SimpleSettingModelTest extends TestCase
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

    public function test_model_casts_value_correctly(): void
    {
        $setting = SimpleSetting::create([
            'group' => 'test',
            'name' => 'count',
            'val' => '42',
            'type' => 'integer',
        ]);
        
        $this->assertEquals(42, $setting->value);
    }

    public function test_model_casts_boolean_correctly(): void
    {
        $setting = SimpleSetting::create([
            'group' => 'test',
            'name' => 'enabled',
            'val' => '1',
            'type' => 'boolean',
        ]);
        
        $this->assertTrue($setting->value);
    }

    public function test_model_casts_array_correctly(): void
    {
        $setting = SimpleSetting::create([
            'group' => 'test',
            'name' => 'options',
            'val' => json_encode(['a', 'b']),
            'type' => 'array',
        ]);
        
        $this->assertEquals(['a', 'b'], $setting->value);
    }

    public function test_model_casts_null_correctly(): void
    {
        $setting = SimpleSetting::create([
            'group' => 'test',
            'name' => 'empty',
            'val' => '',
            'type' => 'null',
        ]);
        
        $this->assertNull($setting->value);
    }

    public function test_model_scope_filters_by_group(): void
    {
        SimpleSetting::create([
            'group' => 'email',
            'name' => 'smtp_host',
            'val' => 'smtp.example.com',
            'type' => 'string',
        ]);
        
        SimpleSetting::create([
            'group' => 'app',
            'name' => 'name',
            'val' => 'My App',
            'type' => 'string',
        ]);
        
        $emailSettings = SimpleSetting::query()->group('email')->get();
        
        $this->assertEquals(1, $emailSettings->count());
        $this->assertEquals('smtp_host', $emailSettings->first()->name);
    }
}
