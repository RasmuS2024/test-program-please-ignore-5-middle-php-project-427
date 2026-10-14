<?php

namespace Hexlet\Code\Serializer;

final class CitySerializer
{
    /**
     * @param array{code: string, name: string, country: string} $row
     *
     * @return array{code: string, name: string, country: string}
     */
    public static function serialize(array $row): array
    {
        return [
            'code' => (string) $row['code'],
            'name' => (string) $row['name'],
            'country' => (string) $row['country'],
        ];
    }

    /**
     * @param list<array{code: string, name: string, country: string}> $rows
     *
     * @return list<array{code: string, name: string, country: string}>
     */
    public static function many(array $rows): array
    {
        return array_map([self::class, 'serialize'], $rows);
    }
}