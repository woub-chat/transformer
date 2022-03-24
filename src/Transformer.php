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
        $this->model = $model
            ? (is_string($model) ? app($model) : $model)
            : $this->getModel();
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
        $this->fromModel = $newFromModel;
    }

    protected function convertToModel()
    {
        foreach ($this->toModel as $dataKey => $modelKey) {
            $dataValue = $this->fieldCasting(
                $dataKey,
                $this->__dotCall($dataKey, $this->data)
            );

            $methodMutator = 'to'.ucfirst(Str::camel($modelKey)).'Attribute';

            if (method_exists($this, $methodMutator)) {
                $dataValue = $this->{$methodMutator}($dataValue);
            }

            $this->model->{$modelKey} = $dataValue;
        }

        return $this->model;
    }

    protected function convertFromModel()
    {
        $fields = $this->fromModel ?: array_flip($this->toModel);

        foreach ($fields as $modelKey => $dataKey) {
            $modelValue = $this->__dotCall($modelKey, $this->model);

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

    protected function getModel()
    {
        return app($this->model);
    }

    protected function __dotCall(string $path, mixed $data)
    {
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

    public static function toModel(object|array $data, object|string|null $model = null)
    {
        return (new static($data, $model))->convertToModel();
    }

    public static function fromModel(object|string|null $model, mixed $data = [])
    {
        return (new static($data, $model))->convertFromModel();
    }

    public static function toModelCollection(
        object|array $datas,
        Collection $modelCollection = null
    ): TransformerCollection {
        $collection = app(TransformerCollection::class);

        foreach ($datas as $key => $data) {
            $collection->push(
                static::toModel($data, $modelCollection ? ($modelCollection[$key] ?? null) : null)
            );
        }

        return $collection;
    }

    public static function fromModelCollection(Collection $modelCollection, mixed $datas = []): TransformerCollection
    {
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
