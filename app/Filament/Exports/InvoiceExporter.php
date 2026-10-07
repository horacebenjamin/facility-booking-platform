<?php

namespace App\Filament\Exports;

use App\Enums\InvoiceStatus;
use App\Filament\Exports\Concerns\FormatsReportExportValues;
use App\Models\Invoice;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Str;

class InvoiceExporter extends Exporter
{
    use FormatsReportExportValues;

    protected static ?string $model = Invoice::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('reference')->label('Invoice reference'),
            ExportColumn::make('issue_date')->label('Issued')->formatStateUsing(fn ($state): string => $state?->toDateString() ?? ''),
            ExportColumn::make('due_date')->label('Due date')->formatStateUsing(fn ($state): string => $state?->toDateString() ?? ''),
            ExportColumn::make('status')->label('Status')->formatStateUsing(fn ($state): string => $state->label()),
            ExportColumn::make('overdue')->label('Overdue')->state(fn (Invoice $record): string => $record->status === InvoiceStatus::Issued && $record->isOverdue() ? 'Yes' : 'No'),
            ExportColumn::make('paid_at')->label('Paid (Europe/London)')->formatStateUsing(fn ($state): string => static::localDateTime($state)),
            ExportColumn::make('centres')->label('Centre')->state(fn (Invoice $record): string => $record->lines->map(fn ($line) => $line->booking?->centre->name)->filter()->unique()->sort()->implode(', ')),
            ...static::ownershipColumns(fn (Invoice $record) => $record->organisation, fn (Invoice $record) => $record->customer),
            ExportColumn::make('currency')->label('Currency'),
            ExportColumn::make('total_minor')->label('Invoice total')->formatStateUsing(fn ($state): string => static::majorUnits($state)),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your invoice export has completed and '.Str::of('row')->counted($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Str::of('row')->counted($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
