<?php

namespace App\Filament\Resources\Invoices;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Resources\Invoices\Pages\ViewInvoice;
use App\Models\Invoice;
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

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-currency-pound';

    protected static string|UnitEnum|null $navigationGroup = 'Payments & invoices';

    protected static ?string $recordTitleAttribute = 'reference';

    public static function table(Table $table): Table
    {
        return $table->defaultSort('issue_date', 'desc')->columns([
            TextColumn::make('reference')->searchable(),
            TextColumn::make('customer.name')->label('Customer')->searchable(),
            TextColumn::make('organisation.name')->label('Organisation')->placeholder('Individual')->searchable(),
            TextColumn::make('status')->badge()->formatStateUsing(fn (InvoiceStatus $state): string => $state->label()),
            TextColumn::make('issue_date')->date('j M Y')->sortable(),
            TextColumn::make('due_date')->date('j M Y')->sortable(),
            TextColumn::make('currency'),
            TextColumn::make('total_minor')->label('Total')->formatStateUsing(fn (int $state, Invoice $record): string => self::formatAmount($state, $record->currency)),
        ])->recordActions([ViewAction::make()]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Invoice')->schema([
                TextEntry::make('reference')->copyable(),
                TextEntry::make('customer.name')->label('Customer'),
                TextEntry::make('organisation.name')->label('Responsible organisation')->placeholder('Individual invoice'),
                TextEntry::make('status')->badge()->formatStateUsing(fn (InvoiceStatus $state): string => $state->label()),
                TextEntry::make('issue_date')->date('j M Y'),
                TextEntry::make('due_date')->date('j M Y'),
                TextEntry::make('total_minor')->label('Total')->formatStateUsing(fn (int $state, Invoice $record): string => self::formatAmount($state, $record->currency)),
                TextEntry::make('settlement')->state(fn (Invoice $record): string => $record->status === InvoiceStatus::Paid ? 'Paid in full' : 'Outstanding in full'),
            ])->columns(2),
            Section::make('Booking charges')->schema([
                RepeatableEntry::make('lines')->schema([
                    TextEntry::make('booking.reference')->label('Booking'),
                    TextEntry::make('booking.centre.name')->label('Centre'),
                    TextEntry::make('description'),
                    TextEntry::make('amount_minor')->label('Amount')->formatStateUsing(fn (int $state): string => number_format($state / 100, 2)),
                ])->columns(4)->contained(false),
            ]),
            Section::make('Financial history')->schema([
                RepeatableEntry::make('activities')->schema([
                    TextEntry::make('created_at')->dateTime('j M Y, H:i'),
                    TextEntry::make('event'),
                    TextEntry::make('causer.name')->label('Actor')->placeholder('System'),
                ])->columns(3)->contained(false),
            ]),
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['customer', 'organisation', 'lines.booking.centre', 'activities.causer'])
            ->whereHas('lines')
            ->whereDoesntHave('lines.booking', fn (Builder $query): Builder => $query
                ->whereDoesntHave('centre.assignedUsers', fn (Builder $users): Builder => $users->whereKey(auth()->id())));
    }

    public static function getPages(): array
    {
        return ['index' => ListInvoices::route('/'), 'view' => ViewInvoice::route('/{record}')];
    }

    private static function formatAmount(int $amountMinor, string $currency): string
    {
        return $currency.' '.number_format($amountMinor / 100, 2);
    }
}
