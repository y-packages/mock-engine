<?php

declare(strict_types=1);

namespace YakNet\MockEngine\Tests;

use PHPUnit\Framework\TestCase;
use YakNet\MockEngine\QueryBuilder;
use YakNet\MockEngine\Collection;

class QueryBuilderTest extends TestCase
{
    private array $users;
    private array $posts;

    protected function setUp(): void
    {
        $this->users = [
            [
                'id' => 1,
                'name' => 'John Doe',
                'age' => 25,
                'role' => 'admin',
                'status' => 'active',
                'profile' => [
                    'city' => 'Istanbul',
                    'verified' => true
                ]
            ],
            [
                'id' => 2,
                'name' => 'Jane Smith',
                'age' => 30,
                'role' => 'user',
                'status' => 'active',
                'profile' => [
                    'city' => 'Ankara',
                    'verified' => false
                ]
            ],
            [
                'id' => 3,
                'name' => 'Bob Johnson',
                'age' => 17,
                'role' => 'user',
                'status' => 'inactive',
                'profile' => [
                    'city' => 'Izmir',
                    'verified' => true
                ]
            ],
            [
                'id' => 4,
                'name' => 'Alice Williams',
                'age' => 35,
                'role' => 'admin',
                'status' => 'active',
                'profile' => [
                    'city' => 'Istanbul',
                    'verified' => false
                ]
            ]
        ];

        $this->posts = [
            ['id' => 101, 'user_id' => 1, 'title' => 'First Post'],
            ['id' => 102, 'user_id' => 1, 'title' => 'Second Post'],
            ['id' => 103, 'user_id' => 2, 'title' => 'Hello World'],
        ];
    }

    public function testBasicWhereFilters(): void
    {
        $builder = new QueryBuilder($this->users);

        // Equals
        $results = $builder->where('role', 'admin')->get();
        $this->assertCount(2, $results);
        $this->assertEquals('John Doe', $results[0]['name']);
        $this->assertEquals('Alice Williams', $results[1]['name']);

        // Greater than
        $results = (new QueryBuilder($this->users))->where('age', '>', 25)->get();
        $this->assertCount(2, $results); // Jane (30), Alice (35)

        // Point notation
        $results = (new QueryBuilder($this->users))->where('profile.city', '=', 'Istanbul')->get();
        $this->assertCount(2, $results);
    }

    public function testOperators(): void
    {
        // LIKE
        $results = (new QueryBuilder($this->users))->where('name', 'LIKE', 'Jane%')->get();
        $this->assertCount(1, $results);
        $this->assertEquals('Jane Smith', $results[0]['name']);

        // LIKE case-insensitive
        $results = (new QueryBuilder($this->users))->where('name', 'like', '%doe%')->get();
        $this->assertCount(1, $results);

        // IN
        $results = (new QueryBuilder($this->users))->where('role', 'IN', ['admin', 'super'])->get();
        $this->assertCount(2, $results);

        // BETWEEN
        $results = (new QueryBuilder($this->users))->where('age', 'BETWEEN', [20, 30])->get();
        $this->assertCount(2, $results); // John (25), Jane (30)
    }

    public function testNestedWheres(): void
    {
        $results = (new QueryBuilder($this->users))
            ->where('status', 'active')
            ->where(function (QueryBuilder $query) {
                $query->where('role', 'admin')
                      ->orWhere('profile.verified', true);
            })
            ->get();

        // Should match:
        // John Doe (active, admin, verified=true) - Yes
        // Jane Smith (active, user, verified=false) - No
        // Alice Williams (active, admin, verified=false) - Yes
        $this->assertCount(2, $results);
        $this->assertEquals('John Doe', $results[0]['name']);
        $this->assertEquals('Alice Williams', $results[1]['name']);
    }

    public function testSelectAndAliases(): void
    {
        $results = (new QueryBuilder($this->users))
            ->select('id', 'name', 'profile.city as city_name')
            ->where('id', 1)
            ->get();

        $this->assertCount(1, $results);
        $row = $results[0];
        $this->assertArrayHasKey('id', $row);
        $this->assertArrayHasKey('name', $row);
        $this->assertArrayHasKey('city_name', $row);
        $this->assertEquals('Istanbul', $row['city_name']);
        $this->assertArrayNotHasKey('profile', $row);
    }

    public function testOrderByAndLimit(): void
    {
        $results = (new QueryBuilder($this->users))
            ->orderBy('age', 'DESC')
            ->limit(2)
            ->get();

        $this->assertCount(2, $results);
        $this->assertEquals('Alice Williams', $results[0]['name']); // 35
        $this->assertEquals('Jane Smith', $results[1]['name']); // 30
    }

