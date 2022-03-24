<?php

namespace Bfg\Transformer;

use Bfg\Transformer\Commands\MakeTransformerCommand;

/**
 * Class ServiceProvider
 * @package Bfg\Transformer
 */
class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    /**
     * Register route settings.
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     * @return void
     */
    public function boot()
    {
        $this->commands([
            MakeTransformerCommand::class
        ]);
    }
}

