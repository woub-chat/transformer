<?php

if (! function_exists('recursive_get')) {

    function recursive_get(mixed $data, string|int $path) {

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
}
