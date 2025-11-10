<?php

namespace Usermp\Dockerize\Services;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class DockerFileGenerator
{
    public function __construct(protected Filesystem $files) {}

    public function generate(array $environment): void
    {
        $stubPath = __DIR__ . "/../Stubs/dockerfile.stub";
        $stub = $this->files->get($stubPath);

        $replacements = [
            '{{ $phpVersion }}' => $environment["php_version"],
            '{{ $extensions }}' => $this->generatePHPExtensions($environment),
            '{{ $systemDependencies }}' => $this->generateSystemDependencies(
                $environment,
            ),
            '{{ $additionalCommands }}' => $this->generateAdditionalCommands(
                $environment,
            ),
        ];

        $content = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $stub,
        );

        $this->ensureDirectoryExists(base_path("docker/php"));
        $this->files->put(base_path("Dockerfile"), $content);
    }

    protected function generatePHPExtensions(array $environment): string
    {
        $extensions = [
            "pdo_mysql",
            "mbstring",
            "exif",
            "pcntl",
            "bcmath",
            "gd",
        ];

        // Add database specific extensions
        if ($environment["database"] === "pgsql") {
            $extensions[] = "pdo_pgsql";
        }

        if ($environment["database"] === "sqlite") {
            $extensions[] = "pdo_sqlite";
        }

        // Add cache specific extensions
        if (
            in_array("redis", $environment["dependencies"]) ||
            $environment["cache_driver"] === "redis" ||
            $environment["queue_driver"] === "redis"
        ) {
            $extensions[] = "redis";
        }

        if ($environment["cache_driver"] === "memcached") {
            $extensions[] = "memcached";
        }

        return implode(" ", $extensions);
    }

    protected function generateSystemDependencies(array $environment): string
    {
        $dependencies = [
            "git",
            "curl",
            "libpng-dev",
            "libonig-dev",
            "libxml2-dev",
            "zip",
            "unzip",
        ];

        // Add database client dependencies
        if ($environment["database"] === "pgsql") {
            array_push($dependencies, "libpq-dev");
        }

        if ($environment["database"] === "mysql") {
            array_push($dependencies, "default-mysql-client");
        }

        // Add additional dependencies based on detected services
        if (in_array("mongodb", $environment["dependencies"])) {
            array_push($dependencies, "libssl-dev");
        }

        if (in_array("imagick", $environment["dependencies"])) {
            array_push($dependencies, "libmagickwand-dev");
        }

        return implode(" \\\n    ", $dependencies);
    }

    protected function generateAdditionalCommands(array $environment): string
    {
        $commands = [];

        // Install additional PHP extensions
        if (in_array("mongodb", $environment["dependencies"])) {
            $commands[] =
                "RUN pecl install mongodb && docker-php-ext-enable mongodb";
        }

        if (in_array("imagick", $environment["dependencies"])) {
            $commands[] =
                "RUN pecl install imagick && docker-php-ext-enable imagick";
        }

        // Install Node.js if needed
        if ($environment["node_version"]) {
            $commands[] =
                "RUN curl -fsSL https://deb.nodesource.com/setup_" .
                $environment["node_version"] .
                ".x | bash -";
            $commands[] = "RUN apt-get install -y nodejs";
        }

        // Install supervisor for queue workers
        if ($environment["queue_driver"] !== "sync") {
            $commands[] = "RUN apt-get install -y supervisor";
            $commands[] =
                "COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf";
        }

        return implode("\n", $commands);
    }

    protected function ensureDirectoryExists(string $path): void
    {
        if (!$this->files->exists($path)) {
            $this->files->makeDirectory($path, 0755, true);
        }
    }
}
