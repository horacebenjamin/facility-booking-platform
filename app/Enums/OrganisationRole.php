<?php

namespace App\Enums;

enum OrganisationRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case BookingManager = 'booking_manager';
    case Finance = 'finance';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Admin => 'Admin',
            self::BookingManager => 'Booking Manager',
            self::Finance => 'Finance',
            self::Member => 'Member',
        };
    }

    public function canManageMembers(): bool
    {
        return in_array($this, [self::Owner, self::Admin], true);
    }

    public function canCreateBookings(): bool
    {
        return in_array($this, [self::Owner, self::Admin, self::BookingManager], true);
    }

    /**
     * Every organisation role has read-only visibility of organisation bookings;
     * Finance needs it to find bookings awaiting payment.
     */
    public function canViewBookings(): bool
    {
        return true;
    }

    public function canManageBookings(): bool
    {
        return $this->canCreateBookings();
    }

    public function canManageFinance(): bool
    {
        return in_array($this, [self::Owner, self::Admin, self::Finance], true);
    }
}
