<?php

namespace App\Filament\Resources;

use App\Models\Centre;
use App\Models\Equipment;
use App\Models\Facility;
use App\Models\Resource as BookableResource;
use App\Models\User;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

abstract class VenueConfigurationResource extends Resource
{
    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->hasRole('manager') && $user->can('facilities.manage');
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canView(Model $record): bool
    {
        $centre = match (true) {
            $record instanceof Centre => $record,
            $record instanceof Facility, $record instanceof Equipment => $record->centre,
            $record instanceof BookableResource => $record->facility->centre,
            default => null,
        };

        return $centre instanceof Centre && static::canConfigureCentre($centre);
    }

    public static function canEdit(Model $record): bool
    {
        return static::canView($record);
    }

    public static function canDelete(Model $record): bool
    {
        return static::canView($record);
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function canConfigureCentre(Centre $centre): bool
    {
        $user = auth()->user();

        return static::canViewAny() && $user instanceof User && $user->isAssignedToCentre($centre);
    }

    public static function validatedId(mixed $value): int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        abort_unless(is_int($id), 403);

        return $id;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $userId = auth()->id();

        return match (static::getModel()) {
            Centre::class => $query->whereHas('assignedUsers', fn (Builder $assigned): Builder => $assigned->whereKey($userId)),
            Facility::class, Equipment::class => $query->whereHas('centre.assignedUsers', fn (Builder $assigned): Builder => $assigned->whereKey($userId)),
            BookableResource::class => $query->whereHas('facility.centre.assignedUsers', fn (Builder $assigned): Builder => $assigned->whereKey($userId)),
            default => $query->whereRaw('1 = 0'),
        };
    }
}
