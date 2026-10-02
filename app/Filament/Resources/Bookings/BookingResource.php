<?php

namespace App\Filament\Resources\Bookings;

use App\Enums\BookingStatus;
use App\Enums\FinancialStatus;
use App\Filament\Resources\Bookings\Pages\ListBookings;
use App\Filament\Resources\Bookings\Pages\ViewBooking;
use App\Models\Booking;
use Carbon\CarbonInterface;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class BookingResource extends Resource
{
    protected static ?string $model = Booking::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static string|UnitEnum|null $navigationGroup = 'Bookings';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'reference';

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at')
            ->columns([
                TextColumn::make('reference')->searchable()->sortable(),
                TextColumn::make('customer.name')->label('Customer')->searchable()->sortable(),
                TextColumn::make('centre.name')->label('Centre')->sortable(),
                TextColumn::make('facility.name')->label('Facility')->sortable(),
                TextColumn::make('resource.name')->label('Resource')->searchable()->sortable(),
                TextColumn::make('starts_at')->label('Requested time')->dateTime('j M Y, H:i')->sortable(),
                TextColumn::make('ends_at')->label('Ends')->dateTime('H:i'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (BookingStatus $state): string => $state->label()),
                TextColumn::make('priceSnapshot.final_total_minor')
                    ->label('Total')
                    ->formatStateUsing(fn (?int $state): string => self::formatMinor($state)),
                TextColumn::make('allocationOccupancy.expires_at')
                    ->label('Provisional protection')
                    ->dateTime('j M Y, H:i')
                    ->placeholder('Missing')
                    ->description(fn (?CarbonInterface $state): string => match (true) {
                        $state === null => 'Protection is missing',
                        $state->isPast() => 'Expired — approval unavailable',
                        default => 'Active provisional protection',
                    })
                    ->color(fn (?CarbonInterface $state): string => $state?->isFuture() ? 'warning' : 'danger'),
            ])
            ->recordActions([ViewAction::make()->label('Review')]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Booking request')->schema([
                TextEntry::make('reference')->label('Booking reference')->copyable(),
                TextEntry::make('status')->label('Booking state')->badge()->formatStateUsing(fn (BookingStatus $state): string => $state->label()),
                TextEntry::make('financial_status')->label('Financial state')->badge()->formatStateUsing(fn (FinancialStatus $state): string => $state->label()),
                TextEntry::make('allocationOccupancy.expires_at')
                    ->label('Provisional protection expires')
                    ->dateTime('j M Y, H:i')
                    ->placeholder('Protection is missing')
                    ->helperText(fn (?CarbonInterface $state): string => $state?->isFuture()
                        ? 'Protection is active and will be revalidated before approval.'
                        : 'Protection is expired or missing. Approval is unavailable.'),
            ])->columns(2),
            Section::make('Customer and venue')->schema([
                TextEntry::make('customer.name')->label('Customer'),
                TextEntry::make('customer.email')->label('Customer email'),
                TextEntry::make('centre.name')->label('Centre'),
                TextEntry::make('facility.name')->label('Facility'),
                TextEntry::make('resource.name')->label('Resource'),
                TextEntry::make('starts_at')->label('Starts')->dateTime('j M Y, H:i'),
                TextEntry::make('ends_at')->label('Ends')->dateTime('j M Y, H:i'),
            ])->columns(2),
            Section::make('Equipment')->schema([
                RepeatableEntry::make('equipmentRequests')
                    ->label('Requested equipment')
                    ->schema([
                        TextEntry::make('equipment.name')->label('Equipment'),
                        TextEntry::make('requested_quantity')->label('Quantity'),
                    ])
                    ->contained(false)
                    ->columns(2),
            ]),
            Section::make('Price snapshot')->schema([
                TextEntry::make('priceSnapshot.currency')->label('Currency'),
                TextEntry::make('priceSnapshot.resource_amount_minor')->label('Resource')->formatStateUsing(fn (?int $state): string => self::formatMinor($state)),
                TextEntry::make('priceSnapshot.equipment_amount_minor')->label('Equipment')->formatStateUsing(fn (?int $state): string => self::formatMinor($state)),
                TextEntry::make('priceSnapshot.discount_amount_minor')->label('Discount')->formatStateUsing(fn (?int $state): string => self::formatMinor($state)),
                TextEntry::make('priceSnapshot.final_total_minor')->label('Final total')->formatStateUsing(fn (?int $state): string => self::formatMinor($state)),
                RepeatableEntry::make('priceSnapshot.lines')
                    ->label('Price breakdown')
                    ->schema([
                        TextEntry::make('description')->label('Description'),
                        TextEntry::make('quantity')->label('Quantity'),
                        TextEntry::make('amount_minor')->label('Amount')->formatStateUsing(fn (?int $state): string => self::formatMinor($state)),
                    ])
                    ->contained(false)
                    ->columns(3),
            ])->columns(2),
            Section::make('Review history')->schema([
                RepeatableEntry::make('activities')
                    ->label('History')
                    ->schema([
                        TextEntry::make('created_at')->label('When')->dateTime('j M Y, H:i'),
                        TextEntry::make('event')->label('Action'),
                        TextEntry::make('causer.name')->label('Actor')->placeholder('System'),
                        TextEntry::make('properties.reason')->label('Rejection reason')->placeholder('—'),
                    ])
                    ->contained(false)
                    ->columns(2),
            ]),
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'customer',
                'centre',
                'facility',
                'resource',
                'allocationOccupancy',
                'equipmentRequests.equipment',
                'priceSnapshot.lines',
                'activities.causer',
            ])
            ->where('status', BookingStatus::Requested->value)
            ->whereHas('centre.assignedUsers', fn (Builder $query): Builder => $query->whereKey(auth()->id()));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBookings::route('/'),
            'view' => ViewBooking::route('/{record}'),
        ];
    }

    private static function formatMinor(?int $amountMinor): string
    {
        return '£'.number_format(($amountMinor ?? 0) / 100, 2);
    }
}
