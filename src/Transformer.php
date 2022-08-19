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

    public ?string $modelClass = null;
    public ?string $modelId = 'remote_id';
    public ?string $remoteId = null;
    public array $child = [];
    public array $lastChild = [];

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
    protected array $toRelatedData = [];

    public function __construct(
        public Model|string|null $model = null,
        public object|array $data = [],
        public ?Relation $relation = null,
        public ?Transformer $parent = null,
    ) {
        if ($setModel = $this->model?:$this->modelClass) {
            $this->withModel($setModel);
        }
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
        if (!$this->data) {
            $this->data = $this->getData();
        }

        if ($this->data instanceof Collection) {

            $collection = app(TransformerCollection::class);

            foreach ($this->data as $datum) {

                $collection->push(
                    static::make($this->model, $datum, $this->relation, $this->parent)
                        ->with($this->cache)
                        ->toModel()
                );
            }

            return $collection;

        } else if ($this->data) {

            $this->model = $this->getModel();

            $dataToModel = [];

            if ($this->remoteId) {

                $this->toModel[$this->remoteId] = $this->modelId;
            }

            foreach ($this->toModel as $dataKey => $modelKey) {

                if (!is_numeric($dataKey) && class_exists($dataKey)) {
                    $relation = $this->model->{$modelKey}();
                    if ($relation instanceof Relation) {
                        $this->toRelatedModel[$dataKey] = $modelKey;
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
        }


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
        if ($this->data) {

            if ($this->model->exists) {
                $result = $this->updateModel($this->toModel);
            } else {
                $result = $this->createModel($this->toModel);
            }

            if ($result) {

                $this->saved();

                /** @var Transformer $transformer */
                foreach ($this->toRelatedModel as $transformer => $relation) {

                    $relation = $this->model->{$relation}();

                    $transformer = $this->child[] = $this->lastChild[$transformer] = $transformer::make(
                        $relation->getRelated(), [], $relation, $this
                    );

                    $transformer->with($this->cache)
                        ->toModel()
                        ->save();
                }
            }

            return $result;
        }

        return false;
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
        return $this->model = ($this->relation ?? $this->model)->create($input);
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

        foreach ($this->fromModel as $modelKey => $dataKeys) {
            foreach ((array) $dataKeys as $dataKey) {

                if (!is_numeric($modelKey) && class_exists($modelKey)) {
                    $relation = $this->model->{$dataKey}();
                    if ($relation instanceof Relation) {
                        $this->toRelatedData[$modelKey] = $dataKey;
                    }
                } else {

                    $modelValue = recursive_get($this->model, $modelKey);

                    $methodMutator = 'from'.ucfirst(Str::camel($modelKey)).'Attribute';

                    if (method_exists($this, $methodMutator)) {
                        $modelValue = $this->{$methodMutator}($modelValue);
                    }

                    $methodMutator = 'for'.ucfirst(Str::camel($dataKey)).'DataAttribute';

                    if (method_exists($this, $methodMutator)) {
                        $modelValue = $this->{$methodMutator}($modelValue);
                    }

                    if (is_array($this->data)) {
                        Arr::set($this->data, $dataKey, $modelValue);
                    } else {
                        $this->data->{$dataKey} = $modelValue;
                    }
                }
            }
        }

        return $this;
    }

    public function upload()
    {
        /** @var Transformer $transformer */
        foreach ($this->toRelatedData as $transformer => $relation) {

            $relation = $this->model->{$relation}();

            $transformer = $transformer::make(
                $relation->getRelated(), [], $relation, $this
            );

            $transformer->with($this->cache)
                ->toData()
                ->upload();
        }
    }

    public function create()
    {
        /** @var Transformer $transformer */
        foreach ($this->toRelatedData as $transformer => $relation) {

            $relation = $this->model->{$relation}();

            foreach ($relation->get() as $item) {

                $transformer = $transformer::make(
                    $item, $item, $relation, $this
                );

                $transformer->with($this->cache)
                    ->toData()
                    ->create();
            }
        }
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
