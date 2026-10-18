<?php

namespace Hexlet\Code\Serializer;

use DateTimeImmutable;
use DateTimeZone;

final class DateTimeSerializer
{
    /**
     * Render a PostgreSQL timestamp as a UTC ISO-8601 string with a trailing Z.
     */
    public static function toUtcIso(mixed $value): string
    {
        return (new DateTimeImmutable((string) $value, new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d\TH:i:s\Z');
    }
}
