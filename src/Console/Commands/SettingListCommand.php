<?php

namespace TimurTurdyev\SimpleSettings\Console\Commands;

use TimurTurdyev\SimpleSettings\Models\SimpleSetting;

class SettingListCommand extends BaseCommand
{
    protected $signature = 'setting:list {--g|group= : Filter by group}';

    protected $description = 'List all settings';

    public function handle(): int
    {
        $group = $this->option('group');

        $query = SimpleSetting::query()
            ->when($group, fn($q) => $q->where('group', $group));

        $settings = $query->get();

        if ($settings->isEmpty()) {
            $this->info('No settings found.');

            return self::SUCCESS;
        }

        $this->table(
            ['Group', 'Key', 'Type', 'Value'],
            $settings->map(fn($s) => [
                'group' => $s->group,
                'key' => $s->name,
                'type' => $s->type,
                'value' => $s->val,
            ])
        );

        return self::SUCCESS;
    }
}
