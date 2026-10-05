<?php

namespace App\Filament\Resources\AvailabilityBlocks\Pages;

use App\Filament\Resources\AvailabilityBlocks\AvailabilityBlockResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateAvailabilityBlock extends CreateRecord
{
    protected static string $resource = AvailabilityBlockResource::class;

    protected ?bool $hasDatabaseTransactions = false;

    protected static bool $canCreateAnother = false;

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        return app(\App\Actions\CreateAvailabilityBlock::class)->handle($actor, $data);
    }

    protected function getRedirectUrl(): string
    {
        return AvailabilityBlockResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
