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

| Formula    | SQL equivalent                                  | Example value         |
|------------|-------------------------------------------------|-----------------------|
| `eq`       | `WHERE column = ?`                              | `"john"`              |
| `ne`       | `WHERE column != ?`                             | `"banned"`            |
| `gt`       | `WHERE column > ?`                              | `18`                  |
| `gte`      | `WHERE column >= ?`                             | `18`                  |
| `lt`       | `WHERE column < ?`                              | `100`                 |
| `lte`      | `WHERE column <= ?`                             | `100`                 |
| `in`       | `WHERE column IN (?)`                           | `[1, 2, 3]`           |
| `ni`       | `WHERE column NOT IN (?)`                       | `[4, 5]`              |
| `null`     | `WHERE column IS NULL`                          | *(any truthy value)*  |
| `not_null` | `WHERE column IS NOT NULL`                      | *(any truthy value)*  |
| `cn`       | `WHERE column LIKE '%value%'`                   | `"john"`              |
| `sw`       | `WHERE column LIKE 'value%'`                    | `"jo"`                |
| `ew`       | `WHERE column LIKE '%value'`                    | `"hn"`                |
| `bw`       | `WHERE column >= from AND column <= to`         | `{"from":1,"to":10}`  |
| `dbw`      | `WHERE column >= from AND column <= to` (dates) | `{"from":"2024-01-01","to":"2024-12-31"}` |

### Examples

```php
->setFilters([
    // Exact match
    'status'       => 'users.status:eq',

    // Contains (LIKE %value%)
    'name'         => 'users.name:cn',

    // Numeric range: GET /users?age[from]=18&age[to]=30
    'age'          => 'users.age:bw',

    // Date range: GET /users?created[from]=2024-01-01&created[to]=2024-12-31
    'created'      => 'users.created_at:dbw',

    // Filter by list: GET /users?role_ids[]=1&role_ids[]=2
    'role_ids'     => 'users.role_id:in',

    // Null check: GET /users?deleted=1
    'deleted'      => 'users.deleted_at:not_null',
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
    ->setWiths(['profile', 'roles', 'orders' => function ($query) {
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

Add formulas to `config/filter-builder.php` and they are available in every `FilterConfig` instance:

```php
// config/filter-builder.php
'custom_formulas' => [
    'year'  => fn ($query, $column, $value) => $query->whereYear($column, $value),
    'month' => fn ($query, $column, $value) => $query->whereMonth($column, $value),
],
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

## Publishing & customising the config file

```bash
php artisan vendor:publish --tag=filter-builder-config
```

This creates `config/filter-builder.php`:

```php
return [
    // Keys in the request treated as arrays of {name, value} filter items
    'array_input_keys' => ['keywords', 'periods'],

    // Default JOIN type: 'join' | 'leftJoin' | 'rightJoin'
    'default_join_type' => 'leftJoin',

    // Fallback sort direction when not specified in the request
    'default_sort_direction' => 'desc',

    // Global custom formulas available in all FilterConfig instances
    'custom_formulas' => [],
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
        ->setWiths(['images', 'tags']);

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
