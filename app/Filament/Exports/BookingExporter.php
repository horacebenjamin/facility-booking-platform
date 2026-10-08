<?php

namespace App\Filament\Exports;

use App\Filament\Exports\Concerns\FormatsReportExportValues;
use App\Models\Booking;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Str;

class BookingExporter extends Exporter
{
    use FormatsReportExportValues;

    protected static ?string $model = Booking::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('reference')->label('Booking reference'),
            ExportColumn::make('starts_at')->label('Starts ('.config('booking.local_timezone').')')->formatStateUsing(fn ($state): string => static::localDateTime($state)),
            ExportColumn::make('ends_at')->label('Ends ('.config('booking.local_timezone').')')->formatStateUsing(fn ($state): string => static::localDateTime($state)),
            ExportColumn::make('status')->label('Status')->formatStateUsing(fn ($state): string => $state->label()),
            ExportColumn::make('attendance_state')->label('Attendance')->formatStateUsing(fn ($state): string => $state->label()),
            ExportColumn::make('centre.name')->label('Centre'),
            ExportColumn::make('facility.name')->label('Facility'),
            ExportColumn::make('resource.name')->label('Resource'),
            ...static::ownershipColumns(fn (Booking $record) => $record->organisation, fn (Booking $record) => $record->customer),
            ExportColumn::make('currency')->label('Currency')->state(fn (Booking $record): string => $record->priceSnapshot->currency ?? ''),
            ExportColumn::make('historic_total')->label('Historic booking total')->state(fn (Booking $record): string => static::majorUnits($record->priceSnapshot?->final_total_minor)),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your booking export has completed and '.Str::of('row')->counted($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Str::of('row')->counted($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
