<?php

namespace App\Filament\Resources\Centres\Pages;

use App\Filament\Resources\Centres\CentreResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateCentre extends CreateRecord
{
    protected static string $resource = CentreResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        abort_unless(CentreResource::canCreate(), 403);
        $manager = auth()->user();
        abort_unless($manager instanceof User, 403);

        return DB::transaction(function () use ($data, $manager): Model {
            $centre = parent::handleRecordCreation($data);
            $manager->assignedCentres()->attach($centre);

            return $centre;
        });
    }
}
