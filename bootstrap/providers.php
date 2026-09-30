<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\ManagementPanelProvider;
use App\Providers\Filament\OperationsPanelProvider;
use App\Providers\FortifyServiceProvider;

return [
    AppServiceProvider::class,
    ManagementPanelProvider::class,
    OperationsPanelProvider::class,
    FortifyServiceProvider::class,
];
