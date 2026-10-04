<?php

namespace App\Services;

use App\Models\CustomerCommunication;
use App\Notifications\BookingApprovedPaymentRequiredNotification;
use App\Notifications\BookingConfirmedNotification;
use App\Notifications\BookingRejectedNotification;
use App\Notifications\BookingRequestReceivedNotification;
use App\Notifications\InvoiceIssuedNotification;
use App\Notifications\PaymentReceivedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

class LifecycleNotificationService
{
    public function __construct(private LifecycleNotificationPayload $payloads) {}

    public function queueActivity(int $activityId): ?CustomerCommunication
    {
        $firstId = DB::table('notification_delivery_checkpoints')->where('id', 1)->value('first_activity_id');
        if ($firstId === null || $activityId < $firstId) {
            return null;
        }
        $activity = Activity::query()->find($activityId);
        $data = $activity === null ? null : $this->payloads->fromActivity($activity);
        if ($data === null) {
            return null;
        }
        $communication = CustomerCommunication::query()->firstOrCreate(['semantic_key' => $data['semantic_key']], [
            'id' => (string) Str::uuid(), 'activity_id' => $activityId,
            'customer_id' => $data['customer_id'], 'type' => $data['type'], 'payload' => $data['payload'],
        ]);
        $notification = match ($communication->type) {
            'booking.requested' => new BookingRequestReceivedNotification($communication->id),
            'booking.approved_payment_required' => new BookingApprovedPaymentRequiredNotification($communication->id),
            'booking.confirmed' => new BookingConfirmedNotification($communication->id),
            'booking.rejected' => new BookingRejectedNotification($communication->id),
            'payment.received' => new PaymentReceivedNotification($communication->id),
            'invoice.issued' => new InvoiceIssuedNotification($communication->id),
            default => throw new \UnexpectedValueException('Unsupported customer communication type.'),
        };
        $communication->customer?->notify($notification);

        return $communication;
    }
}
