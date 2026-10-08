<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Actions\RecordManualInvoicePayment;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\InvoiceUnavailable;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Models\Invoice;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadInvoice')
                ->label('Download invoice PDF')
                ->visible(fn (): bool => auth()->user()?->can('view', $this->invoice()) === true)
                ->url(fn (): string => route('management.documents.invoices', $this->invoice()))
                ->openUrlInNewTab(),
            Action::make('downloadReceipt')
                ->label('Download payment receipt')
                ->visible(fn (): bool => auth()->user()?->can('payments.view') === true && $this->invoice()->payments()->where('status', PaymentStatus::Succeeded)->exists())
                ->url(fn (): string => route('management.documents.receipts', $this->invoice()->payments()->where('status', PaymentStatus::Succeeded)->latest('id')->firstOrFail()))
                ->openUrlInNewTab(),
            Action::make('recordPayment')->label('Record manual settlement')
                ->visible(fn (): bool => $this->invoice()->status === InvoiceStatus::Issued && auth()->user()?->can('recordPayment', $this->invoice()) === true)
                ->modalDescription('Record an externally received payment for the full outstanding invoice total. This does not collect a card payment.')
                ->schema([
                    TextInput::make('external_reference')->label('External payment reference')->required()->maxLength(255),
                    Textarea::make('note')->label('Settlement note')->required()->maxLength(1000),
                ])
                ->action(function (array $data): mixed {
                    /** @var User $actor */
                    $actor = auth()->user();

                    try {
                        app(RecordManualInvoicePayment::class)->handle($actor, $this->invoice(), $data['external_reference'], $data['note']);
                    } catch (InvoiceUnavailable $exception) {
                        Notification::make()->title($exception->getMessage())->danger()->send();

                        return null;
                    }

                    Notification::make()->title('Manual settlement recorded.')->success()->send();

                    return $this->redirect(InvoiceResource::getUrl('view', ['record' => $this->invoice()]));
                }),
        ];
    }

    private function invoice(): Invoice
    {
        /** @var Invoice $invoice */
        $invoice = $this->getRecord();

        return $invoice;
    }
}
