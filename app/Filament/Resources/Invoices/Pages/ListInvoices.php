<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Actions\IssueInvoice;
use App\Enums\BillingMethod;
use App\Enums\BookingStatus;
use App\Enums\FinancialStatus;
use App\Exceptions\InvoiceUnavailable;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Models\Booking;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('issueInvoice')->label('Issue invoice')
                ->visible(fn (): bool => auth()->user()?->can('invoices.manage') === true)
                ->schema([
                    Select::make('booking_ids')->label('Booking charges')->multiple()->searchable()->required()
                        ->helperText('Select confirmed invoice bookings for one customer with matching currency and payment terms. Totals and due date are calculated from the booking records.')
                        ->options(fn (): array => Booking::query()->with('customer')
                            ->where('status', BookingStatus::Confirmed->value)
                            ->where('billing_method', BillingMethod::Invoice->value)
                            ->where('financial_status', FinancialStatus::InvoiceOutstanding->value)
                            ->whereHas('centre.assignedUsers', fn (Builder $query): Builder => $query->whereKey(auth()->id()))
                            ->orderBy('id')->get()
                            ->mapWithKeys(fn (Booking $booking): array => [$booking->id => $booking->reference.' — '.$booking->customer->name])->all()),
                ])
                ->action(function (array $data): mixed {
                    /** @var User $actor */
                    $actor = auth()->user();

                    try {
                        $invoice = app(IssueInvoice::class)->handle($actor, array_map('intval', $data['booking_ids']));
                    } catch (InvoiceUnavailable $exception) {
                        Notification::make()->title($exception->getMessage())->danger()->send();

                        return null;
                    }

                    Notification::make()->title('Invoice issued.')->success()->send();

                    return $this->redirect(InvoiceResource::getUrl('view', ['record' => $invoice]));
                }),
        ];
    }
}
