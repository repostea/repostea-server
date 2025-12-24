<?php

declare(strict_types=1);

return [
    App\Providers\AppServiceProvider::class,
    // Only load Telescope in non-production environments
    ...app()->environment('local', 'testing')
        ? [App\Providers\TelescopeServiceProvider::class]
        : [],
];
