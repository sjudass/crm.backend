<?php

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use JetBrains\PhpStorm\Pure;

class ModuleMake extends Command
{
    private $files;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:module {name}
                                                  {--all}
                                                  {--migration}
                                                  {--vue}
                                                  {--view}
                                                  {--controller}
                                                  {--model}
                                                  {--api}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * ModuleMake constructor.
     * @param Filesystem $filesystem
     */
    public function __construct(Filesystem $filesystem)
    {
        parent::__construct();

        $this->files = $filesystem;
    }

    /**
     * Execute the console command.
     *
     */
    public function handle()
    {
        if ($this->option('all')) {
            $this->input->setOption('migration', true);
            $this->input->setOption('vue', true);
            $this->input->setOption('view', true);
            $this->input->setOption('controller', true);
            $this->input->setOption('model', true);
            $this->input->setOption('api', true);
        }

        if ($this->option('model')) {
            $this->createModel();
        }

        if ($this->option('controller')) {
            $this->createController();
        }

        if ($this->option('api')) {
            $this->createApiController();
        }

        if ($this->option('migration')) {
            $this->createMigration();
        }

        if ($this->option('vue')) {
            $this->createVueComponent();
        }

        if ($this->option('view')) {
            $this->createView();
        }
    }

    private function createModel()
    {
        $model = Str::singular(Str::studly(class_basename($this->argument('name'))));

        $this->call('make:model', [
            'name' => "App/Modules/". trim($this->argument('name'))."/Models/" . $model
        ]);
    }

    /**
     * @throws FileNotFoundException
     */
    private function createController()
    {
        $controller = Str::studly(class_basename($this->argument('name')));
        $modelName = Str::singular(Str::studly(class_basename($this->argument('name'))));
        $path = $this->getControllerPath($this->argument('name'));

        if ($this->alreadyExists($path)) {
            $this->error('Controller already exists!');
        } else {
            $this->makeDirectory($path);

            $stub = $this->files->get(base_path('resources/stubs/controller.stub'));

            $stub = Str::replace(
                [
                    'DummyNamespace',
                    'DummyRootNamespace',
                    'DummyClass',
                ],
                [
                    "App\\Modules\\".trim(Str::replace('/', '\\', $this->argument('name')))."\\Controllers",
                    $this->laravel->getNamespace(),
                    $controller."Controller"
                ],
                $stub
            );

            $this->files->put($path, $stub);
            $this->info('Controller created successfully.');
            //$this->updateModularConfig();
        }

        $this->createRoutes($controller, $modelName);
    }

    private function createApiController()
    {
        $controller = Str::studly(class_basename($this->argument('name')));
        $modelName = Str::singular(Str::studly(class_basename($this->argument('name'))));
        $path = $this->getApiControllerPath($this->argument('name'));

        if ($this->alreadyExists($path)) {
            $this->error('Api Controller already exists!');
        } else {
            $this->makeDirectory($path);

            $stub = $this->files->get(base_path('resources/stubs/controller.model.api.stub'));

            $stub = Str::replace(
                [
                    'DummyNamespace',
                    'DummyRootNamespace',
                    'DummyClass',
                    'DummyFullModelClass',
                    'DummyModelClass',
                    'DummyModelVariable'
                ],
                [
                    "App\\Modules\\".trim(Str::replace('/', '\\', $this->argument('name')))."\\Controllers\\Api",
                    $this->laravel->getNamespace(),
                    $controller."Controller",
                    "App\\Modules\\".trim(Str::replace('/', '\\', $this->argument('name')))."\\Models\\{$modelName}",
                    $modelName,
                    lcfirst($modelName)
                ],
                $stub
            );

            $this->files->put($path, $stub);
            $this->info('Api Controller created successfully.');
            //$this->updateModularConfig();
        }

        $this->createApiRoutes($controller, $modelName);
    }

    private function createMigration()
    {
        $table = Str::plural(Str::snake(class_basename($this->argument('name'))));
        $path = "App/Modules/".trim($this->argument('name'))."/Migrations";

        try {
            $this->call('make:migration', [
                'name' => "create_{$table}_table",
                '--create' => $table,
                '--path' => $path
            ]);
        } catch (Exception $ex) {
            $this->error($ex->getMessage());
        }
    }

