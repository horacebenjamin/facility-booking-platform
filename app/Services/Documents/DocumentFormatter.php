<?php

namespace App\Services\Documents;

use App\Services\BookingDateTime;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

class DocumentFormatter
{
    public function money(int $minor, string $currency): string
    {
        $absolute = abs($minor);
        $amount = number_format(intdiv($absolute, 100)).'.'.str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);

        return ($minor < 0 ? '-' : '').($currency === 'GBP' ? '£' : $currency.' ').$amount;
    }

    public function date(CarbonInterface $date): string
    {
        return BookingDateTime::inLocalTimezone($date)->format('j F Y');
    }

    public function dateTime(CarbonInterface $date): string
    {
        return BookingDateTime::inLocalTimezone($date)->format('j F Y, H:i T');
    }

    public function filename(string $type, string $reference): string
    {
        return $type.'-'.Str::slug($reference).'.pdf';
    }
}
