<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleSettings\Events;

use Illuminate\Foundation\Events\Dispatchable;

class SettingDeleted
{
    use Dispatchable;

    public function __construct(
        public readonly string $key,
        public readonly string $group,
        public readonly mixed $oldValue = null,
    ) {}
}
