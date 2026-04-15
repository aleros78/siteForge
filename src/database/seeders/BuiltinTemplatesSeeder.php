<?php

namespace Database\Seeders;

use App\Models\Template;
use App\Models\TemplateFile;
use Illuminate\Database\Seeder;

class BuiltinTemplatesSeeder extends Seeder
{
    private string $templatesPath;

    public function __construct()
    {
        $this->templatesPath = env('TEMPLATES_BASE_PATH', '/opt/siteforge/templates');
    }

    public function run(): void
    {
        $this->seedLaravelBasic();
        $this->seedPhpBasic();
        $this->command->info('Built-in templates seeded.');
    }

    private function seedLaravelBasic(): void
    {
        $template = Template::create([
            'name'        => 'Laravel Basic',
            'slug'        => 'laravel-basic',
            'description' => 'Stack Laravel completo con PHP-FPM, Nginx, MariaDB, Redis e Queue Worker',
            'version'     => '1.0.0',
            'type'        => 'laravel',
            'is_active'   => true,
            'is_builtin'  => true,
            'services'    => ['app', 'nginx', 'db', 'redis', 'queue'],
        ]);

        // docker-compose.yml
        TemplateFile::create([
            'template_id' => $template->id,
            'filename'    => 'docker-compose.stub',
            'target_path' => 'docker-compose.yml',
            'type'        => 'docker-compose',
            'is_required' => true,
            'content'     => $this->readStub('laravel-basic/docker-compose.stub'),
        ]);

        // nginx config
        TemplateFile::create([
            'template_id' => $template->id,
            'filename'    => 'nginx.conf.stub',
            'target_path' => 'docker/nginx/default.conf',
            'type'        => 'nginx',
            'is_required' => true,
            'content'     => $this->readStub('laravel-basic/nginx.conf.stub'),
        ]);

        // .env
        TemplateFile::create([
            'template_id' => $template->id,
            'filename'    => '.env.stub',
            'target_path' => '.env',
            'type'        => 'env',
            'is_required' => true,
            'content'     => $this->readStub('laravel-basic/.env.stub'),
        ]);
    }

    private function seedPhpBasic(): void
    {
        $template = Template::create([
            'name'        => 'PHP Basic',
            'slug'        => 'php-basic',
            'description' => 'Stack PHP puro con Nginx e MariaDB (senza framework)',
            'version'     => '1.0.0',
            'type'        => 'php',
            'is_active'   => true,
            'is_builtin'  => true,
            'services'    => ['app', 'nginx', 'db'],
        ]);

        TemplateFile::create([
            'template_id' => $template->id,
            'filename'    => 'docker-compose.stub',
            'target_path' => 'docker-compose.yml',
            'type'        => 'docker-compose',
            'is_required' => true,
            'content'     => $this->phpBasicDockerCompose(),
        ]);

        TemplateFile::create([
            'template_id' => $template->id,
            'filename'    => 'nginx.conf.stub',
            'target_path' => 'docker/nginx/default.conf',
            'type'        => 'nginx',
            'is_required' => true,
            'content'     => $this->phpBasicNginx(),
        ]);
    }

    private function readStub(string $relativePath): string
    {
        // Prova prima dal volume montato
        $fullPath = rtrim($this->templatesPath, '/') . '/' . $relativePath;
        if (file_exists($fullPath)) {
            return file_get_contents($fullPath);
        }

        // Fallback: path relativo all'app (utile in dev)
        $appPath = base_path('../templates/' . $relativePath);
        if (file_exists($appPath)) {
            return file_get_contents($appPath);
        }

        $this->command->warn("Template stub non trovato: {$relativePath}. Uso placeholder generico.");
        return "# Template {$relativePath} non trovato\n";
    }

    private function phpBasicDockerCompose(): string
    {
        return <<<'YAML'
version: '3.8'

networks:
  {{project_slug}}_network:
    driver: bridge
  traefik_public:
    external: true
    name: traefik_public

volumes:
  {{project_slug}}_db_data:

services:
  app:
    image: php:8.3-fpm-alpine
    container_name: {{compose_project_name}}_app
    restart: unless-stopped
    working_dir: /var/www
    volumes:
      - ./app:/var/www
    networks:
      - {{project_slug}}_network

  nginx:
    image: nginx:1.25-alpine
    container_name: {{compose_project_name}}_nginx
    restart: unless-stopped
    volumes:
      - ./app:/var/www:ro
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf:ro
    depends_on:
      - app
    networks:
      - {{project_slug}}_network
      - traefik_public
    labels:
      - "traefik.enable=true"
      - "traefik.docker.network=traefik_public"
      - "traefik.http.routers.{{compose_project_name}}.rule=Host(`{{project_domain}}`)"
      - "traefik.http.routers.{{compose_project_name}}.entrypoints=web"
      - "traefik.http.services.{{compose_project_name}}.loadbalancer.server.port=80"

  db:
    image: mariadb:11.2
    container_name: {{compose_project_name}}_db
    restart: unless-stopped
    environment:
      MYSQL_DATABASE: {{db_database}}
      MYSQL_USER: {{db_username}}
      MYSQL_PASSWORD: {{db_password}}
      MYSQL_ROOT_PASSWORD: {{db_root_password}}
    volumes:
      - {{project_slug}}_db_data:/var/lib/mysql
    networks:
      - {{project_slug}}_network
YAML;
    }

    private function phpBasicNginx(): string
    {
        return <<<'NGINX'
server {
    listen 80;
    server_name {{project_domain}};
    root /var/www/public;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass app:9000;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }
}
NGINX;
    }
}
