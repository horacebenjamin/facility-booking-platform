<?php

namespace App\Http\Controllers;

use App\Actions\ReconcileStripePayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use JsonException;

class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, ReconcileStripePayment $reconcile): JsonResponse
    {
        try {
            $event = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            abort(422, 'Invalid payment event.');
        }

        abort_unless(is_array($event), 422, 'Invalid payment event.');
        try {
            $reconcile->handle($event);
        } catch (ValidationException) {
            return response()->json(['message' => 'Payment event does not match the expected context.'], 422);
        }

        return response()->json(['received' => true]);
    }
}
