<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Actions\ApproveBooking;
use App\Actions\RejectBooking;
use App\Enums\BookingStatus;
use App\Exceptions\BookingLifecycleTransitionUnavailable;
use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use App\Models\BookingEquipment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
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
                ->modalDescription('Approval reserves the requested resource and equipment while the booking awaits payment. It does not confirm the booking.')
                ->visible(fn (): bool => $this->canDecide('approve'))
                ->disabled(fn (): bool => ! $this->hasActiveProvisionalProtection())
                ->action(fn (): mixed => $this->approveBooking()),
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
            $this->approvalAction()->handle($this->actor(), $this->booking());
        } catch (BookingLifecycleTransitionUnavailable $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();

            return null;
        }

        Notification::make()->title('Booking approved and awaiting payment.')->success()->send();

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
