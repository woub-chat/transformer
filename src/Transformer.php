<?php

namespace Bfg\Transformer;

use Bfg\Transformer\Traits\TransformerDataCasting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Transformer
{
    use TransformerDataCasting;

    protected array $toModel = [
//        'dataField' => 'modelField',
//        YouTransformer::class => 'modelRelation'
    ];
    protected array $fromModel = [];

    protected $casts = [];
    protected $classCastCache = [];
    protected $dateFormat = 'Y-m-d H:i:s';

    protected array $cache = [];

    /**
     * @var array|Transformer[]
     */
    protected array $toRelatedModel = [];

    public function __construct(
        public Model|string $model,
        public object|array $data = [],
        public ?Relation $relation = null,
        public ?Transformer $parent = null,
    ) {
        $this->model = is_string($this->model) ? new $this->model : $this->model;
    }

    protected function getModel()
    {
        return $this->model;
    }

    protected function getData()
    {
        return $this->data;
    }

    protected function getDataCollection()
    {
        return null;
    }

    public function toModel(): TransformerCollection|static
    {
        $this->model = $this->getModel();
        $this->data = $this->getData();

        if ($this->data instanceof Collection) {

            $collection = app(TransformerCollection::class);

            foreach ($this->data as $datum) {

                $collection->push(
                    static::make($this->model, $datum)->toModel()
                );
            }

            return $collection;
        }

        $dataToModel = [];

        foreach ($this->toModel as $dataKey => $modelKey) {

            if (class_exists($dataKey)) {
                $relation = $this->model->{$modelKey}();
                if ($relation instanceof Relation) {
                    /** @var Transformer $dataKey */
                    $this->toRelatedModel[$modelKey] = $dataKey::make(
                        $relation->getRelated(), $this->data, $relation, $this
                    );
                }
            } else {
                $dataToModel[$modelKey] = recursive_get($this->data, $dataKey);
                $methodMutator = 'to'.ucfirst(Str::camel($modelKey)).'Attribute';
                if (method_exists($this, $methodMutator)) {
                    $dataToModel[$modelKey] = $this->{$methodMutator}($dataToModel[$modelKey]);
                }
            }
        }

        $this->toModel = $dataToModel;

        return $this;
    }

    public function with(string|array $name, mixed $value = null): static
    {
        if (is_array($name)) {

            foreach ($name as $key => $item) {

                $this->with($key, $item);
            }

            return $this;
        }

        $this->cache[$name] = $value;

        return $this;
    }

    public function save()
    {
        if ($this->model->exists) {
            $result = $this->updateModel($this->toModel);
        } else {
            $result = $this->createModel($this->toModel);
        }

        foreach ($this->toRelatedModel as $item) {

            $item->toModel()->save();
        }

        return $result;
    }

    protected function updateModel(array $input)
    {
        return $this->model->update($input);
    }

    protected function createModel(array $input)
    {
        return $this->model = $this->model->create($input);
    }

    public function toData(): array|object
    {
        foreach ($this->fromModel as $modelKey => $dataKey) {
            $modelValue = recursive_get($this->model, $modelKey);

            $methodMutator = 'from'.ucfirst(Str::camel($modelKey)).'Attribute';

            if (method_exists($this, $methodMutator)) {
                $modelValue = $this->{$methodMutator}($modelValue);
            }

            if (is_array($this->data)) {
                Arr::set($this->data, $dataKey, $modelValue);
            } else {
                $this->data->{$dataKey} = $modelValue;
            }
        }


        return $this->data;
    }

    public static function make(
        Model|string $model,
        object|array $data = [],
        ?Relation $relation = null,
        ?Transformer $parent = null,
    ) {
        return app(
            static::class,
            compact('model', 'data', 'relation', 'parent')
        );
    }

    public function __get(string $name)
    {
        return $this->cache[$name] ?? null;
    }

    public function __set(string $name, $value): void
    {
        $this->cache[$name] = $value;
    }
}
