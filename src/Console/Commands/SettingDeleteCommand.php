<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleSettings\Console\Commands;

class SettingDeleteCommand extends BaseCommand
{
    protected $signature = 'setting:delete {key? : The setting key to delete (leave empty to delete all in group)} {--g|group=global : The setting group}';

    protected $description = 'Delete a setting';

    public function handle(): int
    {
        $key = $this->argument('key');
        $group = $this->option('group');

        $storage = $this->storage($group);

        if (!$key && !$this->confirm("Delete ALL settings in group [{$group}]?")) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        $deleted = $key ? $storage->remove($key) : $storage->removeAll();

        if ($deleted > 0) {
            $keyDisplay = $key ?? "all settings in group [{$group}]";
            $this->info("Setting [{$keyDisplay}] has been deleted. ({$deleted} record(s))");

            return self::SUCCESS;
        }

        $this->error('No settings found to delete.');

        return self::FAILURE;
    }
}
