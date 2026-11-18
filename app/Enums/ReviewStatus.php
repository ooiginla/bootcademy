<?php

namespace App\Enums;

enum ReviewStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Closed = 'closed';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
