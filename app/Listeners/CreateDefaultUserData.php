<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\DefaultUserData;
use Illuminate\Auth\Events\Registered;

class CreateDefaultUserData
{
    public function __construct(private DefaultUserData $defaults) {}

    public function handle(Registered $event): void
    {
        if ($event->user instanceof User) {
            $this->defaults->createFor($event->user);
        }
    }
}
