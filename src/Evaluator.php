<?php

declare(strict_types=1);

namespace YakNet\MockEngine;

use YakNet\MockEngine\Exception\QueryException;

class Evaluator
{
    /**
     * Evaluates a row value against a criteria.
     *
     * @throws QueryException
     */
    public static function evaluate(mixed $row, string $column, string $operator, mixed $value): bool
    {
        $actual = Helper::getValue($row, $column);
        $operator = strtoupper(trim($operator));

        switch ($operator) {
            case '=':
            case '==':
                return self::equals($actual, $value);

            case '!=':
            case '<>':
                return !self::equals($actual, $value);

            case '>':
                return $actual > $value;

            case '<':
                return $actual < $value;

            case '>=':
                return $actual >= $value;

            case '<=':
                return $actual <= $value;

            case 'LIKE':
                return self::like($actual, $value);

            case 'NOT LIKE':
                return !self::like($actual, $value);

            case 'IN':
                if (!is_array($value)) {
                    throw new QueryException("Operator IN requires an array value.");
                }
                return in_array($actual, $value, true);

            case 'NOT IN':
                if (!is_array($value)) {
                    throw new QueryException("Operator NOT IN requires an array value.");
                }
                return !in_array($actual, $value, true);

            case 'BETWEEN':
                if (!is_array($value) || count($value) !== 2) {
                    throw new QueryException("Operator BETWEEN requires an array with exactly two elements [min, max].");
                }
                return $actual >= $value[0] && $actual <= $value[1];

            case 'NOT BETWEEN':
                if (!is_array($value) || count($value) !== 2) {
                    throw new QueryException("Operator NOT BETWEEN requires an array with exactly two elements [min, max].");
                }
                return $actual < $value[0] || $actual > $value[1];

            default:
                throw new QueryException("Unsupported operator: {$operator}");
        }
    }

    /**
     * Case-insensitive equality check for strings, standard strict check for others.
     */
    private static function equals(mixed $actual, mixed $expected): bool
    {
        if (is_string($actual) && is_string($expected)) {
            return mb_strtolower($actual) === mb_strtolower($expected);
        }
        return $actual === $expected;
    }

    /**
     * SQL LIKE operator simulation.
     */
    private static function like(mixed $actual, mixed $pattern): bool
    {
        if (!is_string($actual) || !is_string($pattern)) {
            return false;
        }

        // Convert SQL LIKE pattern to a regular expression
        $regex = preg_quote($pattern, '/');
        $regex = str_replace(['%', '_'], ['.*', '.'], $regex);
        $regex = '/^' . $regex . '$/iu';

        return (bool) preg_match($regex, $actual);
    }
}
