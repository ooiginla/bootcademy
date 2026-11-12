<?php

namespace App\Academies\Deletion;

/**
 * Something that still belongs to a record and stops it from being deleted.
 *
 * A removable blocker can itself be deleted or detached first. A permanent
 * blocker is history that is never deleted (invoices, payments, attendance),
 * so the parent record has to be kept.
 */
final readonly class DeletionBlocker
{
    public function __construct(
        public int $count,
        public string $singular,
        public string $plural,
        public bool $permanent = false,
    ) {}

    public function describe(): string
    {
        return $this->count.' '.($this->count === 1 ? $this->singular : $this->plural);
    }
}
