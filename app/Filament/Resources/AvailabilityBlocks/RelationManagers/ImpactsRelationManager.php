<?php

namespace App\Filament\Resources\AvailabilityBlocks\RelationManagers;

use App\Actions\RecordClosureCustomerCommunication;
use App\Actions\RecordClosureImpactReview;
use App\Actions\ResolveClosureImpact;
use App\Enums\AttendanceState;
use App\Enums\BookingStatus;
use App\Enums\ClosureFinancialRequirement;
use App\Enums\ClosureImpactStatus;
use App\Enums\ClosureOperationalRequirement;
use App\Enums\FinancialStatus;
use App\Filament\Resources\Bookings\BookingResource;
use App\Models\AvailabilityBlock;
use App\Models\AvailabilityBlockBookingImpact;
use App\Models\User;
use App\Services\ClosureAuthorization;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ImpactsRelationManager extends RelationManager
{
    protected static string $relationship = 'impacts';

    protected static ?string $title = 'Affected bookings';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $actor = auth()->user();
        if (! $actor instanceof User || ! $ownerRecord instanceof AvailabilityBlock) {
            return false;
        }
        $actor = app(ClosureAuthorization::class)->freshActor($actor);

        return $actor->can('view', $ownerRecord) && $actor->can('bookings.view');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table->heading('Affected bookings')->description('Review records management decisions only. Communication records manual contact; this page sends no messages. Resolution closes the operational review and does not reschedule, cancel, refund, credit or adjust an invoice. Financial requirements remain recorded for follow-up.')
            ->modifyQueryUsing(function (Builder $query): Builder {
                $actor = $this->actor();

                return $query->where('availability_block_id', $this->getOwnerRecord()->getKey())
                    ->whereHas('booking.centre.assignedUsers', fn (Builder $query) => $query->whereKey($actor->id))
                    ->with(['booking.customer:id,name,email', 'booking.facility:id,name', 'booking.resource:id,name', 'booking.series:id,identifier,occurrence_count']);
            })
            ->columns([
                TextColumn::make('booking.reference')->label('Booking reference')->searchable()
                    ->url(fn (AvailabilityBlockBookingImpact $record): ?string => $this->actor()->can('view', $record->booking)
                        ? BookingResource::getUrl('view', ['record' => $record->booking])
                        : null)
                    ->description(fn (AvailabilityBlockBookingImpact $record): ?string => $record->booking->series === null ? null : 'Occurrence '.$record->booking->occurrence_index.' of '.$record->booking->series->occurrence_count.' · '.$record->booking->series->identifier),
                TextColumn::make('booking.customer.name')->label('Customer'),
                TextColumn::make('booking.customer.email')->label('Email for manual contact')->toggleable(),
                TextColumn::make('booking.starts_at')->label('Starts')->dateTime('j M Y, H:i'),
                TextColumn::make('booking.ends_at')->label('Ends')->dateTime('j M Y, H:i'),
                TextColumn::make('booking.facility.name')->label('Facility'),
                TextColumn::make('booking.resource.name')->label('Resource'),
                TextColumn::make('booking.status')->label('Booking state')->formatStateUsing(fn (BookingStatus $state): string => $state->label()),
                TextColumn::make('booking.attendance_state')->label('Attendance')->formatStateUsing(fn (AttendanceState $state): string => $state->label()),
                TextColumn::make('booking.financial_status')->label('Financial state')->formatStateUsing(fn (FinancialStatus $state): string => $state->label()),
                TextColumn::make('status')->label('Impact review')->formatStateUsing(fn (ClosureImpactStatus $state): string => $state->label()),
                TextColumn::make('operational_requirement')->label('Operational requirement')->formatStateUsing(fn (ClosureOperationalRequirement $state): string => $state->label()),
                TextColumn::make('financial_requirement')->label('Financial follow-up')->formatStateUsing(fn (ClosureFinancialRequirement $state): string => $state->label()),
                TextColumn::make('communication_required')->label('Communication')->formatStateUsing(fn (bool $state): string => $state ? 'Customer contact required' : 'Customer contact not required'),
                TextColumn::make('communicated_at')->label('Manual contact recorded')->dateTime('j M Y, H:i')->placeholder('Not recorded'),
                TextColumn::make('resolved_at')->label('Review resolved')->dateTime('j M Y, H:i')->placeholder('Unresolved'),
                TextColumn::make('review_notes')->label('Review notes')->wrap()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('communication_notes')->label('Manual contact notes')->wrap()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('resolution_notes')->label('Resolution notes')->wrap()->toggleable(isToggledHiddenByDefault: true),
            ])->recordActions([
                Action::make('review')->label('Record review')
                    ->visible(fn (AvailabilityBlockBookingImpact $record): bool => $record->status === ClosureImpactStatus::Unresolved)
                    ->fillForm(fn (AvailabilityBlockBookingImpact $record): array => ['operational_requirement' => $record->operational_requirement->value, 'financial_requirement' => $record->financial_requirement->value, 'communication_required' => $record->communication_required, 'review_notes' => $record->review_notes])
                    ->schema([
                        Select::make('operational_requirement')->label('Operational requirement')->options(ClosureOperationalRequirement::options())->required(),
                        Select::make('financial_requirement')->label('Financial follow-up requirement')->options(ClosureFinancialRequirement::options())->required(),
                        Toggle::make('communication_required')->label('Customer contact required'),
                        Textarea::make('review_notes')->label('Review notes')->required()->maxLength(2000),
                    ])->modalDescription('Record the required next steps. This leaves the impact unresolved and does not change the booking or its financial obligations.')
                    ->action(function (AvailabilityBlockBookingImpact $record, array $data): void {
                        app(RecordClosureImpactReview::class)->handle($this->actor(), $this->impact($record), $data);
                        Notification::make()->title('Closure impact review recorded.')->success()->send();
                    }),
                Action::make('contactCustomer')->label('Record customer contact')
                    ->visible(fn (AvailabilityBlockBookingImpact $record): bool => $record->status === ClosureImpactStatus::Unresolved && $record->communication_required && $record->communicated_at === null)
                    ->modalDescription('Record contact you have already made manually. No email or other message will be sent by this action.')
                    ->schema([Textarea::make('communication_notes')->label('Manual contact notes')->required()->maxLength(2000)])
                    ->action(function (AvailabilityBlockBookingImpact $record, array $data): void {
                        app(RecordClosureCustomerCommunication::class)->handle($this->actor(), $this->impact($record), $data['communication_notes']);
                        Notification::make()->title('Manual customer contact recorded.')->success()->send();
                    }),
                Action::make('resolve')->label('Resolve operational review')->requiresConfirmation()
                    ->visible(fn (AvailabilityBlockBookingImpact $record): bool => $record->status === ClosureImpactStatus::Unresolved && $record->operational_requirement === ClosureOperationalRequirement::NoActionRequired && $record->financial_requirement !== ClosureFinancialRequirement::ReviewRequired && (! $record->communication_required || $record->communicated_at !== null))
                    ->modalDescription('Close this operational review. Any recorded financial follow-up remains required. This does not perform a booking change, refund, credit or invoice adjustment.')
                    ->schema([Textarea::make('resolution_notes')->label('Resolution notes')->required()->maxLength(2000)])
                    ->action(function (AvailabilityBlockBookingImpact $record, array $data): void {
                        app(ResolveClosureImpact::class)->handle($this->actor(), $this->impact($record), $data['resolution_notes']);
                        $this->dispatch('closure-impact-resolved');
                        Notification::make()->title('Operational review resolved. Financial follow-up remains as recorded.')->success()->send();
                    }),
            ]);
    }

    private function actor(): User
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);
        $owner = AvailabilityBlock::query()->whereKey($this->getOwnerRecord()->getKey())->firstOrFail();
        $actor = app(ClosureAuthorization::class)->authorizeBlock($actor, $owner);
        abort_unless($actor->can('bookings.view'), 403);

        return $actor;
    }

    private function impact(AvailabilityBlockBookingImpact $record): AvailabilityBlockBookingImpact
    {
        $this->actor();

        return AvailabilityBlockBookingImpact::query()->where('availability_block_id', $this->getOwnerRecord()->getKey())->findOrFail($record->id);
    }
}
