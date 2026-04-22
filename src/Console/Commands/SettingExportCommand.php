<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleSettings\Console\Commands;

use TimurTurdyev\SimpleSettings\Contracts\SettingStorageInterface;

class SettingExportCommand extends BaseCommand
{
    protected $signature = 'setting:export {file? : Path to write JSON (omit for stdout)} {--g|group= : Filter by group}';

    protected $description = 'Export all settings to JSON';

    public function handle(SettingStorageInterface $settings): int
    {
        $group = $this->option('group');
        $file = $this->argument('file');

        $rows = $settings->list($group)
            ->map(fn($s) => [
                'group' => $s->group,
                'name' => $s->name,
                'type' => $s->type,
                'val' => $s->val,
            ])
            ->values()
            ->all();

        $json = json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            $this->error('Failed to encode settings to JSON: ' . json_last_error_msg());
            return self::FAILURE;
        }

        if ($file === null) {
            $this->line($json);
            return self::SUCCESS;
        }

        if (file_put_contents($file, $json) === false) {
            $this->error("Failed to write to [{$file}].");
            return self::FAILURE;
        }

        $this->info("Exported " . count($rows) . " setting(s) to [{$file}].");

        return self::SUCCESS;
    }
}
