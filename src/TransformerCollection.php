<?php

namespace Bfg\Transformer;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * @mixin Model
 */
class TransformerCollection extends Collection
{
    protected $transaction = 0;

    public function transaction(int $attempts = 1): static
    {
        $this->transaction = $attempts;

        return $this;
    }

    public function __call($method, $parameters)
    {
        if ($this->transaction) {
            DB::transaction(function () use ($method, $parameters) {
                $this->applyMethod($method, $parameters);
            }, $this->transaction);
        } else {
            $this->applyMethod($method, $parameters);
        }

        return $this;
    }

    public function model()
    {
        foreach ($this->items as $key => $model) {
            $this->items[$key] = $model->model();
        }

        return $this;
    }

    protected function applyMethod($method, $parameters)
    {
        foreach ($this->items as $model) {
            call_user_func_array(
                [$model, $method],
                $parameters
            );
        }
    }
}
