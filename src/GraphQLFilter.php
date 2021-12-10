<?php

declare(strict_types=1);

namespace Simexis\GraphQLFilter;

use GraphQL;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\Type;
use ReflectionClass;
use ReflectionException;
use Simexis\Filterable\Filterable;
use Illuminate\Database\Eloquent\Relations;
use Illuminate\Support\Collection;

class GraphQLFilter
{
    /**
     * @param $className
     * @param array|null $only
     * @param array $overrides
     * @return array
     * @throws ReflectionException
     */
    static function fieldsFromModel($className, array $only = null, array $overrides = []): array
    {
        $model = new $className;
        $modelName = self::getModelName($model);
        $typeName = self::getTypeName($model);
        $fields = Collection::make();
        $only = $only === null ? null : Collection::make($only);
        foreach ($model->getFilterable() as $field => $rules) {
            if ($only !== null && !$only->contains($field)) {
                continue;
            }
            if (array_key_exists($field, $overrides) && $overrides[$field] === null) {
                continue;
            }
            if ($rules instanceof Relations\Relation) {
                $relationName = self::getModelName($rules->getModel());
                $relationType = self::getTypeName($rules->getModel());
                $fields[$field] = [
                    'type' => GraphQL::Type($relationType),
                    'description' => 'Filter by relationship to ' . $relationName
                ];
                continue;
            }
            $type = Collection::make($rules)->reduce(function ($type, $rule) {
                return $type === null ? (Filterable::isFilterableType($rule) ? $rule::type : null) : $type;
            });
            if (isset($overrides[$field]) && isset($overrides[$field]['type'])) {
                $type = $overrides[$field]['type'];
                unset($overrides[$field]['type']);
            } else {
                $type = self::filterableTypeToGraphQLType($type);
            }
            if ($type === null) {
                continue;
            }
            $rules = Collection::make($rules)->map(function ($rule) {
                return Filterable::isFilterableType($rule) ? $rule::defaultRules() : $rule;
            })->flatten()->unique();
            foreach ($rules as $index => $rule) {
                $key = "${field}_$rule";
                $not = "${field}_NOT_$rule";
                if (array_key_exists($key, $overrides) && $overrides[$key] === null) {
                    continue;
                }
                if ($index === 0) {
                    $k = $field;
                    $nk = "${field}_NOT";
                } else {
                    $k = $key;
                    $nk = $not;
                }
                $fieldType = $type;
                if ($rule === Filterable::IN) {
                    $fieldType = Type::listOf($type);
                } elseif ($rule === Filterable::NULL) {
                    $fieldType = Type::boolean();
                }
                $fieldDefinition = [
                    'type' => $fieldType,
                    'description' => "Filter $modelName $field using $rule rule"
                ];
                $fields[$k] = Collection::make($fieldDefinition)
                    ->merge($overrides[$field] ?? null)
                    ->merge($overrides[$key] ?? null);
                if (method_exists($className, 'scopeFilterNot' . $rule)) {
                    $fieldDefinition = [
                        'type' => $fieldType,
                        'description' => "Filter $modelName $field using negated $rule rule"
                    ];
                    $fields[$nk] = Collection::make($fieldDefinition)
                        ->merge($overrides[$field] ?? null)
                        ->merge($overrides[$key] ?? null);
                }
            }
        }
        $fields = $fields->merge([
            'AND' => [
                'type' => Type::listOf(GraphQL::Type($typeName)),
                'description' => 'Nested logical AND of filter parameters'
            ],
            'OR' => [
                'type' => Type::listOf(GraphQL::Type($typeName)),
                'description' => 'Nested logical OR of filter parameters'
            ],
            'NOT' => [
                'type' => Type::listOf(GraphQL::Type($typeName)),
                'description' => 'Nested logical NOT of filter parameters (elements are AND\'ed)'
            ],
            'NOR' => [
                'type' => Type::listOf(GraphQL::Type($typeName)),
                'description' => 'Nested logical NOT of filter parameters (elements are OR\'ed)'
            ],
        ]);
        return $fields->toArray();
    }

    /**
     * @param $model
     * @return string
     * @throws ReflectionException
     */
    static function getModelName($model): string
    {
        return (new ReflectionClass($model))->getShortName();
    }

    /**
     * @param $model
     * @return string
     * @throws ReflectionException
     */
    static function getTypeName($model): string
    {
        return self::getModelName($model) . 'Filter';
    }

    static function filterableTypeToGraphQLType($filterableType): ?ScalarType
    {
        switch ($filterableType) {
            case 'String':
            case 'Text':
            case 'Date':
            case 'Enum':
                return Type::string();
            case 'Integer':
                return Type::int();
            case 'Numeric':
                return Type::float();
            case 'Boolean':
                return Type::boolean();
            default:
                return null;
        }
    }
}
