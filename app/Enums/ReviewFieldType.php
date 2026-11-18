<?php

namespace App\Enums;

enum ReviewFieldType: string
{
    case ShortText = 'short_text';
    case LongText = 'long_text';
    case Email = 'email';
    case Phone = 'phone';
    case Number = 'number';
    case Date = 'date';
    case Select = 'select';
    case MultiSelect = 'multi_select';
    case Radio = 'radio';
    case Checkbox = 'checkbox';
    case Rating = 'rating';

    public function label(): string
    {
        return match ($this) {
            self::ShortText => 'Short text',
            self::LongText => 'Long text',
            self::Email => 'Email',
            self::Phone => 'Phone number',
            self::Number => 'Number',
            self::Date => 'Date',
            self::Select => 'Dropdown',
            self::MultiSelect => 'Multiple choice',
            self::Radio => 'Single choice',
            self::Checkbox => 'Checkboxes',
            self::Rating => 'Rating',
        };
    }

    public function needsOptions(): bool
    {
        return in_array($this, [self::Select, self::MultiSelect, self::Radio, self::Checkbox], true);
    }
}
