<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Carbon\CarbonInterface;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $reference
 * @property int $booking_id
 * @property int $customer_id
 * @property string $provider
 * @property string|null $provider_session_id
 * @property string|null $provider_payment_intent_id
 * @property string|null $checkout_url
 * @property array<string, mixed> $checkout_parameters
 * @property int $amount_minor
 * @property string $currency
 * @property PaymentStatus $status
 * @property bool $live_mode
 * @property CarbonInterface $session_expires_at
 * @property CarbonInterface|null $succeeded_at
 * @property string|null $reconciliation_issue
 * @property CarbonInterface $created_at
 */
#[Fillable(['reference', 'booking_id', 'customer_id', 'provider', 'provider_session_id', 'provider_payment_intent_id', 'checkout_url', 'checkout_parameters', 'amount_minor', 'currency', 'status', 'live_mode', 'session_expires_at', 'succeeded_at', 'reconciliation_issue'])]
#[Hidden(['provider_session_id', 'provider_payment_intent_id', 'checkout_url', 'checkout_parameters', 'reconciliation_issue'])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** @return BelongsTo<User, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'checkout_parameters' => 'array',
            'status' => PaymentStatus::class,
            'live_mode' => 'boolean',
            'session_expires_at' => 'datetime',
            'succeeded_at' => 'datetime',
        ];
    }
}
