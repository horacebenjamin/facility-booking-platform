<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property int $activity_id
 * @property string $semantic_key
 * @property int $customer_id
 * @property string $type
 * @property array{type: string, title: string, body: string, action_label: string, action_url: string, occurred_at: string} $payload
 * @property CarbonInterface|null $database_delivered_at
 * @property CarbonInterface|null $mail_delivered_at
 */
#[Fillable(['id', 'activity_id', 'semantic_key', 'customer_id', 'type', 'payload', 'database_delivered_at', 'mail_delivered_at'])]
class CustomerCommunication extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    /** @return BelongsTo<User, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['payload' => 'array', 'database_delivered_at' => 'datetime', 'mail_delivered_at' => 'datetime'];
    }
}
