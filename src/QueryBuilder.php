<?php

declare(strict_types=1);

namespace YakNet\MockEngine;

use Closure;
use YakNet\MockEngine\Exception\QueryException;

class QueryBuilder
{
    protected const NO_VALUE = '___no_value___';
    /** @var array<array-key, string|array<array-key, string>> */
    protected array $selects = [];

    /** @var array<int, array{type: string, column?: string|Closure, operator?: string, value?: mixed, boolean: string, query?: Closure}> */
    protected array $wheres = [];

    /** @var array<int, array{data: array<int, mixed>, localKey: string, operator: string, foreignKey: string, type: string}> */
    protected array $joins = [];

    /** @var array<int, array{column: string, direction: string}> */
    protected array $orders = [];

    protected ?int $limit = null;
    protected ?int $offset = null;
    protected ?string $groupBy = null;

    /**
     * @param array<int, mixed> $data The source array of rows (each row can be an array or object)
     */
    public function __construct(protected array $data)
    {
    }

    /**
     * Static factory method to start a query.
     *
     * @param array<int, mixed> $data
     */
    public function from(array $data): self
    {
        return new self($data);
    }

    /**
     * Set the columns to select.
     *
     * @param string|array<int, string> ...$columns
     */
    public function select(string|array ...$columns): self
    {
        if (count($columns) === 1 && is_array($columns[0])) {
            $columns = $columns[0];
        }

        $this->selects = array_merge($this->selects, $columns);
        return $this;
    }

    /**
     * Add a WHERE condition.
     *
     * @param string|Closure $column
     * @param string|mixed|null $operator
     * @param mixed $value
     * @param string $boolean 'AND' or 'OR'
     */
    public function where(
        string|Closure $column,
        mixed $operator = null,
        mixed $value = self::NO_VALUE,
        string $boolean = 'AND'
    ): self {
        if ($column instanceof Closure) {
            $this->wheres[] = [
                'type' => 'nested',
                'query' => $column,
                'boolean' => strtoupper($boolean),
            ];
            return $this;
        }

        if ($value === self::NO_VALUE) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => is_string($operator) ? $operator : '=',
            'value' => $value,
            'boolean' => strtoupper($boolean),
        ];

