# MockEngine

[![Latest Version on Packagist](https://img.shields.io/packagist/v/yaknet/mock-engine.svg?style=flat-square)](https://packagist.org/packages/yaknet/mock-engine)
[![Total Downloads](https://img.shields.io/packagist/dt/yaknet/mock-engine.svg?style=flat-square)](https://packagist.org/packages/yaknet/mock-engine)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)

**MockEngine** is a lightweight, pure PHP (PHP 8.1+) in-memory query engine that lets you filter, sort, join, group, and aggregate raw arrays or object lists using an elegant, Laravel/Symfony-style fluent Query Builder—completely without any database overhead!

It is perfect for unit testing, in-memory caching systems, sorting baskets in e-commerce, or managing nested datasets in microservices.

---

## Features

- ✨ **Fluent Query Builder:** Build queries using `where()`, `orWhere()`, `select()`, `orderBy()`, `limit()`, `groupBy()`, `join()`, and `leftJoin()`.
- 🎯 **Point Notation (Nested Access):** Query nested arrays or objects instantly, e.g., `where('profile.address.city', '=', 'Istanbul')`.
- ⚙️ **Advanced Operators:** Support for `=`, `!=`, `>`, `<`, `>=`, `<=`, `LIKE`, `NOT LIKE`, `IN`, `NOT IN`, `BETWEEN`, and `NOT BETWEEN`.
- 🔗 **Relation Joins:** Easily perform `INNER JOIN` and `LEFT JOIN` operations across different arrays or object collections.
- 🗃️ **Group By & Collections:** Group results automatically, and utilize the built-in `Collection` wrapper for powerful aggregations (`sum`, `avg`, `min`, `max`, `pluck`).
- 🚀 **Zero Dependencies:** Written entirely in pure, highly-optimized PHP.

---

## Installation

Install the package via Composer:

```bash
composer require yaknet/mock-engine
```

---

## Quick Start

### 1. Basic Filtering

```php
use YakNet\MockEngine\QueryBuilder;

$users = [
    ['id' => 1, 'name' => 'John Doe', 'role' => 'admin', 'profile' => ['city' => 'Istanbul']],
    ['id' => 2, 'name' => 'Jane Smith', 'role' => 'user', 'profile' => ['city' => 'Ankara']],
    ['id' => 3, 'name' => 'Bob Johnson', 'role' => 'user', 'profile' => ['city' => 'Izmir']],
];

// Query active admins in Istanbul
$results = QueryBuilder::from($users)
    ->select('id', 'name', 'profile.city as city')
    ->where('role', '=', 'user')
    ->where('profile.city', '!=', 'Ankara')
    ->get();

// Returns a Collection containing:
// [ ['id' => 3, 'name' => 'Bob Johnson', 'city' => 'Izmir'] ]
```

### 2. Complex & Nested Logical Groups (AND / OR)

You can group logical operations together by passing a `Closure` to `where` or `orWhere`:

```php
$results = QueryBuilder::from($users)
    ->where('status', '=', 'active')
    ->where(function (QueryBuilder $query) {
        $query->where('role', '=', 'admin')
              ->orWhere('profile.verified', '=', true);
    })
    ->get();
```

### 3. Joining Two Collections (INNER / LEFT JOIN)

```php
$posts = [
    ['id' => 101, 'user_id' => 1, 'title' => 'First Post'],
    ['id' => 102, 'user_id' => 1, 'title' => 'Second Post'],
    ['id' => 103, 'user_id' => 2, 'title' => 'Hello World'],
];

// Perform INNER JOIN
$results = QueryBuilder::from($users)
    ->select('name', 'title')
    ->join($posts, 'id', '=', 'user_id')
    ->get();
```

### 4. Grouping & Collection Aggregations

If you use `groupBy()`, the results will be grouped into a nested Collection of Collections:

```php
$grouped = QueryBuilder::from($users)
    ->groupBy('profile.city')
    ->get();

// Get the average age of users in Istanbul
$istanbulUsers = $grouped['Istanbul']; // This is a Collection!
$averageAge = $istanbulUsers->avg('age');
$maxAge = $istanbulUsers->max('age');
$names = $istanbulUsers->pluck('name'); // ['John Doe', ...]
```

---

## Running Tests

All features are fully verified with a comprehensive unit test suite:

```bash
vendor/bin/phpunit
```

## License

This project is licensed under the MIT License. See [LICENSE.md](LICENSE.md) for details.
