<?php

namespace App\Services;

use App\Enums\AvailabilityReason;
use App\Enums\RecurringBookingConflictReason;
use App\Models\Booking;
use App\Models\BookingSeries;
use App\Models\Equipment;
use Carbon\CarbonImmutable;
use LogicException;

class RecurringBookingPayload
{
    /**
     * @param  list<array{equipment_id: int, quantity: int}>  $equipmentSelections
     * @return array<string, mixed>
     */
    public function validation(
        BookingSeriesValidationResult $validation,
        RecurrencePattern $pattern,
        array $equipmentSelections,
    ): array {
        $equipment = $this->equipment($equipmentSelections);
        $occurrences = [
            ...array_map(
                fn (ValidatedSeriesOccurrence $occurrence): array => [
                    'index' => $occurrence->period->index,
                    'starts_at' => $occurrence->period->startsAt->setTimezone($pattern->timezone)->format('Y-m-d H:i:s'),
                    'ends_at' => $occurrence->period->endsAt->setTimezone($pattern->timezone)->format('Y-m-d H:i:s'),
                    'status' => 'available',
                    'price' => [
                        'currency' => $occurrence->priceQuote->currency,
                        'total_minor' => $occurrence->priceQuote->finalTotalMinor,
                    ],
                    'equipment' => $equipment,
                    'conflict_reasons' => [],
                    'conflict_messages' => [],
                ],
                $validation->validOccurrences,
            ),
            ...array_map(
                fn (SeriesOccurrenceConflict $conflict): array => [
                    'index' => $conflict->period->index,
                    'starts_at' => $conflict->period->startsAt->setTimezone($pattern->timezone)->format('Y-m-d H:i:s'),
                    'ends_at' => $conflict->period->endsAt->setTimezone($pattern->timezone)->format('Y-m-d H:i:s'),
                    'status' => 'conflict',
                    'price' => null,
                    'equipment' => $equipment,
                    'conflict_reasons' => array_keys($this->safeConflicts($conflict)),
                    'conflict_messages' => array_values($this->safeConflicts($conflict)),
                ],
                $validation->conflicts,
            ),
        ];
        usort($occurrences, static fn (array $left, array $right): int => $left['index'] <=> $right['index']);

        if ($occurrences === []) {
            throw new LogicException('A recurring booking validation must contain occurrences.');
        }

        $firstOccurrence = $occurrences[0];
        $lastOccurrence = $occurrences[count($occurrences) - 1];

        return [
            'pattern' => [
                'frequency' => $pattern->frequency->value,
                'interval_weeks' => $pattern->intervalWeeks,
                'occurrence_count' => $pattern->occurrenceCount,
                'timezone' => $pattern->timezone,
            ],
            'summary' => [
                'cadence' => $pattern->intervalWeeks === 1
                    ? "Every week for {$pattern->occurrenceCount} bookings"
                    : "Every {$pattern->intervalWeeks} weeks for {$pattern->occurrenceCount} bookings",
                'date_range' => $this->date($firstOccurrence['starts_at']).' → '.$this->date($lastOccurrence['starts_at']),
            ],
            'occurrences' => $occurrences,
            'valid_occurrence_indexes' => array_map(
                static fn (ValidatedSeriesOccurrence $occurrence): int => $occurrence->period->index,
                $validation->validOccurrences,
            ),
            'conflict_count' => count($validation->conflicts),
        ];
    }

