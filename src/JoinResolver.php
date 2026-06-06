<?php

declare(strict_types=1);

namespace YakNet\MockEngine;

class JoinResolver
{
    /**
     * Resolves all joins on the given dataset.
     *
     * @param array<int, mixed> $leftData
     * @param array<int, array{data: array<int, mixed>, localKey: string, operator: string, foreignKey: string, type: string}> $joins
     * @return array<int, mixed>
     */
    public static function resolve(array $leftData, array $joins): array
    {
        $currentData = $leftData;

        foreach ($joins as $join) {
            $rightData = $join['data'];
            $localKey = $join['localKey'];
            $operator = $join['operator'];
            $foreignKey = $join['foreignKey'];
            $type = strtolower($join['type']);

            $joinedResult = [];

            foreach ($currentData as $leftRow) {
                $matched = false;
                $leftVal = Helper::getValue($leftRow, $localKey);

                foreach ($rightData as $rightRow) {
                    $rightVal = Helper::getValue($rightRow, $foreignKey);

                    // Evaluate the join condition using Evaluator
                    $isMatch = false;
                    try {
                        $isMatch = Evaluator::evaluate(
                            [$localKey => $leftVal],
                            $localKey,
                            $operator,
                            $rightVal
                        );
                    } catch (\Exception) {
                        $isMatch = false;
                    }

                    if ($isMatch) {
                        $joinedResult[] = self::mergeRows($leftRow, $rightRow);
                        $matched = true;
                    }
                }

                if (!$matched && $type === 'left') {
                    // For LEFT JOIN, if no match, keep left row with empty right side
                    $joinedResult[] = self::mergeRows($leftRow, []);
                }
            }

            $currentData = $joinedResult;
        }

        return $currentData;
    }

    /**
     * Merges two rows (array or object) into a single associative array.
     *
     * @return array<string, mixed>
     */
    private static function mergeRows(mixed $left, mixed $right): array
    {
        $leftArr = self::toArray($left);
        $rightArr = self::toArray($right);

        return array_merge($leftArr, $rightArr);
    }

    /**
     * Coerces any row type to array.
     *
     * @return array<string, mixed>
     */
    private static function toArray(mixed $row): array
    {
        if (is_array($row)) {
            /** @var array<string, mixed> $row */
            return $row;
        }
        if (is_object($row)) {
            if (method_exists($row, 'toArray')) {
                $arr = $row->toArray();
                return is_array($arr) ? $arr : [];
            }
            if ($row instanceof \JsonSerializable) {
                $arr = $row->jsonSerialize();
                return is_array($arr) ? $arr : [];
            }
            /** @var array<string, mixed> $arr */
            $arr = (array) $row;
            return $arr;
        }
        return [];
    }
}
