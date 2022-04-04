<?php

namespace Bfg\Transformer;

use Bfg\Transformer\Traits\TransformerDataCasting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * @template MODEL_TEMPLATE
 */
abstract class Transformer
{
    use TransformerDataCasting;

    /**
     * @var MODEL_TEMPLATE|Model|object|string
     */
    protected $model;
    protected $toModel = [];
    protected $fromModel = [];
    protected $toModelDefault = [];
    protected $fromModelDefault = [];
    protected $casts = [];
    protected $classCastCache = [];
    protected $dateFormat = 'Y-m-d H:i:s';

    /**
     * @param  object|array  $data
     * @param  MODEL_TEMPLATE|object|string|null  $model
     */
    public function __construct(
        public object|array $data = [],
        object|string|null $model = null,
    ) {
        if ($model) {
            $this->model = $model;
        }
        $this->model = $this->getModel();
        $this->prepareMappings();
    }

    protected function prepareMappings()
    {
        $newToModel = [];
        $newFromModel = [];
        foreach ($this->toModel as $dataKey => $modelKey) {
            $dataKey = is_int($dataKey) ? $modelKey : $dataKey;
            $newToModel[$dataKey] = $modelKey;
        }
        foreach ($this->fromModel as $modelKey => $dataKey) {
            $modelKey = is_int($modelKey) ? $dataKey : $modelKey;
            $newFromModel[$modelKey] = $dataKey;
        }
        $this->toModel = $newToModel;
        $this->fromModel = $newFromModel ?: array_flip($newToModel);
    }

    protected function convertToModel()
    {
        $insert = [];

        foreach ($this->toModel as $dataKey => $modelKey) {
            $dataValue = $this->fieldCasting(
                $dataKey,
                $this->__dotCall($dataKey, $this->data)
            );

            $methodMutator = 'to'.ucfirst(Str::camel($modelKey)).'Attribute';

            $dataValue = $dataValue ?: ($this->toModelDefault[$modelKey] ?? null);

            if (method_exists($this, $methodMutator)) {

                $dataValue = $this->{$methodMutator}($dataValue);
            }

            $insert[$modelKey] = $dataValue;
        }

        $this->fillableModel($insert);

        return $this;
    }

    protected function fillableModel(array $insert)
    {
        $this->model->fill($insert);
    }

    protected function convertFromModel()
    {
        foreach ($this->fromModel as $modelKey => $dataKey) {
            $modelValue = $this->__dotCall($modelKey, $this->model);

            $methodMutator = 'from'.ucfirst(Str::camel($modelKey)).'Attribute';

            if (method_exists($this, $methodMutator)) {
                $modelValue = $this->{$methodMutator}($modelValue);
            }

            $modelValue = $modelValue ?: ($this->fromModelDefault[$dataKey] ?? null);

            if (is_array($this->data)) {
                Arr::set($this->data, $dataKey, $modelValue);
            } else {
                $this->data->{$dataKey} = $modelValue;
            }
        }

        return $this->data;
    }

    protected function getModel() {

        return is_string($this->model) ? app($this->model) : $this->model;
    }

    protected function __dotCall(
        string $path,
        mixed $data
    ) {
        $split = explode('.', $path);

        foreach ($split as $item) {
            try {
                if ($data instanceof \Illuminate\Support\Collection) {
                    $data = $data->get($item);
                } else {
                    if (is_object($data)) {
                        try {
                            $data = $data->{$item};
                        } catch (\Exception $exception) {
                            $data = $data->{$item}();
                        }
                    } else {
                        if (is_array($data)) {
                            $data = $data[$item] ?? null;
                        }
                    }
                }

                if ($data === null) {
                    return null;
                }
            } catch (\Exception $exception) {
                return null;
            }
        }

        return $data;
    }

    public function __call(string $name, array $arguments)
    {
        return call_user_func_array([$this->model, $name], $arguments);
    }

    public function __get(string $name)
    {
        return $this->model->{$name};
    }

    public function model()
    {
        return $this->model;
    }

    /**
     * @param ...$transformers
     * @return TransformerCollection|MODEL_TEMPLATE[]
     */
    public function and(...$transformers)
    {
        $collection = app(TransformerCollection::class);

        $collection->push($this);

        foreach ($transformers as $transformer) {
            if (class_exists($transformer)) {
                $collection->push(
                    $transformer::toModel($this->data, $this->model)
                );
            }
        }

        return $collection;
    }

    /**
     * @param  object|array  $data
     * @param  MODEL_TEMPLATE|object|string|null  $model
     * @return \Illuminate\Contracts\Foundation\Application|Model|mixed|object|string|static|MODEL_TEMPLATE
     */
    public static function toModel(
        object|array $data,
        object|string|null $model = null
    ) {
        return (new static($data, $model))->convertToModel();
    }

    /**
     * @template SET_DATA
     * @param  object|string|null  $model
     * @param  mixed|SET_DATA  $data
     * @return SET_DATA|array|object
     */
    public static function fromModel(
        object|string|null $model,
        mixed $data = []
    ) {
        return (new static($data, $model))->convertFromModel();
    }

    /**
     * @param  object|array  $datas
     * @param  Collection|null  $modelCollection
     * @return TransformerCollection|MODEL_TEMPLATE[]
     */
    public static function toModelCollection(
        object|array $datas,
        Collection $modelCollection = null
    ): TransformerCollection  {
        $collection = app(TransformerCollection::class);

        foreach ($datas as $key => $data) {
            $collection->push(
                static::toModel($data, $modelCollection ? ($modelCollection[$key] ?? null) : null)
            );
        }

        return $collection;
    }

    /**
     * @template SET_DATA
     * @param  Collection  $modelCollection
     * @param  mixed  $datas
     * @return TransformerCollection|SET_DATA[]
     */
    public static function fromModelCollection(
        Collection $modelCollection,
        mixed $datas = []
    ): TransformerCollection {
        $collection = app(TransformerCollection::class);

        foreach ($modelCollection as $key => $model) {
            $collection->push(
                static::fromModel(
                    $model, $datas
                    ? (is_array($datas) ? ($datas[$key] ?? []) : ($datas->{$key} ?? []))
                    : []
                )
            );
        }

        return $collection;
    }
}
