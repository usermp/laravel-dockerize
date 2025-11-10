<?php

namespace Usermp\Dockerize\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Usermp\Dockerize\Services\DockerFileGenerator;
use Usermp\Dockerize\Services\DockerComposeGenerator;
use Usermp\Dockerize\Services\EnvironmentDetector;

class DockerizeCommand extends Command
{
    protected $signature = 'dockerize
                          {--php= : PHP version (default: 8.2)}
                          {--node= : Node.js version}
                          {--database= : Database type (mysql, pgsql, sqlite)}
                          {--cache= : Cache driver (redis, memcached)}
                          {--queue= : Queue driver (redis, database)}
                          {--force : Overwrite existing files}';

    protected $description = "Dockerize Laravel application";

    protected Filesystem $files;
    protected DockerFileGenerator $dockerGenerator;
    protected DockerComposeGenerator $composeGenerator;
    protected EnvironmentDetector $detector;

    public function __construct()
    {
        parent::__construct();

        $this->files = new Filesystem();
        $this->detector = new EnvironmentDetector($this->files);
        $this->dockerGenerator = new DockerFileGenerator($this->files);
        $this->composeGenerator = new DockerComposeGenerator($this->files);
    }

    public function handle()
    {
        $this->info("🚀 Starting Laravel Dockerization...");

        // Detect environment
        $environment = $this->detector->detect();

        // Apply command line options
        $environment = $this->applyOptions($environment);

        // Generate Dockerfile
        $this->dockerGenerator->generate($environment);

        // Generate docker-compose.yml
        $this->composeGenerator->generate($environment);

        // Generate additional config files
        $this->generateNginxConfig();
        $this->generatePHPConfig();

        $this->info("✅ Laravel application successfully dockerized!");
        $this->info('📋 Run "docker-compose up -d" to start the application');
    }

    protected function applyOptions(array $environment): array
    {
        if ($this->option("php")) {
            $environment["php_version"] = $this->option("php");
        }

        if ($this->option("node")) {
            $environment["node_version"] = $this->option("node");
        }

        if ($this->option("database")) {
            $environment["database"] = $this->option("database");
        }

        if ($this->option("cache")) {
            $environment["cache_driver"] = $this->option("cache");
        }

        if ($this->option("queue")) {
            $environment["queue_driver"] = $this->option("queue");
        }

        return $environment;
    }

    protected function generateNginxConfig()
    {
        $stubPath = __DIR__ . "/../Stubs/nginx.conf.stub";

        if (!$this->files->exists($stubPath)) {
            $this->createDefaultNginxStub();
            return;
        }

        $stub = $this->files->get($stubPath);
        $this->ensureDirectoryExists(base_path("docker/nginx"));
        $this->files->put(base_path("docker/nginx/nginx.conf"), $stub);
    }

    protected function generatePHPConfig()
    {
        $stubPath = __DIR__ . "/../Stubs/php.ini.stub";

        if (!$this->files->exists($stubPath)) {
            $this->createDefaultPHPStub();
            return;
        }

        $stub = $this->files->get($stubPath);
        $this->ensureDirectoryExists(base_path("docker/php"));
        $this->files->put(base_path("docker/php/php.ini"), $stub);
    }

    protected function createDefaultNginxStub()
    {
        $this->ensureDirectoryExists(base_path("docker/nginx"));

        $nginxConfig = <<<'NGINX'
        server {
            listen 80;
            server_name localhost;
            root /var/www/public;

            add_header X-Frame-Options "SAMEORIGIN";
            add_header X-Content-Type-Options "nosniff";

            index index.php;

            charset utf-8;

            location / {
                try_files $uri $uri/ /index.php?$query_string;
            }

            location = /favicon.ico { access_log off; log_not_found off; }
            location = /robots.txt  { access_log off; log_not_found off; }

            error_page 404 /index.php;

            location ~ \.php$ {
                fastcgi_pass app:9000;
                fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
                include fastcgi_params;
            }

            location ~ /\.(?!well-known).* {
                deny all;
            }
        }
        NGINX;

        $this->files->put(base_path("docker/nginx/nginx.conf"), $nginxConfig);
    }

    protected function createDefaultPHPStub()
    {
        $this->ensureDirectoryExists(base_path("docker/php"));

        $phpConfig = <<<'PHP'
        memory_limit = 256M
        upload_max_filesize = 64M
        post_max_size = 64M
        max_execution_time = 300
        max_input_time = 1000
        max_input_vars = 3000

        display_errors = Off
        log_errors = On
        error_log = /var/log/php/error.log

        opcache.enable = 1
        opcache.memory_consumption = 128
        opcache.max_accelerated_files = 10000
        opcache.revalidate_freq = 2

        PHP;

        $this->files->put(base_path("docker/php/php.ini"), $phpConfig);
    }

    protected function ensureDirectoryExists(string $path): void
    {
        if (!$this->files->exists($path)) {
            $this->files->makeDirectory($path, 0755, true);
        }
    }
}
