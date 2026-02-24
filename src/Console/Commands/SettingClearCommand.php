<?php

namespace TimurTurdyev\SimpleSettings\Console\Commands;

use TimurTurdyev\SimpleSettings\Models\SimpleSetting;

class SettingClearCommand extends BaseCommand
{
    protected $signature = 'setting:clear {--g|group= : Clear cache for specific group}';

    protected $description = 'Clear settings cache';

    public function handle(): int
    {
        $group = $this->option('group');

        if ($group) {
            $this->storage($group)->flushCache();
            $this->info("Cache for group [{$group}] has been cleared.");
        } else {
            SimpleSetting::query()->distinct()->pluck('group')
                ->each(fn($g) => $this->storage($g)->flushCache());

            $this->info('All settings cache has been cleared.');
        }

        return self::SUCCESS;
    }
}
