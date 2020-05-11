<?php

namespace ModelCreator;

use Illuminate\Support\ServiceProvider;
use ModelCreator\Commands\CreateEloquentModelCommand;

class ModelCreatorServiceProvider extends ServiceProvider
{
    /**
     * register the commands
     */
    public function register()
    {
        $this->commands(CreateEloquentModelCommand::class);
    }
}