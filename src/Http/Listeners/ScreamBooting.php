<?php

declare(strict_types=1);

namespace Denosys\Http\Listeners;

class ScreamBooting
{
    public function handle(): void
    {
        dd('Listener for Kernel Booting');
    }

}
