<?php

namespace Usermp\Dockerize\Services;

use Illuminate\Filesystem\Filesystem;

class DockerComposeGenerator
{
    public function __construct(protected Filesystem $files) {}

    public function generate(array $environment): void
    {
        $stubPath = __DIR__ . "/../Stubs/docker-compose.stub";
        $stub = $this->files->get($stubPath);

        $replacements = [
            '{{ $database }}' => $environment["database"],
            '{{ $phpVersion }}' => $environment["php_version"],
            '{{ $nodeVersion }}' => $environment["node_version"] ?? "",
            '{{ $cacheDriver }}' => $environment["cache_driver"],
            '{{ $queueDriver }}' => $environment["queue_driver"],
            '{{ $dependencies }}' => $environment["dependencies"],
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
        $env = file_get_contents(base_path(".env"));
        return Str::contains($env, ["MAIL_MAILER=", "MAIL_HOST="]);
    }
}