    /**
     * @param  list<Booking>  $bookings
     * @return array<string, mixed>
     */
    public function confirmation(BookingSeries $series, array $bookings): array
    {
        $timezone = $series->timezone;
        $bookings = collect($bookings)->sortBy('occurrence_index')->values();

        $occurrences = array_values($bookings->map(function (Booking $booking) use ($timezone): array {
            $booking->loadMissing('priceSnapshot');
            $priceSnapshot = $booking->priceSnapshot;

            if ($priceSnapshot === null) {
                throw new LogicException('A persisted recurring occurrence is missing its price snapshot.');
            }

            return [
                'index' => $booking->occurrence_index,
                'reference' => $booking->reference,
                'starts_at' => $booking->starts_at->setTimezone($timezone)->format('Y-m-d H:i:s'),
                'ends_at' => $booking->ends_at->setTimezone($timezone)->format('Y-m-d H:i:s'),
                'price' => [
                    'currency' => $priceSnapshot->currency,
                    'total_minor' => $priceSnapshot->final_total_minor,
                ],
            ];
        })->all());

        if ($occurrences === []) {
            throw new LogicException('A recurring booking confirmation must contain occurrences.');
        }

        $firstOccurrence = $occurrences[0];
        $lastOccurrence = $occurrences[count($occurrences) - 1];

        return [
            'identifier' => $series->identifier,
            'status' => 'requested',
            'status_label' => 'Requested / Awaiting Management Approval',
            'occurrence_count' => count($occurrences),
            'requested_occurrence_count' => $series->occurrence_count,
            'first_date' => $this->date($firstOccurrence['starts_at']),
            'last_date' => $this->date($lastOccurrence['starts_at']),
            'timezone' => $timezone,
            'organisation_name' => $series->organisation?->name,
            'booked_by_name' => $series->customer->name,
            'occurrences' => $occurrences,
        ];
    }

    /**
     * @param  list<array{equipment_id: int, quantity: int}>  $equipmentSelections
     * @return list<array{equipment_id: int, name: string, quantity: int}>
     */
    private function equipment(array $equipmentSelections): array
    {
        $names = Equipment::query()
            ->whereKey(collect($equipmentSelections)->pluck('equipment_id'))
            ->pluck('name', 'id');

        return array_map(
            static fn (array $selection): array => [
                'equipment_id' => $selection['equipment_id'],
                'name' => (string) $names->get($selection['equipment_id']),
                'quantity' => $selection['quantity'],
            ],
            $equipmentSelections,
        );
    }

    /**
     * @return array<string, string>
     */
    private function safeConflicts(SeriesOccurrenceConflict $conflict): array
    {
        $messages = [];

        foreach ($conflict->availabilityReasons as $reason) {
            [$code, $message] = match ($reason) {
                AvailabilityReason::InvalidPeriod => ['invalid_period', 'This booking date or time is not valid.'],
                AvailabilityReason::OutsideBookableHours => ['outside_bookable_hours', 'This time is outside the bookable hours for the facility.'],
                AvailabilityReason::Blockout => ['facility_block', 'The facility is closed or blocked for this time.'],
                AvailabilityReason::EquipmentUnavailable => ['equipment_unavailable', 'The requested equipment is unavailable in the quantity needed.'],
                AvailabilityReason::InactiveCentre,
                AvailabilityReason::InactiveFacility,
                AvailabilityReason::InactiveResource,
                AvailabilityReason::ResourceConflict => ['resource_unavailable', 'The resource is unavailable for this time.'],
            };
            $messages[$code] = $message;
        }

        foreach ($conflict->reasons as $reason) {
            if ($reason === RecurringBookingConflictReason::Pricing) {
                $messages['pricing_unavailable'] = 'A price is not available for this occurrence.';
            }

            if ($reason === RecurringBookingConflictReason::ResourceAllocationUnavailable) {
                $messages['resource_unavailable'] = 'The resource is unavailable for this time.';
            }

            if ($reason === RecurringBookingConflictReason::SeriesOverlap) {
                $messages['series_overlap'] = 'This occurrence overlaps another booking in the requested series.';
            }
        }

        if ($messages === []) {
            $messages['resource_unavailable'] = 'This occurrence is unavailable.';
        }

        return $messages;
    }

    private function date(string $dateTime): string
    {
        return CarbonImmutable::parse($dateTime)->format('j M Y');
    }
}
