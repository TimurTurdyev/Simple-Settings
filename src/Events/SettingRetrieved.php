<?php

namespace TimurTurdyev\SimpleSettings\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SettingRetrieved
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $key,
        public readonly mixed $value,
        public readonly string $group,
    ) {}
}
