<?php

namespace App\Policies;

use App\Filament\Exports\BookingExporter;
use App\Filament\Exports\InvoiceExporter;
use App\Filament\Exports\PaymentExporter;
use App\Models\User;
use Filament\Actions\Exports\Models\Export;

class ExportPolicy
{
    public function view(User $user, Export $export): bool
    {
        if ($export->user_id !== $user->id
            || ! $user->hasRole('manager')
            || ! $user->can('bookings.view')
            || ! $user->can('reports.view')
            || $export->file_disk !== 'local'
            || ! in_array($export->exporter, [BookingExporter::class, InvoiceExporter::class, PaymentExporter::class], true)) {
            return false;
        }

        $centreIds = json_decode((string) $export->authorized_centre_ids, true);
        if (! is_array($centreIds) || $centreIds === [] || ! array_is_list($centreIds)
            || ! collect($centreIds)->every(fn (mixed $id): bool => is_int($id) && $id > 0)) {
            return false;
        }

        $currentCentreIds = $user->assignedCentres()->pluck('centres.id')->all();

        return array_diff($centreIds, $currentCentreIds) === [];
    }
}
