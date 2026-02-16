<?php

namespace TimurTurdyev\SimpleSettings\Console\Commands;

use Illuminate\Console\Command;

class SettingClearCommand extends Command
{
    protected $signature = 'setting:clear {--g|group= : Clear cache for specific group}';

    protected $description = 'Clear settings cache';

    public function handle(): int
    {
        $group = $this->option('group');

        if ($group) {
            $setting = app(\TimurTurdyev\SimpleSettings\Contracts\SettingStorageInterface::class)
                ->forGroup($group);

            $setting->flushCache();

            $this->info("Cache for group [{$group}] has been cleared.");
        } else {
            $setting = app(\TimurTurdyev\SimpleSettings\Contracts\SettingStorageInterface::class);

            $groups = \TimurTurdyev\SimpleSettings\Models\SimpleSetting::query()
                ->distinct()
                ->pluck('group');

            foreach ($groups as $g) {
                $setting->forGroup($g)->flushCache();
            }

            $this->info('All settings cache has been cleared.');
        }

        return self::SUCCESS;
    }
}
