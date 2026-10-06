<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Actions\ApproveBooking;
use App\Actions\RejectBooking;
use App\Actions\SetCustomerInvoiceTerms;
use App\Enums\BillingMethod;
use App\Enums\BookingStatus;
use App\Exceptions\BookingLifecycleTransitionUnavailable;
use App\Exceptions\InvoiceUnavailable;
use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use App\Models\BookingEquipment;
use App\Models\CustomerInvoiceTerms;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewBooking extends ViewRecord
{
    protected static string $resource = BookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label('Approve')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Approve booking request')
                ->modalDescription(fn (): string => $this->invoiceTerms()?->enabled
                    ? 'Approval confirms this booking under authorised invoice terms. Payment remains outstanding until settlement.'
                    : 'Approval reserves the requested resource and equipment while the booking awaits payment. It does not confirm the booking.')
                ->visible(fn (): bool => $this->canDecide('approve'))
                ->disabled(fn (): bool => ! $this->hasActiveProvisionalProtection())
                ->action(fn (): mixed => $this->approveBooking()),
            Action::make('invoiceTerms')
                ->label('Customer invoice terms')
                ->visible(fn (): bool => $this->booking()->status === BookingStatus::Requested
                    && $this->actor()->can('invoices.manage')
                    && $this->actor()->can('view', $this->booking()))
                ->fillForm(function (): array {
                    $terms = $this->invoiceTerms();

                    return $terms === null ? ['enabled' => false, 'term_days' => config('invoices.default_term_days')] : ['enabled' => $terms->enabled, 'term_days' => $terms->term_days];
                })
                ->schema([
                    Toggle::make('enabled')->label('Authorise invoice billing at this centre'),
                    TextInput::make('term_days')->label('Payment terms (days)')->integer()->required()->minValue(1)->maxValue(365),
                ])
                ->modalDescription(fn (): string => ($this->booking()->organisation_id === null ? 'These terms apply to this customer' : 'These terms apply to the responsible organisation')
                    .' at this centre when future booking requests are approved. Existing approved arrangements remain unchanged.')
                ->action(function (array $data): void {
                    try {
                        app(SetCustomerInvoiceTerms::class)->handle($this->actor(), $this->booking(), (bool) $data['enabled'], (int) $data['term_days']);
                    } catch (InvoiceUnavailable $exception) {
                        Notification::make()->title($exception->getMessage())->danger()->send();

                        return;
                    }

                    Notification::make()->title('Customer invoice terms updated.')->success()->send();
                }),
            Action::make('reject')
                ->label('Reject')
                ->color('danger')
                ->schema([
                    Textarea::make('reason')
                        ->label('Rejection reason')
                        ->required()
                        ->minLength(1)
                        ->maxLength(1000),
                ])
                ->modalHeading('Reject booking request')
                ->modalDescription('Rejection releases the provisional resource and equipment protection immediately.')
                ->visible(fn (): bool => $this->canDecide('reject'))
                ->action(fn (array $data): mixed => $this->rejectBooking($data['reason'])),
        ];
    }

    private function approveBooking(): mixed
    {
        try {
            $approved = $this->approvalAction()->handle($this->actor(), $this->booking());
        } catch (BookingLifecycleTransitionUnavailable $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();

            return null;
        }

        Notification::make()->title($approved->billing_method === BillingMethod::Invoice
            ? 'Booking confirmed under invoice terms; payment remains outstanding.'
            : 'Booking approved and awaiting payment.')->success()->send();

        return $this->redirect(BookingResource::getUrl('index'));
    }

    private function rejectBooking(string $reason): mixed
    {
        try {
            $this->rejectionAction()->handle($this->actor(), $this->booking(), $reason);
        } catch (BookingLifecycleTransitionUnavailable $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();

            return null;
        }

        Notification::make()->title('Booking rejected and provisional protection released.')->success()->send();

        return $this->redirect(BookingResource::getUrl('index'));
    }

    private function canDecide(string $ability): bool
    {
        return $this->booking()->status === BookingStatus::Requested
            && $this->actor()->can($ability, $this->booking());
    }

    private function hasActiveProvisionalProtection(): bool
    {
        $booking = $this->booking();
        $evaluatedAt = CarbonImmutable::now(config('app.timezone'));

        if (! $booking->allocationOccupancy?->expires_at?->gt($evaluatedAt)) {
            return false;
        }

        $allocations = $booking->equipmentAllocations()->get()->keyBy('booking_equipment_id');

        return $booking->equipmentRequests->every(
            fn (BookingEquipment $request): bool => $allocations->get($request->id)?->expires_at?->gt($evaluatedAt) === true,
        );
    }

    private function booking(): Booking
    {
        /** @var Booking $booking */
        $booking = $this->getRecord();

        return $booking;
    }

    private function invoiceTerms(): ?CustomerInvoiceTerms
    {
        return CustomerInvoiceTerms::query()->responsibleFor($this->booking())->first();
    }

    private function actor(): User
    {
        /** @var User $actor */
        $actor = auth()->user();

        return $actor;
    }

    private function approvalAction(): ApproveBooking
    {
        return app(ApproveBooking::class);
    }

    private function rejectionAction(): RejectBooking
    {
        return app(RejectBooking::class);
    }
}
