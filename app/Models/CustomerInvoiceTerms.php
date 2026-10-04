<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $customer_id
 * @property int $centre_id
 * @property bool $enabled
 * @property int $term_days
 * @property int $authorised_by
 * @property CarbonInterface $authorised_at
 */
#[Fillable(['customer_id', 'centre_id', 'enabled', 'term_days', 'authorised_by', 'authorised_at'])]
class CustomerInvoiceTerms extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'term_days' => 'integer', 'authorised_at' => 'datetime'];
    }
}
