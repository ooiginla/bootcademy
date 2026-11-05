<?php

namespace App\Academies\PlayerImports;

class PlayerImportAnalysis
{
    /**
     * @param  list<string>  $headers
     * @param  list<array{line: int, label: string, data: array<string, mixed>, errors: list<string>}>  $rows
     * @param  list<string>  $fileErrors
     */
    public function __construct(
        public readonly array $headers,
        public readonly array $rows,
        public readonly array $fileErrors,
    ) {}

    public function totalRows(): int
    {
        return count($this->rows);
    }

    public function errorRows(): int
    {
        return count(array_filter($this->rows, fn (array $row): bool => $row['errors'] !== []));
    }

    public function isValid(): bool
    {
        return $this->fileErrors === [] && $this->errorRows() === 0 && $this->rows !== [];
    }
}
