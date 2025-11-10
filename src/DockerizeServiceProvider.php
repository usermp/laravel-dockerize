<?php

namespace Usermp\Dockerize;

use Illuminate\Support\ServiceProvider;
use Usermp\Dockerize\Commands\DockerizeCommand;
use Usermp\Dockerize\Services\DockerFileGenerator;
use Usermp\Dockerize\Services\DockerComposeGenerator;
use Usermp\Dockerize\Services\EnvironmentDetector;
use Illuminate\Filesystem\Filesystem;

class DockerizeServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind("command.dockerize", function ($app) {
            return new DockerizeCommand(
                $app->make(Filesystem::class),
                $app->make(DockerFileGenerator::class),
                $app->make(DockerComposeGenerator::class),
                $app->make(EnvironmentDetector::class),
            );
        });

        $this->commands(["command.dockerize"]);
    }

    public function boot()
    {
        // publish config if needed
    }
}
