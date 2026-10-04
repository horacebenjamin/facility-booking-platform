<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingEquipment;
use App\Models\Centre;
use App\Models\Resource;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Validator;

class TodayScheduleService
{
    public function __construct(private OperationalOccupancyCalculator $occupancyCalculator) {}

    /** @return Collection<int, Centre> */
    public function authorizedCentres(User $staff): Collection
    {
        $this->authorizeStaff($staff);

        return $staff->assignedCentres()->orderBy('name')->orderBy('centres.id')->get(['centres.id', 'centres.name']);
    }

    public function forDate(User $staff, Centre $centre, string $date): TodaySchedule
    {
        $this->authorizeStaff($staff);

        if (! $staff->isAssignedToCentre($centre)) {
            throw new AuthorizationException;
        }

        Validator::make(['date' => $date], ['date' => ['required', 'date_format:Y-m-d']])->validate();

        $dayStart = CarbonImmutable::parse($date, config('app.timezone'))->startOfDay();
        $dayEnd = $dayStart->addDay();
        $refreshedAt = CarbonImmutable::now(config('app.timezone'));
        $resources = Resource::query()
            ->whereHas('facility', fn ($query) => $query->where('centre_id', $centre->id))
            ->get(['id', 'facility_id', 'name', 'setup_minutes', 'cleanup_minutes'])
            ->keyBy('id');

        /**
         * The widest centre buffers bound the candidate query. Exact overlap is
         * determined below by the shared calculator using current resource data.
         */
        $bookings = Booking::query()
            ->where('centre_id', $centre->id)
            ->where('status', BookingStatus::Confirmed)
            ->where('ends_at', '>', $dayStart->subMinutes((int) $resources->max('cleanup_minutes')))
            ->where('starts_at', '<', $dayEnd->addMinutes((int) $resources->max('setup_minutes')))
            ->with([
                'customer:id,name',
                'facility:id,name',
                'series:id,identifier,occurrence_count',
                'equipmentRequests:id,booking_id,equipment_id,requested_quantity',
                'equipmentRequests.equipment:id,name',
            ])
            ->get(['id', 'reference', 'customer_id', 'centre_id', 'facility_id', 'resource_id', 'booking_series_id', 'occurrence_index', 'starts_at', 'ends_at']);

        $sessions = [];

        foreach ($bookings as $booking) {
            $resource = $resources->get($booking->resource_id);

            if ($resource === null) {
                continue;
            }

            $period = $this->occupancyCalculator->calculate($resource, $booking->starts_at, $booking->ends_at);

            if ($period === null || ! $period->startsAt->lt($dayEnd) || ! $period->endsAt->gt($dayStart)) {
                continue;
            }

            $sessions[] = new TodayScheduleSession(
                bookingId: $booking->id,
                reference: $booking->reference,
                customerName: $booking->customer->name,
                facilityName: $booking->facility->name,
                resourceName: $resource->name,
                startsAt: CarbonImmutable::instance($booking->starts_at)->setTimezone(config('app.timezone')),
                endsAt: CarbonImmutable::instance($booking->ends_at)->setTimezone(config('app.timezone')),
                operationalStartsAt: $period->startsAt,
                operationalEndsAt: $period->endsAt,
                seriesIdentifier: $booking->series?->identifier,
                occurrenceIndex: $booking->occurrence_index,
                occurrenceCount: $booking->series?->occurrence_count,
                equipment: array_values($booking->equipmentRequests->sortBy('equipment_id')->map(fn (BookingEquipment $request): array => [
                    'name' => $request->equipment->name,
                    'quantity' => $request->requested_quantity,
                ])->all()),
            );
        }

        usort($sessions, fn (TodayScheduleSession $left, TodayScheduleSession $right): int => $left->operationalStartsAt <=> $right->operationalStartsAt
            ?: $left->startsAt <=> $right->startsAt
            ?: $left->bookingId <=> $right->bookingId);

        $now = [];
        $next = [];

        foreach ($sessions as $session) {
            if ($session->operationalStartsAt->lte($refreshedAt) && $session->operationalEndsAt->gt($refreshedAt)) {
                $now[] = $session;
            } elseif ($session->operationalStartsAt->gt($refreshedAt)
                && ($next === [] || $session->operationalStartsAt->eq($next[0]->operationalStartsAt))) {
                $next[] = $session;
            }
        }

        return new TodaySchedule($date, $refreshedAt, $sessions, $now, $next);
    }

    private function authorizeStaff(User $staff): void
    {
        if (! $staff->hasRole('leisure-assistant') || ! $staff->can('bookings.view')) {
            throw new AuthorizationException;
        }
    }
}
