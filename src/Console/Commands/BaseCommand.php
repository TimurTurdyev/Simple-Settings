<?php

namespace TimurTurdyev\SimpleSettings\Console\Commands;

use Illuminate\Console\Command;
use TimurTurdyev\SimpleSettings\Contracts\SettingStorageInterface;

abstract class BaseCommand extends Command
{
    protected function storage(string $group = 'global'): SettingStorageInterface
    {
        return app(SettingStorageInterface::class)->forGroup($group);
    }
}
