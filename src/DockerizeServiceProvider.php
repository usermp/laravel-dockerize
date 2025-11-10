<?php

namespace Usermp\Dockerize;

use Illuminate\Support\ServiceProvider;
use Usermp\Dockerize\Commands\DockerizeCommand;

class DockerizeServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton("command.dockerize", function ($app) {
            return new DockerizeCommand($app["files"]);
        });

        $this->commands(["command.dockerize"]);
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes(
                [
                    __DIR__ . "/../config/dockerize.php" => config_path(
                        "dockerize.php",
                    ),
                ],
                "dockerize-config",
            );
        }
    }
}
