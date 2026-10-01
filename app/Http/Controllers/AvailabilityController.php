<?php

namespace App\Http\Controllers;

use App\Models\Centre;
use App\Models\Equipment;
use App\Models\Facility;
use App\Models\Resource;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

class AvailabilityController extends Controller
{
    /**
     * Display the public availability selection page.
     */
    public function index(): Response
    {
        return Inertia::render('availability/Index', [
            'centres' => Centre::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Centre $centre): array => [
                    'id' => $centre->id,
                    'name' => $centre->name,
                ])
                ->values(),
            'facilities' => Facility::query()
                ->where('is_active', true)
                ->whereHas('centre', fn (Builder $query): Builder => $query->where('is_active', true))
                ->orderBy('name')
                ->get(['id', 'centre_id', 'name'])
                ->map(fn (Facility $facility): array => [
                    'id' => $facility->id,
                    'centre_id' => $facility->centre_id,
                    'name' => $facility->name,
                ])
                ->values(),
            'resources' => Resource::query()
                ->where('is_active', true)
                ->whereHas('facility', fn (Builder $query): Builder => $query
                    ->where('is_active', true)
                    ->whereHas('centre', fn (Builder $centreQuery): Builder => $centreQuery->where('is_active', true)))
                ->orderBy('name')
                ->get(['id', 'facility_id', 'name'])
                ->map(fn (Resource $resource): array => [
                    'id' => $resource->id,
                    'facility_id' => $resource->facility_id,
                    'name' => $resource->name,
                ])
                ->values(),
            'equipment' => Equipment::query()
                ->where('is_active', true)
                ->whereHas('centre', fn (Builder $query): Builder => $query->where('is_active', true))
                ->where(fn (Builder $query): Builder => $query
                    ->whereNull('facility_id')
                    ->orWhereHas('facility', fn (Builder $facilityQuery): Builder => $facilityQuery->where('is_active', true)))
                ->orderBy('name')
                ->get(['id', 'centre_id', 'facility_id', 'name', 'quantity'])
                ->map(fn (Equipment $equipment): array => [
                    'id' => $equipment->id,
                    'centre_id' => $equipment->centre_id,
                    'facility_id' => $equipment->facility_id,
                    'name' => $equipment->name,
                    'quantity' => $equipment->quantity,
                ])
                ->values(),
        ]);
    }
}
