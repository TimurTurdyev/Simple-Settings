<?php

namespace TimurTurdyev\SimpleSettings\Console\Commands;

use Illuminate\Console\Command;

class SettingDeleteCommand extends Command
{
    protected $signature = 'setting:delete {key? : The setting key to delete (leave empty to delete all in group)} {--g|group=global : The setting group}';

    protected $description = 'Delete a setting';

    public function handle(): int
    {
        $key = $this->argument('key');
        $group = $this->option('group');

        $setting = app(\TimurTurdyev\SimpleSettings\Contracts\SettingStorageInterface::class)
            ->forGroup($group);

        $deleted = $setting->remove($key);

        if ($deleted > 0) {
            $keyDisplay = $key ?? "all settings in group [{$group}]";
            $this->info("Setting [{$keyDisplay}] has been deleted. ({$deleted} record(s))");

            return self::SUCCESS;
        }

        $this->error('No settings found to delete.');

        return self::FAILURE;
    }
}
