<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleSettings\Events;

use Illuminate\Foundation\Events\Dispatchable;

class SettingRetrieved
{
    use Dispatchable;

    public function __construct(
        public readonly string $key,
        public readonly mixed $value,
        public readonly string $group,
    ) {}
}