        return $this;
    }

    /**
     * Add an OR WHERE condition.
     */
    public function orWhere(string|Closure $column, mixed $operator = null, mixed $value = self::NO_VALUE): self
    {
        return $this->where($column, $operator, $value, 'OR');
    }

    /**
     * Add a WHERE NULL condition.
     */
    public function whereNull(string $column, string $boolean = 'AND'): self
    {
        return $this->where($column, '=', null, $boolean);
    }

    /**
     * Add a WHERE NOT NULL condition.
     */
    public function whereNotNull(string $column, string $boolean = 'AND'): self
    {
        return $this->where($column, '!=', null, $boolean);
    }

    /**
     * Add an OR WHERE NULL condition.
     */
    public function orWhereNull(string $column): self
    {
        return $this->whereNull($column, 'OR');
    }

    /**
     * Add an OR WHERE NOT NULL condition.
     */
    public function orWhereNotNull(string $column): self
    {
        return $this->whereNotNull($column, 'OR');
    }

    /**
     * Add a WHERE IN condition.
     *
     * @param array<int, mixed> $values
     */
    public function whereIn(string $column, array $values, string $boolean = 'AND'): self
    {
        $this->wheres[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => 'IN',
            'value' => $values,
            'boolean' => strtoupper($boolean),
        ];
        return $this;
    }

    /**
     * Add an OR WHERE IN condition.
     *
     * @param array<int, mixed> $values
     */
    public function orWhereIn(string $column, array $values): self
    {
        return $this->whereIn($column, $values, 'OR');
    }

    /**
     * Add a WHERE NOT IN condition.
     *
     * @param array<int, mixed> $values
     */
    public function whereNotIn(string $column, array $values, string $boolean = 'AND'): self
    {
        $this->wheres[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => 'NOT IN',
            'value' => $values,
            'boolean' => strtoupper($boolean),
        ];
        return $this;
    }

    /**
     * Add an OR WHERE NOT IN condition.
     *
     * @param array<int, mixed> $values
     */
    public function orWhereNotIn(string $column, array $values): self
    {
        return $this->whereNotIn($column, $values, 'OR');
    }

    /**
     * Add a WHERE BETWEEN condition.
     *
     * @param array{0: mixed, 1: mixed} $values
     */
    public function whereBetween(string $column, array $values, string $boolean = 'AND'): self
    {
        $this->wheres[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => 'BETWEEN',
            'value' => $values,
            'boolean' => strtoupper($boolean),
        ];
        return $this;
    }

    /**
     * Add an OR WHERE BETWEEN condition.
     *
     * @param array{0: mixed, 1: mixed} $values
     */
    public function orWhereBetween(string $column, array $values): self
    {
        return $this->whereBetween($column, $values, 'OR');
    }

    /**
     * Add a WHERE NOT BETWEEN condition.
     *
     * @param array{0: mixed, 1: mixed} $values
     */
    public function whereNotBetween(string $column, array $values, string $boolean = 'AND'): self
    {
        $this->wheres[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => 'NOT BETWEEN',
            'value' => $values,
            'boolean' => strtoupper($boolean),
        ];
        return $this;
    }

    /**
     * Add an OR WHERE NOT BETWEEN condition.
     *
     * @param array{0: mixed, 1: mixed} $values
     */
    public function orWhereNotBetween(string $column, array $values): self
    {
        return $this->whereNotBetween($column, $values, 'OR');
    }

    /**
     * Get the registered where conditions (useful for nested evaluation).
     *
     * @return array<int, array{type: string, column?: string|Closure, operator?: string, value?: mixed, boolean: string, query?: Closure}>
     */
    public function getWheres(): array
    {
        return $this->wheres;
    }

    /**
     * Add a JOIN relationship.
     *
     * @param array<int, mixed> $otherData
     */
    public function join(
        array $otherData,
        string $localKey,
        string $operator,
        string $foreignKey,
        string $type = 'inner'
    ): self {
        $this->joins[] = [
            'data' => $otherData,
            'localKey' => $localKey,
            'operator' => $operator,
            'foreignKey' => $foreignKey,
            'type' => $type,
        ];
        return $this;
    }

    /**
     * Add a LEFT JOIN relationship.
     *
     * @param array<int, mixed> $otherData
     */
    public function leftJoin(array $otherData, string $localKey, string $operator, string $foreignKey): self
    {
        return $this->join($otherData, $localKey, $operator, $foreignKey, 'left');
    }

    /**
     * Add an ORDER BY clause.
     */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orders[] = [
            'column' => $column,
            'direction' => strtoupper($direction),
        ];
        return $this;
    }

    /**
     * Set LIMIT and OFFSET.
     */
    public function limit(int $limit, int $offset = 0): self
    {
        $this->limit = $limit;
        $this->offset = $offset;
        return $this;
    }

    /**
     * Add a GROUP BY clause.
     */
    public function groupBy(string $column): self
    {
        $this->groupBy = $column;
        return $this;
    }

    /**
     * Execute the query and return a Collection.
     *
     * @return Collection<array-key, mixed>
     */
    public function get(): Collection
    {
        // 1. Resolve Joins
        $results = $this->data;
        if (!empty($this->joins)) {
            $results = JoinResolver::resolve($results, $this->joins);
        }

        // 2. Filter Wheres
        if (!empty($this->wheres)) {
            $results = array_filter($results, fn($row) => $this->evaluateRowWheres($row));
        }

        // 3. Sorting
        if (!empty($this->orders)) {
            usort($results, function ($a, $b) {
                foreach ($this->orders as $order) {
                    $column = $order['column'];
                    $direction = $order['direction'];

                    $valA = Helper::getValue($a, $column);
                    $valB = Helper::getValue($b, $column);

                    if ($valA === $valB) {
                        continue;
                    }

                    if ($valA === null) {
                        return $direction === 'DESC' ? 1 : -1;
                    }
                    if ($valB === null) {
                        return $direction === 'DESC' ? -1 : 1;
                    }

                    $comparison = 0;
                    if (is_string($valA) && is_string($valB)) {
                        $comparison = strcasecmp($valA, $valB);
                    } else {
                        $comparison = $valA <=> $valB;
                    }

                    return $direction === 'DESC' ? -$comparison : $comparison;
                }
                return 0;
            });
        }

        // 4. Grouping
        if ($this->groupBy !== null) {
            $grouped = [];
            foreach ($results as $row) {
                $rawKey = Helper::getValue($row, $this->groupBy, 'null');
                $groupKey = is_scalar($rawKey) ? (string) $rawKey : 'null';
                $grouped[$groupKey][] = $row;
            }

            // Map groups to Collections
            $results = [];
            foreach ($grouped as $key => $groupRows) {
                $processedGroup = [];
                foreach ($groupRows as $row) {
                    $processedGroup[] = $this->applySelect($row);
                }
                $results[$key] = new Collection($processedGroup);
            }

            /** @var Collection<array-key, mixed> $col */
            $col = new Collection($results);
            return $col;
        }

        // 5. Select & Project Columns
        $finalResults = [];
        foreach ($results as $row) {
            $finalResults[] = $this->applySelect($row);
        }

        // 6. Limit & Offset
        if ($this->limit !== null || $this->offset !== null) {
            $finalResults = array_slice($finalResults, $this->offset ?? 0, $this->limit);
        }

        /** @var Collection<array-key, mixed> $col */
        $col = new Collection($finalResults);
        return $col;
    }

    /**
     * Get the first matching row.
     */
    public function first(): mixed
    {
        $this->limit(1);
        $collection = $this->get();
        return $collection->first();
    }

    /**
     * Apply select projection to a row.
     *
     * @return array<string, mixed>|object
     */
    protected function applySelect(mixed $row): array|object
    {
        if (empty($this->selects) || in_array('*', $this->selects, true)) {
            if (is_array($row)) {
                /** @var array<string, mixed> $row */
                return $row;
            }
            if (is_object($row)) {
                return $row;
            }
            return [];
        }

        $parsedSelects = $this->parseSelects();
        /** @var array<string, mixed> $projected */
        $projected = [];

        foreach ($parsedSelects as $alias => $column) {
            $projected[$alias] = Helper::getValue($row, $column);
        }

        return is_object($row) ? (object) $projected : $projected;
    }

    /**
     * Parse select statement supporting "as" alias and array keys.
     *
     * @return array<string, string>
     */
    protected function parseSelects(): array
    {
        $parsed = [];
        foreach ($this->selects as $key => $column) {
            if (is_string($key)) {
                if (is_string($column)) {
                    $parsed[$key] = $column;
                }
            } else {
                if (is_string($column)) {
                    if (preg_match('/^\s*(.+?)\s+as\s+(.+?)\s*$/i', $column, $matches)) {
                        $parsed[$matches[2]] = $matches[1];
                    } else {
                        $parsed[$column] = $column;
                    }
                }
            }
        }
        return $parsed;
    }

    /**
     * Evaluates a row against the local where conditions.
     */
    protected function evaluateRowWheres(mixed $row): bool
    {
        return $this->evaluateConditions($row, $this->wheres);
    }

    /**
     * Evaluates a list of conditions against a row.
     *
     * @param array<int, array{type: string, column?: string|Closure, operator?: string, value?: mixed, boolean: string, query?: Closure}> $conditions
     */
    protected function evaluateConditions(mixed $row, array $conditions): bool
    {
        $result = true;

        foreach ($conditions as $index => $condition) {
            $boolean = $condition['boolean'];

            if ($condition['type'] === 'nested') {
                $subBuilder = new self([]);
                /** @var Closure(QueryBuilder): void $subQueryClosure */
                $subQueryClosure = $condition['query'] ?? fn() => null;
                $subQueryClosure($subBuilder);
                $match = $this->evaluateConditions($row, $subBuilder->getWheres());
            } else {
                /** @var string $col */
                $col = isset($condition['column']) && is_string($condition['column']) ? $condition['column'] : '';
                /** @var string $op */
                $op = $condition['operator'] ?? '=';
                $val = $condition['value'] ?? null;
                $match = Evaluator::evaluate(
                    $row,
                    $col,
                    $op,
                    $val
                );
            }

            if ($index === 0) {
                $result = $match;
            } else {
                if ($boolean === 'OR') {
                    $result = $result || $match;
                } else {
                    $result = $result && $match;
                }
            }
        }

        return $result;
    }
}
