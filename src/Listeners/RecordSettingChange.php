<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleSettings\Listeners;

use TimurTurdyev\SimpleSettings\Events\SettingDeleted;
use TimurTurdyev\SimpleSettings\Events\SettingSaved;
use TimurTurdyev\SimpleSettings\Models\SimpleSettingChange;

class RecordSettingChange
{
    public function handleSaved(SettingSaved $event): void
    {
        SimpleSettingChange::create([
            'group' => $event->group,
            'name' => $event->key,
            'event' => $event->existed ? 'updated' : 'created',
            'old_payload' => $event->existed ? $event->oldValue : null,
            'new_payload' => $event->value,
            'causer_type' => auth()->user()?->getMorphClass(),
            'causer_id' => auth()->user()?->getKey(),
        ]);
    }

    public function handleDeleted(SettingDeleted $event): void
    {
        SimpleSettingChange::create([
            'group' => $event->group,
            'name' => $event->key,
            'event' => 'deleted',
            'old_payload' => $event->oldValue,
            'new_payload' => null,
            'causer_type' => auth()->user()?->getMorphClass(),
            'causer_id' => auth()->user()?->getKey(),
        ]);
    }
}