    public function testInnerJoin(): void
    {
        $results = (new QueryBuilder($this->users))
            ->select('name', 'title')
            ->join($this->posts, 'id', '=', 'user_id')
            ->get();

        // 2 posts from John, 1 from Jane = 3 total joined rows
        $this->assertCount(3, $results);
        $this->assertEquals('John Doe', $results[0]['name']);
        $this->assertEquals('First Post', $results[0]['title']);
    }

    public function testLeftJoin(): void
    {
        $results = (new QueryBuilder($this->users))
            ->select('name', 'title')
            ->leftJoin($this->posts, 'id', '=', 'user_id')
            ->get();

        // All 4 users should be present, Bob and Alice should have NULL/empty title
        $this->assertCount(5, $results); // John (2 posts) + Jane (1 post) + Bob + Alice = 5 rows
    }

    public function testGroupBy(): void
    {
        $results = (new QueryBuilder($this->users))
            ->groupBy('profile.city')
            ->get();

        // Istanbul (2), Ankara (1), Izmir (1)
        $this->assertCount(3, $results);
        $this->assertInstanceOf(Collection::class, $results['Istanbul']);
        $this->assertCount(2, $results['Istanbul']);
    }

    public function testCollectionAggregations(): void
    {
        $collection = new Collection($this->users);

        $this->assertEquals(4, $collection->count());
        $this->assertEquals(107, $collection->sum('age'));
        $this->assertEquals(26.75, $collection->avg('age'));
        $this->assertEquals(17, $collection->min('age'));
        $this->assertEquals(35, $collection->max('age'));
        $this->assertEquals(['Istanbul', 'Ankara', 'Izmir', 'Istanbul'], $collection->pluck('profile.city'));
    }

    public function testNullFilters(): void
    {
        $testData = [
            ['id' => 1, 'name' => 'John', 'age' => 25, 'city' => 'Istanbul'],
            ['id' => 2, 'name' => 'Charlie', 'age' => null, 'city' => null],
        ];

        // whereNull
        $results = (new QueryBuilder($testData))->whereNull('age')->get();
        $this->assertCount(1, $results);
        $this->assertEquals('Charlie', $results[0]['name']);

        // whereNotNull
        $results = (new QueryBuilder($testData))->whereNotNull('age')->get();
        $this->assertCount(1, $results);
        $this->assertEquals('John', $results[0]['name']);

        // orWhereNull
        $results = (new QueryBuilder($testData))
            ->where('id', '=', 1)
            ->orWhereNull('city')
            ->get();
        $this->assertCount(2, $results);

        // orWhereNotNull
        $results = (new QueryBuilder($testData))
            ->where('id', '=', 2)
            ->orWhereNotNull('city')
            ->get();
        $this->assertCount(2, $results);
    }

    public function testInAndBetweenFilters(): void
    {
        $results = (new QueryBuilder($this->users))
            ->whereIn('profile.city', ['Istanbul', 'Izmir'])
            ->get();
        // John Doe (Istanbul), Bob Johnson (Izmir), Alice Williams (Istanbul) -> 3
        $this->assertCount(3, $results);

        $results = (new QueryBuilder($this->users))
            ->whereNotIn('profile.city', ['Ankara', 'Izmir'])
            ->get();
        // John Doe, Alice Williams -> 2
        $this->assertCount(2, $results);

        $results = (new QueryBuilder($this->users))
            ->whereBetween('age', [18, 30])
            ->get();
        // John Doe (25), Jane Smith (30) -> 2
        $this->assertCount(2, $results);

        $results = (new QueryBuilder($this->users))
            ->whereNotBetween('age', [20, 32])
            ->get();
        // Bob Johnson (17), Alice Williams (35) -> 2
        $this->assertCount(2, $results);

        // Test OR variants
        $results = (new QueryBuilder($this->users))
            ->where('profile.city', '=', 'Ankara')
            ->orWhereIn('profile.city', ['Izmir'])
            ->get();
        // Jane Smith (Ankara), Bob Johnson (Izmir) -> 2
        $this->assertCount(2, $results);

        $results = (new QueryBuilder($this->users))
            ->where('profile.city', '=', 'Ankara')
            ->orWhereNotIn('profile.city', ['Ankara', 'Izmir', 'Istanbul']) // non-existent
            ->get();
        $this->assertCount(1, $results);

        $results = (new QueryBuilder($this->users))
            ->where('profile.city', '=', 'Ankara')
            ->orWhereBetween('age', [34, 40])
            // Jane Smith (Ankara), Alice Williams (35) -> 2
            ->get();
        $this->assertCount(2, $results);

        $results = (new QueryBuilder($this->users))
            ->where('profile.city', '=', 'Ankara')
            ->orWhereNotBetween('age', [15, 32])
            // Jane Smith (Ankara), Alice Williams (35) -> 2
            ->get();
        $this->assertCount(2, $results);
    }
}