    private function createVueComponent()
    {
        $path = $this->getVueComponentPath($this->argument('name'));

        $component = Str::studly(class_basename($this->argument('name')));

        if ($this->alreadyExists($path)) {
            $this->error('Vue Component already exists!');
        } else {
            $this->makeDirectory($path);

            $stub = $this->files->get(base_path('resources/stubs/vue.component.stub'));

            $stub = Str::replace(
                [
                    'DummyClass'
                ],
                [
                    $component
                ],
                $stub
            );

            $this->files->put($path, $stub);
            $this->info('Vue Component created successfully.');
        }
    }

    private function createView()
    {
        $paths = $this->getViewPath($this->argument('name'));

        foreach ($paths as $path) {
            //$view = Str::studly(class_basename($this->argument('name')));

            if ($this->alreadyExists($path)) {
                $this->error('View already exists!');
            } else {
                $this->makeDirectory($path);

                $stub = $this->files->get(base_path('resources/stubs/view.stub'));

                $stub = Str::replace([''], [], $stub);

                $this->files->put($path, $stub);
                $this->info('View created successfully.');
            }
        }
    }

    private function getVueComponentPath($argument): string
    {
        return base_path("resources/js/components/{$argument}.vue");
    }

    private function getViewPath($argument)
    {
        $arrFiles = collect([
            'create',
            'edit',
            'index',
            'show'
        ]);

        return $arrFiles->map(function ($item) use ($argument) {
            return base_path("resources/views/{$argument}/{$item}.blade.php");
        });
    }

    private function getControllerPath($argument): string
    {
        $controller = Str::studly(class_basename($argument));
        return "{$this->laravel['path']}/Modules/{$argument}/Controllers/{$controller}Controller.php";
    }

    private function getApiControllerPath($argument): string
    {
        $controller = Str::studly(class_basename($argument));
        return "{$this->laravel['path']}/Modules/{$argument}/Controllers/Api/{$controller}Controller.php";
    }

    private function makeDirectory($path)
    {
        if (!$this->files->isDirectory(dirname($path))) {
            $this->files->makeDirectory(dirname($path), 0755, true, true);
        }
    }

    private function createRoutes($controller, $modelName)
    {
        $routePath = $this->getRoutesPath($this->argument('name'));

        if ($this->alreadyExists($routePath)) {
            $this->error('Web Routes already exists!');
        } else {
            $this->makeDirectory($routePath);

            $stub = $this->files->get(base_path('resources/stubs/routes.web.stub'));

            $stub = str_replace(
                [
                    'DummyClass',
                    'DummyRoutePrefix',
                    'DummyModelVariable'
                ],
                [
                    $controller."Controller",
                    Str::plural(Str::snake(lcfirst($modelName), '-')),
                    lcfirst($modelName)
                ],
                $stub
            );

            $this->files->put($routePath, $stub);
            $this->info('Web Routes created successfully.');
        }
    }

    private function createApiRoutes($controller, $modelName)
    {
        $routePath = $this->getApiRoutesPath($this->argument('name'));

        if ($this->alreadyExists($routePath)) {
            $this->error('Api Routes already exists!');
        } else {
            $this->makeDirectory($routePath);

            $stub = $this->files->get(base_path('resources/stubs/routes.api.stub'));

            $stub = str_replace(
                [
                    'DummyClass',
                    'DummyRoutePrefix',
                    'DummyModelVariable'
                ],
                [
                    "Api\\{$controller}Controller",
                    Str::plural(Str::snake(lcfirst($modelName), '-')),
                    lcfirst($modelName)
                ],
                $stub
            );

            $this->files->put($routePath, $stub);
            $this->info('Api Routes created successfully.');
        }
    }

    private function getApiRoutesPath($argument): string
    {
        return "{$this->laravel['path']}/Modules/{$argument}/Routes/api.php";
    }

    private function getRoutesPath($argument): string
    {
        return "{$this->laravel['path']}/Modules/{$argument}/Routes/web.php";
    }

    #[Pure] private function alreadyExists($path): bool
    {
        return $this->files->exists($path);
    }
}
