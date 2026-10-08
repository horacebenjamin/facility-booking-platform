<?php

namespace App\Filament\Exports\Concerns;

use App\Models\Organisation;
use App\Models\User;
use App\Services\Reporting\ReportFilters;
use Carbon\CarbonInterface;
use Filament\Actions\Exports\ExportColumn;

trait FormatsReportExportValues
{
    /**
     * Account type, organisation and customer columns. Organisation-owned records keep the booking
     * contact in the customer column; personal records leave the organisation column blank.
     *
     * @param  callable(mixed): (Organisation|null)  $organisation
     * @param  callable(mixed): (User|null)  $customer
     * @return list<ExportColumn>
     */
    protected static function ownershipColumns(callable $organisation, callable $customer): array
    {
        return [
            ExportColumn::make('account_type')->label('Account type')
                ->state(fn ($record): string => $organisation($record) !== null ? 'Organisation' : 'Personal'),
            ExportColumn::make('organisation_name')->label('Organisation')->preventFormulaInjection()
                ->state(fn ($record): string => $organisation($record)->name ?? ''),
            ExportColumn::make('customer_name')->label('Customer')->preventFormulaInjection()
                ->state(fn ($record): string => $customer($record)->name ?? ''),
        ];
    }

    protected static function localDateTime(?CarbonInterface $value): string
    {
        return $value?->setTimezone(ReportFilters::timezone())->format('Y-m-d H:i') ?? '';
    }

    protected static function majorUnits(?int $amountMinor): string
    {
        return $amountMinor === null ? '' : number_format($amountMinor / 100, 2, '.', '');
    }
}
