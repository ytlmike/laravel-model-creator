<?php

namespace ModelCreator;

use Illuminate\Support\ServiceProvider;
use ModelCreator\Commands\CreateEloquentModel;

class ModelCreatorServiceProvider extends ServiceProvider
{
    /**
     * register the commands
     */
    public function register()
    {
        $this->commands(CreateEloquentModel::class);
    }
}