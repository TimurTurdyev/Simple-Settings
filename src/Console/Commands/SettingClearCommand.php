<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleSettings\Console\Commands;

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
            $storage = $this->storage();
            foreach ($storage->groups() as $g) {
                $storage->forGroup($g)->flushCache();
            }

            $this->info('All settings cache has been cleared.');
        }

        return self::SUCCESS;
    }
}
