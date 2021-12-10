# Gency\\GraphQLFilter

Define GraphQL schema for connection filtering using Laravel filterable models.

# Installation

```
composer require gency/laravel-graphql-filter
```

# Usage

Use Filterable trait on Model

```
namespace App;
use Gency\Filterable\Filterable;

class User extends Model
{
  use \Gency\Filterable\FilterableTrait;
  
  protected $filterable = [
    'name' => Filterable::String
  ];
}
```

Create GraphQL User type

```
namespace App\GraphQL\Type;

use App\User;
use GraphQL\Type\Definition\Type;
use Folklore\GraphQL\Support\Type as GraphQLType;

class User extends GraphQLType
{
  protected $attributes = [
    'name' => 'User'
  ];
  public function fields() {
    return [
      'id' => [
        'type' => Type::id(),
      ],
      'name' => [
        'type' => Type::string()
      ]
    ]
  }
}
```

Create GraphQL UserFilter type. The UserFilter fields defintion will be automatically populated with fields in `User::$filterable`.

```
namespace App\GraphQL\Type;

use App\User;
use Gency\GraphQLFilter\Type\FilterType;

class UserFilter extends FilterType
{
  protected $model = User::class;
}
```

Create GraphQL query type to list User records using the User model's filterable settings.

```
namespace App\GraphQL\Type;

use App\User;
use GraphQL;
use GraphQL\Type\Definition\Type;
use Folklore\GraphQL\Support\Query;

class UsersQuery extends Query
{
  protected $attributes = [
    'name' => 'users'
  ];
  public function type() {
    return Type::listOf(GraphQL::type('User'));
  }
  public function args() {
    return [
      'filter' => [
        'type' => GraphQL::type('UserFilter')
      ]
    ];
  }
  public function resolve($root, $args, $context) {
    return User::filter($args['filter'])->orderBy('name')->limit(10)->get();
  }
}
```

A GraphQL query can now use the `Filterable::String` rules to perform searches.

```
query ListJohns {
  users (filter: { name_MATCH: 'john' }) {
    name
  }
}
```
