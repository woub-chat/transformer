<?php

namespace Bfg\Transformer\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption;

class MakeTransformerCommand extends GeneratorCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'make:transformer';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new Transformer for Eloquent model class';

    protected $type = "Transformer";

    /**
     * RepositoryMakeCommand constructor.
     *
     * @param  Filesystem  $files
     */
    public function __construct(Filesystem $files)
    {
        parent::__construct($files);
    }

    public function handle()
    {
        if (! is_dir(app_path('Transformers'))) {
            mkdir(app_path('Transformers'), 0777, 1);
        }

        return parent::handle();
    }

    /**
     * Replace the namespace for the given stub.
     *
     * @param  string  $stub
     * @param  string  $name
     * @return $this
     */
    protected function replaceNamespace(&$stub, $name)
    {
        $searches = [
            ['DummyNamespace', 'DummyRootNamespace', 'NamespacedDummyUserModel', 'transformerModel'],
            ['{{ namespace }}', '{{ rootNamespace }}', '{{ namespacedUserModel }}', '{{ t_model }}'],
            ['{{namespace}}', '{{rootNamespace}}', '{{namespacedUserModel}}', '{{t_model}}'],
        ];

        foreach ($searches as $search) {
            $stub = str_replace(
                $search, [
                    $this->getNamespace($name),
                    $this->rootNamespace(),
                    $this->userProviderModel(),
                    $this->transformerModel(),
                ], $stub
            );
        }

        return $this;
    }

    /**
     * Parse the class name and format according to the root namespace.
     *
     * @param  string  $name
     * @return string
     */
    protected function qualifyClass($name)
    {
        $name = ltrim($name, '\\/');

        if (! str_ends_with($name, 'Transformer')) {
            $name .= 'Transformer';
        }

        $name = str_replace('/', '\\', $name);

        $rootNamespace = $this->rootNamespace();

        if (Str::startsWith($name, $rootNamespace)) {
            return $name;
        }

        return $this->qualifyClass(
            $this->getDefaultNamespace(trim($rootNamespace, '\\')).'\\'.$name
        );
    }

    /**
     * @return string
     */
    protected function transformerModel()
    {
        $line = '// TODO: Implement getModelClass() method.';

        $model = $this->option('model');

        if (! $model) {
            $model = $this->argument('name');
        }

        if ($model && ! class_exists($model)) {
            if (class_exists('App\\Models\\'.$model)) {
                $model = 'App\\Models\\'.$model;
            } elseif (class_exists('App\\'.$model)) {
                $model = 'App\\'.$model;
            } else {
                $model = null;
            }
        } else {
            $model = null;
        }

        return $model ? ' = \\'.trim($model).'::class' : '';
    }

    /**
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['model', 'm', InputOption::VALUE_OPTIONAL, 'Model of transformer'],
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if the transformer already exists'],
        ];
    }

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        return $this->resolveStubPath('/stubs/transformer.stub');
    }

    /**
     * Resolve the fully-qualified path to the stub.
     *
     * @param  string  $stub
     * @return string
     */
    protected function resolveStubPath($stub)
    {
        return file_exists($customPath = $this->laravel->basePath(trim($stub, '/')))
            ? $customPath
            : __DIR__.$stub;
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return is_dir(app_path('Transformers')) ? $rootNamespace.'\\Transformers' : $rootNamespace;
    }
}
