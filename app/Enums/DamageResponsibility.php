<?php

namespace App\Enums;

enum DamageResponsibility: string
{
    case Undetermined = 'undetermined';
    case Customer = 'customer';
    case Centre = 'centre';
    case ThirdParty = 'third_party';
    case NoFault = 'no_fault';

    public function label(): string
    {
        return match ($this) {
            self::Undetermined => 'Undetermined',
            self::Customer => 'Customer',
            self::Centre => 'Centre',
            self::ThirdParty => 'Third party',
            self::NoFault => 'No fault established',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_reduce(self::cases(), function (array $options, self $responsibility): array {
            $options[$responsibility->value] = $responsibility->label();

            return $options;
        }, []);
    }
}
