# Build Filter from API requests


## Basic usage
### Installation

```composer require anhtt/laravel-filter-builder:dev-main```

### Filter a query based on a request: `/users?name=John`:

```php
use AnhTT\FilterBuilder\FilterConfig;

    $filterConfig->setFilters([
            'id' => 'users.id:cn',
            'name' => [
                'users.id:eq',
                'users.name:cn',
            ],
            'email' => 'users.email:eq',
            'email_and_name' => function (Builder $builder, $value) {
                $builder->where('users.name', '=', $value);
                $builder->where('users.email', '=', $value);
            },
            'color_name' => 'colors.name:eq',
            'product_name' => 'products.name:cn',
        ])->setSorts([
            'id' => 'users.id',
            'name' => 'users.name',
        ])->setJoins([
            'products' => ['products', 'products.user_id', '=', 'users.id', 'left'],
            'colors' => ['colors', function (JoinClause $join) {
                $join->on('colors.id', '=', 'products.color_id');
            }],
        ])->setJoinPriority([
            'color_name' => ['products']
        ])->setDefaultSort('id:desc');

// all `User`s that contain the string "John" in their name
```
```
use AnhTT\FilterBuilder\FilterConfig;

   $filterConfig = new UserFilterForm();
   return User::filterBuilder($requestData, $filterConfig, request('sort'))->paginate();
```

```
<?php

namespace App\Filters;

use Illuminate\Database\Query\JoinClause;
use AnhTT\FilterBuilder\FilterForm;

class UserFilterForm extends FilterForm
{

    public function filters(): array
    {
        return [
            'id' => 'users.id:eq',
            'name' => [
                'users.id:eq',
                'users.name:cn',
            ],
            'email' => 'users.email:eq',
            'category_name' => 'categories.name:eq',
            'color_name' => 'colors.name:eq',
            'product_name' => 'products.name:cn',
        ];
    }

    /**
     * @return array
     */
    public function getJoins(): array
    {
        return [
            'products' => ['products', 'products.user_id', '=', 'users.id', 'left'],
            'colors' => ['colors', function (JoinClause $join) {
                $join->on('colors.id', '=', 'products.color_id');
            }],
        ];
    }

    public function getSorts(): array
    {
        return [
            'id' => 'users.id',
        ];
    }

    public function getJoinPriority(): array
    {
        return [
            'colors' => ['products']
        ];
    }

    public function defaultSort(): string
    {
        return 'id:desc';
    }
}
```
## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.