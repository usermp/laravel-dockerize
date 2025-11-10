<?php

namespace Usermp\Dockerize\Services;

use Illuminate\Filesystem\Filesystem;

class EnvironmentDetector
{
    public function __construct(protected Filesystem $files) {}

    public function detect(): array
    {
        return [
            "php_version" => $this->detectPHPVersion(),
            "node_version" => $this->detectNodeVersion(),
            "database" => $this->detectDatabase(),
            "cache_driver" => $this->detectCacheDriver(),
            "queue_driver" => $this->detectQueueDriver(),
            "dependencies" => $this->detectDependencies(),
        ];
    }

    protected function detectPHPVersion(): string
    {
        if (!$this->files->exists(base_path("composer.json"))) {
            return "8.2";
        }

        $composer = json_decode(
            file_get_contents(base_path("composer.json")),
            true,
        );

        if (isset($composer["require"]["php"])) {
            $phpRequirement = $composer["require"]["php"];
            if (preg_match("/~?(\d+\.\d+)/", $phpRequirement, $matches)) {
                return $matches[1];
            }
        }

        return "8.2";
    }

    protected function detectDatabase(): string
    {
        if (!$this->files->exists(base_path(".env"))) {
            return "mysql";
        }

        $env = file_get_contents(base_path(".env"));

        if (str_contains($env, "DB_CONNECTION=mysql")) {
            return "mysql";
        }
        if (str_contains($env, "DB_CONNECTION=pgsql")) {
            return "pgsql";
        }
        if (str_contains($env, "DB_CONNECTION=sqlite")) {
            return "sqlite";
        }

        return "mysql";
    }

    protected function detectDependencies(): array
    {
        $dependencies = [];

        if (!$this->files->exists(base_path("composer.json"))) {
            return $dependencies;
        }

        $composer = json_decode(
            file_get_contents(base_path("composer.json")),
            true,
        );

        // Check for common packages
        $commonPackages = [
            "predis/predis" => "redis",
            "mongodb/mongodb" => "mongodb",
            "elasticsearch/elasticsearch" => "elasticsearch",
            "laravel/scout" => "scout",
            "meilisearch/meilisearch-php" => "meilisearch",
        ];

        foreach ($commonPackages as $package => $service) {
            if (isset($composer["require"][$package])) {
                $dependencies[] = $service;
            }
        }

        return $dependencies;
    }

    protected function detectNodeVersion(): ?string
    {
        if (!$this->files->exists(base_path("package.json"))) {
            return null;
        }

        $package = json_decode(
            file_get_contents(base_path("package.json")),
            true,
        );
        if (isset($package["engines"]["node"])) {
            return preg_replace("/[^\d.]/", "", $package["engines"]["node"]);
        }

        return "18";
    }

    protected function detectCacheDriver(): string
    {
        if (!$this->files->exists(base_path(".env"))) {
            return "file";
        }

        $env = file_get_contents(base_path(".env"));
        if (str_contains($env, "CACHE_DRIVER=redis")) {
            return "redis";
        }
        if (str_contains($env, "CACHE_DRIVER=memcached")) {
            return "memcached";
        }
        return "file";
    }

    protected function detectQueueDriver(): string
    {
        if (!$this->files->exists(base_path(".env"))) {
            return "sync";
        }

        $env = file_get_contents(base_path(".env"));
        if (str_contains($env, "QUEUE_CONNECTION=redis")) {
            return "redis";
        }
        if (str_contains($env, "QUEUE_CONNECTION=database")) {
            return "database";
        }
        return "sync";
    }
}
