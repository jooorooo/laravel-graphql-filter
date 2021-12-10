<?php

namespace Simexis\GraphQLFilter\Type;

use Illuminate\Support\Str;
use Simexis\GraphQLFilter\GraphQLFilter;
use Simexis\GraphQLFilter\GraphQLFilterException;

use ReflectionClass;
use Rebing\GraphQL\Support\InputType;

class FilterType extends InputType
{
    protected $model;
    protected $fieldFilterable = null;
    protected $fieldOverrides = [];

    public function attributes(): array
    {
        $modelName = $this->getModelName();
        $typeName = $this->getTypeName();
        return [
            'name' => $typeName,
            'description' => 'Filter parameters for listing ' . Str::plural($modelName)
        ];
    }

    public function fields(): array
    {
        return $this->fieldsFromModel();
    }

    public function getModelName()
    {
        return (new ReflectionClass($this->getModel()))->getshortName();
    }

    public function getTypeName()
    {
        return $this->getModelName() . 'Filter';
    }

    public function getModel()
    {
        if (!isset($this->model)) {
            throw new GraphQLFilterException('model property must be set');
        }
        return $this->model;
    }

    protected function getFieldFilterable()
    {
        return $this->fieldFilterable;
    }

    protected function getFieldOverrides(): array
    {
        return $this->fieldOverrides;
    }

    protected function fieldsFromModel()
    {
        $model = $this->getModel();
        $fieldFilterables = $this->getFieldFilterable();
        $fieldOverrides = $this->getFieldOverrides();
        return GraphQLFilter::fieldsFromModel($model, $fieldFilterables, $fieldOverrides);
    }
}
