<?php

namespace Usermp\Dockerize\Services;

class EnvironmentDetector
{
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
        // Detect from composer.json or system
        $composer = json_decode(
            file_get_contents(base_path("composer.json")),
            true,
        );

        if (isset($composer["require"]["php"])) {
            $phpRequirement = $composer["require"]["php"];
            if (preg_match("/\d+\.\d+/", $phpRequirement, $matches)) {
                return $matches[0];
            }
        }

        return "8.2";
    }

    protected function detectDatabase(): string
    {
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
        $composer = json_decode(
            file_get_contents(base_path("composer.json")),
            true,
        );

        // Check for common packages
        $commonPackages = [
            "redis" => "redis",
            "mongodb" => "mongo",
            "elasticsearch" => "elasticsearch",
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
        if (file_exists(base_path("package.json"))) {
            $package = json_decode(
                file_get_contents(base_path("package.json")),
                true,
            );
            if (isset($package["engines"]["node"])) {
                return preg_replace(
                    "/[^\d.]/",
                    "",
                    $package["engines"]["node"],
                );
            }
        }

        return null;
    }

    protected function detectCacheDriver(): string
    {
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
