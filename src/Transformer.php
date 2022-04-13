<?php

namespace Bfg\Transformer;

use Bfg\Transformer\Traits\TransformerDataCasting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

abstract class Transformer
{
    use TransformerDataCasting;

    protected ?string $modelClass = null;

    protected array $toModel = [
//        'dataField' => 'modelField',
//        YouTransformer::class => 'modelRelation'
    ];
    protected array $fromModel = [];

    protected ?string $modelId = 'remote_id';
    protected ?string $remoteId = null;

    protected $casts = [];
    protected $classCastCache = [];
    protected $dateFormat = 'Y-m-d H:i:s';

    protected array $cache = [];

    /**
     * @var array|Transformer[]
     */
    protected array $toRelatedModel = [];

    public function __construct(
        public Model|string|null $model = null,
        public object|array $data = [],
        public ?Relation $relation = null,
        public ?Transformer $parent = null,
    ) {
        $this->withModel($this->model?:$this->modelClass);
    }

    public function withModel(Model|string $model): static
    {
        $this->model = is_string($model) ? new $model : $model;

        return $this;
    }

    public function withData(object|array $data): static
    {
        $this->data = $data;

        return $this;
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
        if (!$this->model) {
            $this->model = $this->getModel();
        }
        if (!$this->data) {
            $this->data = $this->getData();
        }

        if ($this->data instanceof Collection) {

            $collection = app(TransformerCollection::class);

            foreach ($this->data as $datum) {

                $collection->push(
                    static::make($this->model, $datum)
                        ->with($this->cache)
                        ->toModel()
                );
            }

            return $collection;
        }

        $dataToModel = [];

        if ($this->remoteId) {

            $this->toModel[$this->remoteId] = $this->modelId;
        }

        foreach ($this->toModel as $dataKey => $modelKey) {

            if (!is_numeric($dataKey) && class_exists($dataKey)) {
                $relation = $this->model->{$modelKey}();
                if ($relation instanceof Relation) {
                    /** @var Transformer $dataKey */
                    $this->toRelatedModel[$modelKey] = $dataKey::make(
                        $relation->getRelated(), $this->data, $relation, $this
                    );
                }
            } else {
                $dataToModel[$modelKey] = is_numeric($dataKey) ? null : recursive_get($this->data, $dataKey);
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

        $this->saved();

        return $result;
    }

    protected function saved()
    {

    }

    protected function updateModel(array $input)
    {
        return $this->model->update($input);
    }

    protected function createModel(array $input)
    {
        return $this->model = $this->model->create($input);
    }

    protected function getDataForUpload(): object|array
    {
        return $this->getData();
    }

    public function toData(): static
    {
        if (!$this->data) {
            $this->data = $this->getDataForUpload();
        }

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

        return $this;
    }

    public function upload()
    {

    }

    public static function make(
        Model|string|null $model = null,
        object|array $data = [],
        ?Relation $relation = null,
        ?Transformer $parent = null,
    ): static {
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
