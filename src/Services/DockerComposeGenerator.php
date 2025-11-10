<?php

namespace Usermp\Dockerize\Services;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class DockerComposeGenerator
{
    public function __construct(protected Filesystem $files) {}

    public function generate(array $environment): void
    {
        $stubPath = __DIR__ . "/../Stubs/docker-compose.stub";

        // اگر فایل stub وجود ندارد، ایجادش کن
        if (!$this->files->exists($stubPath)) {
            $this->createDefaultDockerComposeStub();
        }

        $stub = $this->files->get($stubPath);

        $replacements = [
            '{{ $database }}' => $environment["database"],
            '{{ $phpVersion }}' => $environment["php_version"],
            '{{ $nodeVersion }}' => $environment["node_version"] ?? "18",
            '{{ $cacheDriver }}' => $environment["cache_driver"],
            '{{ $queueDriver }}' => $environment["queue_driver"],
            '{{ $dependencies }}' => $this->formatDependencies(
                $environment["dependencies"],
            ),
            '{{ $databaseImage }}' => $this->getDatabaseImage(
                $environment["database"],
            ),
            '{{ $databaseService }}' => $this->getDatabaseService(
                $environment["database"],
            ),
            '{{ $databasePort }}' => $this->getDatabasePort(
                $environment["database"],
            ),
            '{{ $additionalServices }}' => $this->generateAdditionalServices(
                $environment,
            ),
            '{{ $volumes }}' => $this->generateVolumes($environment),
        ];

        $content = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $stub,
        );

