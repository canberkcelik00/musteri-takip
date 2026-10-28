<?php

namespace App\Enums;

/**
 * What a calendar entry is. The colour of its filter chip and of its strip comes from the category (docs/UI.md §5,
 * Takvim). The two alert categories are filled from Faz 16 (site down, SSL and domain expiry).
 */
enum CalendarCategory: string
{
    case Event = 'event';
    case Task = 'task';
    case ServiceEnd = 'service_end';
    case ChargeDue = 'charge_due';
    case Payment = 'payment';
    case Quote = 'quote';
    case AlertDown = 'alert_down';
    case AlertSoon = 'alert_soon';

    public function getLabel(): string
    {
        return __('enums.calendar_category.'.$this->value);
    }

    /**
     * The colour of the category's dot and strip: the name of a menu square (`--mt-nav-*`) or of a badge tone.
     */
    public function color(): string
    {
        return match ($this) {
            self::Event => 'blue',
            self::Task => 'pink',
            self::ServiceEnd => 'indigo',
            self::ChargeDue => 'purple',
            self::Payment => 'green',
            self::Quote => 'orange',
            self::AlertDown => 'red',
            self::AlertSoon => 'amber',
        };
    }

    /**
     * Charges, payments and quotes carry money, so a user without `money.view` never has them read.
     */
    public function needsMoney(): bool
    {
        return in_array($this, [self::ChargeDue, self::Payment, self::Quote], true);
    }
}
