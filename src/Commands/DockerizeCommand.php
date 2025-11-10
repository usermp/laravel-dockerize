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

    public function __construct(
        protected Filesystem $files,
        protected DockerFileGenerator $dockerGenerator,
        protected DockerComposeGenerator $composeGenerator,
        protected EnvironmentDetector $detector,
    ) {
        parent::__construct();
    }

    public function handle()
    {
        $this->info("🚀 Starting Laravel Dockerization...");

        // Detect environment
        $environment = $this->detector->detect();

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

    protected function generateNginxConfig()
    {
        $stub = $this->files->get(__DIR__ . "/../Stubs/nginx.conf.stub");
        $this->files->put(base_path("docker/nginx/nginx.conf"), $stub);
    }

    protected function generatePHPConfig()
    {
        $stub = $this->files->get(__DIR__ . "/../Stubs/php.ini.stub");
        $this->files->put(base_path("docker/php/php.ini"), $stub);
    }
}
