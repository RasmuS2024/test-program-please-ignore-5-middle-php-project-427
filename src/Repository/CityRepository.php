<?php

namespace Hexlet\Code\Repository;

use PDO;

final class CityRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * All cities in the display order defined by the reference data.
     *
     * @return list<array{code: string, name: string, country: string, sort_order: int|string}>
     */
    public function all(): array
    {
        $stmt = $this->pdo->query('SELECT code, name, country, sort_order FROM cities ORDER BY sort_order');

        return $stmt->fetchAll();
    }
}