        $this->files->put(base_path("docker-compose.yml"), $content);
    }

    protected function formatDependencies(array $dependencies): string
    {
        return var_export($dependencies, true);
    }

    protected function getDatabaseImage(string $database): string
    {
        return match ($database) {
            "pgsql" => "postgres:15",
            "sqlite" => "alpine:latest",
            default => "mysql:8.0",
        };
    }

    protected function getDatabaseService(string $database): string
    {
        return match ($database) {
            "pgsql" => "postgres",
            "sqlite" => "sqlite",
            default => "mysql",
        };
    }

    protected function getDatabasePort(string $database): string
    {
        return match ($database) {
            "pgsql" => "5432",
            default => "3306",
        };
    }

    protected function generateAdditionalServices(array $environment): string
    {
        $services = [];

        // Redis service
        if (
            in_array("redis", $environment["dependencies"]) ||
            $environment["cache_driver"] === "redis" ||
            $environment["queue_driver"] === "redis"
        ) {
            $services[] = $this->generateRedisService();
        }

        // Mailhog for email testing
        if ($this->hasMailConfiguration()) {
            $services[] = $this->generateMailhogService();
        }

        // Meilisearch if needed
        if (in_array("meilisearch", $environment["dependencies"])) {
            $services[] = $this->generateMeilisearchService();
        }

        // Elasticsearch if needed
        if (in_array("elasticsearch", $environment["dependencies"])) {
            $services[] = $this->generateElasticsearchService();
        }

        return implode("\n\n  ", $services);
    }

    protected function generateRedisService(): string
    {
        return <<<'YAML'
          redis:
            image: redis:7-alpine
            container_name: laravel-redis
            restart: unless-stopped
            command: redis-server --appendonly yes
            volumes:
              - redis_data:/data
            networks:
              - laravel-network
            ports:
              - "6379:6379"
        YAML;
    }

    protected function generateMailhogService(): string
    {
        return <<<'YAML'
          mailhog:
            image: mailhog/mailhog:latest
            container_name: laravel-mailhog
            restart: unless-stopped
            networks:
              - laravel-network
            ports:
              - "1025:1025"
              - "8025:8025"
        YAML;
    }

    protected function generateMeilisearchService(): string
    {
        return <<<'YAML'
          meilisearch:
            image: getmeili/meilisearch:latest
            container_name: laravel-meilisearch
            restart: unless-stopped
            networks:
              - laravel-network
            ports:
              - "7700:7700"
            environment:
              - MEILI_MASTER_KEY=masterKey
              - MEILI_NO_ANALYTICS=true
            volumes:
              - meilisearch_data:/meili_data
        YAML;
    }

    protected function generateElasticsearchService(): string
    {
        return <<<'YAML'
          elasticsearch:
            image: elasticsearch:8.9.0
            container_name: laravel-elasticsearch
            restart: unless-stopped
            environment:
              - discovery.type=single-node
              - xpack.security.enabled=false
              - "ES_JAVA_OPTS=-Xms512m -Xmx512m"
            networks:
              - laravel-network
            ports:
              - "9200:9200"
            volumes:
              - elasticsearch_data:/usr/share/elasticsearch/data
        YAML;
    }

    protected function generateVolumes(array $environment): string
    {
        $volumes = ["dbdata:"];

        if (
            in_array("redis", $environment["dependencies"]) ||
            $environment["cache_driver"] === "redis" ||
            $environment["queue_driver"] === "redis"
        ) {
            $volumes[] = "redis_data:";
        }

        if (in_array("meilisearch", $environment["dependencies"])) {
            $volumes[] = "meilisearch_data:";
        }

        if (in_array("elasticsearch", $environment["dependencies"])) {
            $volumes[] = "elasticsearch_data:";
        }

        return implode("\n      - ", $volumes);
    }

    protected function hasMailConfiguration(): bool
    {
        if (!$this->files->exists(base_path(".env"))) {
            return false;
        }

        $env = file_get_contents(base_path(".env"));
        return Str::contains($env, ["MAIL_MAILER=", "MAIL_HOST="]);
    }

    protected function createDefaultDockerComposeStub()
    {
        $stubPath = __DIR__ . "/../Stubs/docker-compose.stub";
        $this->ensureStubDirectoryExists();

        $defaultStub = <<<'YAML'
        version: '3.8'

        services:
          app:
            build:
              context: .
              dockerfile: Dockerfile
            image: laravel-app
            container_name: laravel-app
            restart: unless-stopped
            working_dir: /var/www
            volumes:
              - ./:/var/www
            networks:
              - laravel-network
            extra_hosts:
              - "host.docker.internal:host-gateway"

          webserver:
            image: nginx:alpine
            container_name: nginx-server
            restart: unless-stopped
            ports:
              - "8000:80"
            volumes:
              - ./:/var/www
              - ./docker/nginx/nginx.conf:/etc/nginx/conf.d/default.conf
            networks:
              - laravel-network
            depends_on:
              - app

          database:
            image: {{ $databaseImage }}
            container_name: laravel-db
            restart: unless-stopped
            environment:
        @if($database === 'pgsql')
              POSTGRES_DB: ${DB_DATABASE}
              POSTGRES_USER: ${DB_USERNAME}
              POSTGRES_PASSWORD: ${DB_PASSWORD}
              POSTGRES_ROOT_PASSWORD: ${DB_PASSWORD}
        @else
              MYSQL_DATABASE: ${DB_DATABASE}
              MYSQL_USER: ${DB_USERNAME}
              MYSQL_PASSWORD: ${DB_PASSWORD}
              MYSQL_ROOT_PASSWORD: ${DB_PASSWORD}
        @endif
            volumes:
              - dbdata:/var/lib/{{ $databaseService }}
            networks:
              - laravel-network
            ports:
              - "{{ $databasePort }}:{{ $databasePort }}"

        {{ $additionalServices }}

        volumes:
          {{ $volumes }}

        networks:
          laravel-network:
            driver: bridge
        YAML;

        $this->files->put($stubPath, $defaultStub);
    }

    protected function ensureStubDirectoryExists(): void
    {
        $stubDir = __DIR__ . "/../Stubs";
        if (!$this->files->exists($stubDir)) {
            $this->files->makeDirectory($stubDir, 0755, true);
        }
    }
}
