# laravel-filter-builder

Easily build Eloquent queries from API request parameters — filters, sorts, joins, eager loads, and more — with zero boilerplate.

---

## Table of contents

- [Installation](#installation)
- [Quick start](#quick-start)
- [FilterConfig — ad-hoc configuration](#filterconfig--ad-hoc-configuration)
- [FilterForm — class-based configuration](#filterform--class-based-configuration)
- [Filter formulas reference](#filter-formulas-reference)
- [Sorts](#sorts)
- [Joins](#joins)
- [Select columns](#select-columns)
- [Eager loading (with)](#eager-loading-with)
- [Custom formulas](#custom-formulas)
- [Array input keys](#array-input-keys)
- [Swapping handler classes](#swapping-handler-classes)
- [Strict mode](#strict-mode)
- [Publishing & customising the config file](#publishing--customising-the-config-file)
- [Full real-world example](#full-real-world-example)

---

## Installation

```bash
composer require anhtt/laravel-filter-builder
```

The service provider is auto-discovered. No manual registration needed.

---

## Quick start

### 1. Add the trait to your model

```php
// app/Models/User.php
use AnhTT\FilterBuilder\Filterable;

class User extends Authenticatable
{
    use Filterable;
}
```

### 2. Build and apply a filter

```php
// app/Http/Controllers/UserController.php
use AnhTT\FilterBuilder\FilterConfig;

public function index(Request $request)
{
    $filterConfig = (new FilterConfig())
        ->setFilters([
            'name'  => 'users.name:cn',
            'email' => 'users.email:eq',
        ])
        ->setSorts([
            'name'  => 'users.name',
            'email' => 'users.email',
        ])
        ->setDefaultSort('id:desc');

    return User::filterBuilder(
        $request->all(),
        $filterConfig,
        $request->input('sort', '')
    )->paginate();
}
```

**Request:** `GET /users?name=john&sort=name:asc`

---

## FilterConfig — ad-hoc configuration

Use `FilterConfig` directly when the filter logic is simple or one-off.

```php
use AnhTT\FilterBuilder\FilterConfig;

$filterConfig = (new FilterConfig())
    ->setFilters([
        // column:formula
        'id'           => 'users.id:eq',
        'name'         => 'users.name:cn',
        'email'        => 'users.email:eq',
        'status'       => 'users.status:in',
        'age_from'     => 'users.age:gte',
        'age_to'       => 'users.age:lte',
        'created_from' => 'users.created_at:gte',

        // OR-group: matches name OR email
        'keyword' => [
            'users.name:cn',
            'users.email:cn',
        ],

        // Custom callable for complex logic
        'active_premium' => function ($query, $value) {
            $query->where('users.status', 'active')
                  ->where('users.plan', 'premium');
        },
    ])
    ->setSorts([
        'id'         => 'users.id',
        'name'       => 'users.name',
        'created_at' => 'users.created_at',
    ])
    ->setJoins([
        // Simple format (uses default_join_type from config)
        'orders' => ['orders', 'orders.user_id', '=', 'users.id'],

        // Extended format — specify join type explicitly
        'profiles' => [
            'type' => 'leftJoin',
            'args' => ['profiles', 'profiles.user_id', '=', 'users.id'],
        ],

        // Join with a closure (complex ON condition)
        'roles' => [
            'type' => 'leftJoin',
            'args' => ['roles', function ($join) {
                $join->on('roles.id', '=', 'users.role_id')
                     ->where('roles.active', 1);
            }],
        ],
    ])
    ->setJoinPriority([
        // 'order_items' requires 'orders' to be joined first
        'order_items' => ['orders'],
    ])
    ->setDefaultSort('created_at:desc');
```

---

## FilterForm — class-based configuration

Extend `FilterForm` for reusable, testable filter definitions. Override only the methods you need.

```php
// app/Filters/UserFilterForm.php
namespace App\Filters;

use AnhTT\FilterBuilder\FilterForm;

class UserFilterForm extends FilterForm
{
    protected function filters(): array
    {
        return [
            'id'           => 'users.id:eq',
            'name'         => 'users.name:cn',
            'email'        => 'users.email:eq',
            'status'       => 'users.status:in',
            'role'         => 'roles.name:eq',

            // OR-group across columns
            'search' => [
                'users.name:cn',
                'users.email:cn',
            ],

            // Custom logic
            'verified' => function ($query, $value) {
                if ($value) {
                    $query->whereNotNull('users.email_verified_at');
                } else {
                    $query->whereNull('users.email_verified_at');
                }
            },
        ];
    }

    protected function sorts(): array
    {
        return [
            'id'         => 'users.id',
            'name'       => 'users.name',
            'created_at' => 'users.created_at',
        ];
    }

    protected function joins(): array
    {
        return [
            'roles' => [
                'type' => 'leftJoin',
                'args' => ['roles', 'roles.id', '=', 'users.role_id'],
            ],
        ];
    }

    protected function joinPriorities(): array
    {
        return [];
    }

    protected function defaultSort(): string
    {
        return 'created_at:desc';
    }
}
```

**Usage in controller:**

```php
use App\Filters\UserFilterForm;

public function index(Request $request)
{
    $filterConfig = new UserFilterForm();

    return User::filterBuilder(
        $request->all(),
        $filterConfig,
        $request->input('sort', '')
    )->paginate();
}
```

---

## Filter formulas reference

Formulas are specified as `column:formula` in the filters array.

### Comparison

| Formula | SQL equivalent          | Example value |
|---------|-------------------------|---------------|
| `eq`    | `WHERE col = ?`         | `"active"`    |
| `ne`    | `WHERE col != ?`        | `"banned"`    |
| `gt`    | `WHERE col > ?`         | `18`          |
| `gte`   | `WHERE col >= ?`        | `18`          |
| `lt`    | `WHERE col < ?`         | `100`         |
| `lte`   | `WHERE col <= ?`        | `100`         |
| `col`   | `WHERE col = otherCol`  | `"orders.discount"` |

### Array membership

| Formula | SQL equivalent              | Example value  |
|---------|-----------------------------|----------------|
| `in`    | `WHERE col IN (?)`          | `[1, 2, 3]`    |
| `ni`    | `WHERE col NOT IN (?)`      | `[4, 5]`       |

### Null checks

| Formula    | SQL equivalent          | Example value       |
|------------|-------------------------|---------------------|
| `null`     | `WHERE col IS NULL`     | *(any truthy value)* |
| `not_null` | `WHERE col IS NOT NULL` | *(any truthy value)* |

### String / LIKE

| Formula | SQL equivalent                   | Example value |
|---------|----------------------------------|---------------|
| `cn`    | `WHERE col LIKE '%value%'`       | `"john"`      |
| `ncn`   | `WHERE col NOT LIKE '%value%'`   | `"spam"`      |
| `sw`    | `WHERE col LIKE 'value%'`        | `"jo"`        |
| `ew`    | `WHERE col LIKE '%value'`        | `"hn"`        |

### Range  `{from, to}`

| Formula | SQL equivalent                                        | Example value |
|---------|-------------------------------------------------------|---------------|
| `bw`    | `WHERE col >= from AND col <= to`                     | `{"from":1,"to":100}` |
| `dbw`   | `WHERE col >= from AND col <= to` *(parsed as dates)* | `{"from":"2024-01-01","to":"2024-12-31"}` |

### Date parts  *(on DATETIME / TIMESTAMP columns)*

| Formula | SQL equivalent            | Example value  |
|---------|---------------------------|----------------|
| `date`  | `WHERE DATE(col) = ?`     | `"2024-06-15"` |
| `year`  | `WHERE YEAR(col) = ?`     | `2024`         |
| `month` | `WHERE MONTH(col) = ?`    | `6`            |
| `day`   | `WHERE DAY(col) = ?`      | `15`           |
| `time`  | `WHERE TIME(col) = ?`     | `"08:00:00"`   |

### JSON columns  *(MySQL 5.7+ / PostgreSQL jsonb)*

| Formula | SQL equivalent                    | Example value       |
|---------|-----------------------------------|---------------------|
| `json`  | `WHERE JSON_CONTAINS(col, value)` | `"tag"` or `["a","b"]` |

---

### Examples

```php
->setFilters([
    // Exact match / not equal
    'status'       => 'users.status:eq',
    'blocked'      => 'users.status:ne',

    // Contains / NOT contains
    'name'         => 'users.name:cn',
    'name_exclude' => 'users.name:ncn',

    // Numeric range: GET /users?age[from]=18&age[to]=30
    'age'          => 'users.age:bw',

    // Date range: GET /users?created[from]=2024-01-01&created[to]=2024-12-31
    'created'      => 'users.created_at:dbw',

    // Exact date (ignores time): GET /users?birthday=1990-05-20
    'birthday'     => 'users.birth_date:date',

    // Date parts: GET /users?year=2024&month=6
    'year'         => 'users.created_at:year',
    'month'        => 'users.created_at:month',

    // Filter by list: GET /users?role_ids[]=1&role_ids[]=2
    'role_ids'     => 'users.role_id:in',

    // Null check: GET /users?deleted=1
    'deleted'      => 'users.deleted_at:not_null',
    'active'       => 'users.deleted_at:null',

    // JSON column: GET /products?tag=electronics
    'tag'          => 'products.tags:json',

    // Column-to-column: rows where discount < price
    // GET /orders?check_discount=orders.price
    'check_discount' => 'orders.discount:col',
])
```

### OR-group (matches any of the columns)

```php
->setFilters([
    // GET /users?q=john  → WHERE (name LIKE '%john%' OR email LIKE '%john%')
    'q' => [
        'users.name:cn',
        'users.email:cn',
    ],
])
```

### Callable filter (full control)

The callable receives `($query, $value, $filterConfig)`.

```php
->setFilters([
    'active_premium' => function ($query, $value, $filterConfig) {
        $query->where('users.status', 'active')
              ->where('users.plan', 'premium');
    },
])
```

---

## Sorts

```php
->setSorts([
    'id'         => 'users.id',           // simple column
    'name'       => 'users.name',
    'role_name'  => 'roles.name',         // column from a joined table

    // Callable sort for computed/conditional ordering
    'priority' => function (string $direction, FilterConfig $filterConfig) {
        return function ($query) use ($direction) {
            $query->orderByRaw("FIELD(status, 'active', 'pending', 'inactive') {$direction}");
        };
    },
])
->setDefaultSort('created_at:desc')
```

**Request:** `GET /users?sort=name:asc,created_at:desc`

Multiple sort fields are comma-separated and applied in order. If a field is not in the `sorts` map it is silently ignored. If direction is omitted, falls back to `default_sort_direction` in config (default: `desc`).

---

## Joins

### Simple format

Uses the `default_join_type` from config (default: `leftJoin`).

```php
->setJoins([
    'profiles' => ['profiles', 'profiles.user_id', '=', 'users.id'],
    'orders'   => ['orders',   'orders.user_id',   '=', 'users.id'],
])
```

### Extended format — explicit join type

```php
->setJoins([
    'profiles' => [
        'type' => 'leftJoin',
        'args' => ['profiles', 'profiles.user_id', '=', 'users.id'],
    ],
    'payments' => [
        'type' => 'join',       // INNER JOIN
        'args' => ['payments', 'payments.order_id', '=', 'orders.id'],
    ],
])
```

Accepted `type` values: `join` | `leftJoin` | `rightJoin`.

### Closure ON condition

```php
->setJoins([
    'roles' => [
        'type' => 'leftJoin',
        'args' => ['roles', function ($join) {
            $join->on('roles.id', '=', 'users.role_id')
                 ->where('roles.deleted_at', null);
        }],
    ],
])
```

### Join priorities (dependency chain)

When joining table A requires table B to already be joined:

```php
->setJoins([
    'orders'      => ['orders',      'orders.user_id',    '=', 'users.id'],
    'order_items' => ['order_items', 'order_items.order_id', '=', 'orders.id'],
    'products'    => ['products',    'products.id',       '=', 'order_items.product_id'],
])
->setJoinPriority([
    'order_items' => ['orders'],               // join orders first
    'products'    => ['order_items', 'orders'], // join both first
])
```

Joins are only added when a filter or sort actually references a column from that table — no unnecessary joins.

---

## Select columns

Restrict the columns returned by the query.

```php
$filterConfig = (new UserFilterForm())
    ->setSelects([
        'users.id',
        'users.name',
        'users.email',
        'roles.name as role_name',
    ]);
```

When `setSelects` is not called (or called with an empty array), the query defaults to `SELECT *`.

---

## Eager loading (with)

Load relationships alongside the query result.

```php
$filterConfig = (new UserFilterForm())
    ->setWith(['profile', 'roles', 'orders' => function ($query) {
        $query->where('status', 'completed');
    }]);
```

---

## Custom formulas

### Per-config (applies to one FilterConfig / FilterForm instance)

```php
$filterConfig = (new FilterConfig())
    ->addFormula('year', function ($query, $column, $value) {
        return $query->whereYear($column, $value);
    })
    ->addFormula('month', function ($query, $column, $value) {
        return $query->whereMonth($column, $value);
    })
    ->setFilters([
        'birth_year'  => 'users.birth_date:year',
        'birth_month' => 'users.birth_date:month',
    ]);
```

### Inside FilterForm

```php
class UserFilterForm extends FilterForm
{
    public function __construct()
    {
        parent::__construct();

        $this->addFormula('year', fn ($q, $col, $val) => $q->whereYear($col, $val));
    }

    protected function filters(): array
    {
        return [
            'birth_year' => 'users.birth_date:year',
        ];
    }
}
```

### Global formulas via config file

Add formulas to `config/filter-builder.php` and they are available in every `FilterConfig` instance. These are merged **after** built-ins, so they can also override a built-in formula.

```php
// config/filter-builder.php
'formulas' => [
    'year'  => fn ($query, $column, $value) => $query->whereYear($column, $value),
    'month' => fn ($query, $column, $value) => $query->whereMonth($column, $value),
    'day'   => fn ($query, $column, $value) => $query->whereDay($column, $value),

    // Override the built-in 'cn' to use case-insensitive ILIKE on PostgreSQL
    'cn'    => fn ($query, $column, $value) => $query->whereRaw("lower({$column}) like ?", ['%' . strtolower($value) . '%']),
],
```

**Formula priority (highest wins):**

```
built-ins  <  config('filter-builder.formulas')  <  FilterConfig::addFormula()
```

---

## Array input keys

Some APIs send groups of filters under wrapper keys, e.g.:

```json
{
  "keywords": [
    { "name": "status", "value": "active" },
    { "name": "role",   "value": "admin"  }
  ],
  "periods": [
    { "name": "created", "value": { "from": "2024-01-01", "to": "2024-12-31" } }
  ]
}
```

The package unwraps these automatically. Default wrapper keys are `keywords` and `periods`.

**Override globally** in `config/filter-builder.php`:

```php
'array_input_keys' => ['keywords', 'periods', 'ranges'],
```

**Override per-config:**

```php
$filterConfig->setArrayInputKeys(['keywords', 'periods', 'conditions']);
```

---

## Swapping handler classes

The three internal handlers — `FilterWhere`, `FilterSort`, `FilterJoin` — are resolved through the **Laravel service container**. You can replace any of them globally without touching the package source.

### Option A — via config file

```php
// config/filter-builder.php
'handlers' => [
    'where' => \App\FilterBuilder\MyFilterWhere::class,
    'sort'  => \AnhTT\FilterBuilder\FilterSort::class,   // keep default
    'join'  => \AnhTT\FilterBuilder\FilterJoin::class,   // keep default
],
```

### Option B — via service container (AppServiceProvider)

```php
use AnhTT\FilterBuilder\FilterWhere;
use App\FilterBuilder\MyFilterWhere;

public function register(): void
{
    $this->app->bind(FilterWhere::class, MyFilterWhere::class);
}
```

Your custom handler only needs to implement the same `getQueries()` signature:

```php
// app/FilterBuilder/MyFilterWhere.php
namespace App\FilterBuilder;

use AnhTT\FilterBuilder\FilterConfig;
use AnhTT\FilterBuilder\FilterWhere;

class MyFilterWhere extends FilterWhere
{
    public function getQueries(FilterConfig $filterConfig, array $requestData): array
    {
        // pre-process $requestData, then delegate to parent
        $requestData = $this->sanitize($requestData);
        return parent::getQueries($filterConfig, $requestData);
    }

    private function sanitize(array $data): array
    {
        // strip XSS, trim values, etc.
        return $data;
    }
}
```

---

## Strict mode

By default, an unknown formula (e.g. a typo like `column:equ`) is silently ignored. Enable strict mode to throw an `InvalidArgumentException` instead — helpful during development.

```php
// config/filter-builder.php
'strict_mode' => env('APP_DEBUG', false),
```

With strict mode on:

```php
->setFilters([
    'name' => 'users.name:typo',   // throws InvalidArgumentException
])
```

Error message:

```
Unknown filter formula "typo". Register it in config('filter-builder.formulas')
or via FilterConfig::addFormula().
```

---

## Publishing & customising the config file

```bash
php artisan vendor:publish --tag=filter-builder-config
```

This creates `config/filter-builder.php` with all available options:

```php
return [
    // Keys in the request treated as arrays of {name, value} filter items
    'array_input_keys' => ['keywords', 'periods'],

    // Default JOIN type: 'join' | 'leftJoin' | 'rightJoin'
    'default_join_type' => 'leftJoin',

    // Fallback sort direction when not specified in the request
    'default_sort_direction' => 'desc',

    // Throw on unknown formula instead of silently skipping
    'strict_mode' => env('APP_DEBUG', false),

    // Global formulas available in all FilterConfig instances
    // (merged after built-ins; per-config formulas have highest priority)
    'formulas' => [
        // 'year'  => fn ($q, $col, $val) => $q->whereYear($col, $val),
        // 'month' => fn ($q, $col, $val) => $q->whereMonth($col, $val),
    ],

    // Swappable handler classes (resolved via Laravel container)
    'handlers' => [
        'where' => \AnhTT\FilterBuilder\FilterWhere::class,
        'sort'  => \AnhTT\FilterBuilder\FilterSort::class,
        'join'  => \AnhTT\FilterBuilder\FilterJoin::class,
    ],
];
```

---

## Full real-world example

**Scenario:** Product listing with category/brand joins, range filters, keyword search, custom formula, selects, and eager loads.

```php
// app/Filters/ProductFilterForm.php
namespace App\Filters;

use AnhTT\FilterBuilder\FilterForm;

class ProductFilterForm extends FilterForm
{
    public function __construct()
    {
        parent::__construct();

        $this->addFormula('year', fn ($q, $col, $val) => $q->whereYear($col, $val));
    }

    protected function filters(): array
    {
        return [
            'name'         => 'products.name:cn',
            'sku'          => 'products.sku:eq',
            'status'       => 'products.status:in',
            'brand_id'     => 'products.brand_id:in',
            'category'     => 'categories.slug:eq',
            'price'        => 'products.price:bw',
            'created_year' => 'products.created_at:year',

            // OR-group: search name OR sku
            'search' => [
                'products.name:cn',
                'products.sku:cn',
            ],

            // Callable: stock filter
            'in_stock' => function ($query, $value) {
                if ($value) {
                    $query->where('products.stock', '>', 0);
                } else {
                    $query->where('products.stock', 0);
                }
            },
        ];
    }

    protected function sorts(): array
    {
        return [
            'name'       => 'products.name',
            'price'      => 'products.price',
            'created_at' => 'products.created_at',
            'category'   => 'categories.name',
        ];
    }

    protected function joins(): array
    {
        return [
            'categories' => [
                'type' => 'leftJoin',
                'args' => ['categories', 'categories.id', '=', 'products.category_id'],
            ],
            'brands' => [
                'type' => 'leftJoin',
                'args' => ['brands', 'brands.id', '=', 'products.brand_id'],
            ],
        ];
    }

    protected function defaultSort(): string
    {
        return 'created_at:desc';
    }
}
```

```php
// app/Http/Controllers/ProductController.php
use App\Filters\ProductFilterForm;

public function index(Request $request)
{
    $filterConfig = (new ProductFilterForm())
        ->setSelects([
            'products.id',
            'products.name',
            'products.sku',
            'products.price',
            'products.stock',
            'categories.name as category_name',
            'brands.name as brand_name',
        ])
        ->setWith(['images', 'tags']);

    return Product::filterBuilder(
        $request->all(),
        $filterConfig,
        $request->input('sort', '')
    )->paginate($request->input('per_page', 20));
}
```

**Example request:**

```
GET /products
  ?search=wireless
  &status[]=active
  &status[]=draft
  &price[from]=10
  &price[to]=500
  &sort=price:asc,name:asc
  &per_page=15
```

**Generated SQL (approximate):**

```sql
SELECT products.id, products.name, products.sku, products.price, products.stock,
       categories.name AS category_name, brands.name AS brand_name
FROM products
LEFT JOIN categories ON categories.id = products.category_id
LEFT JOIN brands ON brands.id = products.brand_id
WHERE (products.name LIKE '%wireless%' OR products.sku LIKE '%wireless%')
  AND products.status IN ('active', 'draft')
  AND products.price >= 10
  AND products.price <= 500
ORDER BY products.price ASC, products.name ASC
LIMIT 15 OFFSET 0
```

---

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
