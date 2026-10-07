<?php

namespace App\Filament\Exports;

use App\Filament\Exports\Concerns\FormatsReportExportValues;
use App\Models\Payment;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Str;

class PaymentExporter extends Exporter
{
    use FormatsReportExportValues;

    protected static ?string $model = Payment::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('reference')->label('Payment reference'),
            ExportColumn::make('succeeded_at')->label('Settled (Europe/London)')->formatStateUsing(fn ($state): string => static::localDateTime($state)),
            ExportColumn::make('booking.reference')->label('Booking reference'),
            ExportColumn::make('booking.centre.name')->label('Centre'),
            ExportColumn::make('booking.facility.name')->label('Facility'),
            ExportColumn::make('booking.resource.name')->label('Resource'),
            ...static::ownershipColumns(fn (Payment $record) => $record->booking?->organisation, fn (Payment $record) => $record->booking?->customer),
            ExportColumn::make('currency')->label('Currency'),
            ExportColumn::make('amount_minor')->label('Amount')->formatStateUsing(fn ($state): string => static::majorUnits($state)),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your payment export has completed and '.Str::of('row')->counted($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Str::of('row')->counted($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
