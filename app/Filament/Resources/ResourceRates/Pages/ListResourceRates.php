<?php

namespace App\Filament\Resources\ResourceRates\Pages;

use App\Filament\Resources\ResourceRates\ResourceRateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListResourceRates extends ListRecords
{
    protected static string $resource = ResourceRateResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
