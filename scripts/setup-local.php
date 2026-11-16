<?php

/** Local-only setup: creates isolated application/test databases and ignored environment files. */
require __DIR__.'/../vendor/autoload.php';

if (PHP_SAPI !== 'cli') {
    exit(1);
}
$root = dirname(__DIR__);
$existing = is_file($root.'/.env') ? Dotenv\Dotenv::parse(file_get_contents($root.'/.env')) : [];
if (($existing['APP_ENV'] ?? 'local') !== 'local') {
    exit("This helper is for local development only.\n");
}
$host = getenv('PGHOST') ?: '127.0.0.1';
$port = getenv('PGPORT') ?: '5433';
$admin = getenv('PGUSER') ?: 'postgres';
$password = ($existing['DB_USERNAME'] ?? '') === 'orbit_crm_app' ? $existing['DB_PASSWORD'] : bin2hex(random_bytes(24));
$pdo = new PDO("pgsql:host={$host};port={$port};dbname=postgres", $admin, getenv('PGPASSWORD') ?: null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
if (! $pdo->query("SELECT 1 FROM pg_roles WHERE rolname='orbit_crm_app'")->fetchColumn()) {
    $pdo->exec('CREATE ROLE orbit_crm_app LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE PASSWORD '.$pdo->quote($password));
} elseif (($existing['DB_USERNAME'] ?? '') !== 'orbit_crm_app') {
    exit("Role orbit_crm_app already exists. Configure .env manually; its password was not changed.\n");
}
foreach (['orbit_crm', 'orbit_crm_test'] as $database) {
    if (! $pdo->query("SELECT 1 FROM pg_database WHERE datname=".$pdo->quote($database))->fetchColumn()) {
        $pdo->exec('CREATE DATABASE '.$database.' OWNER orbit_crm_app');
    }
}
$template = file_get_contents($root.'/.env.example');
$key = $existing['APP_KEY'] ?? ('base64:'.base64_encode(random_bytes(32)));
foreach (['.env' => 'orbit_crm', '.env.testing' => 'orbit_crm_test'] as $file => $database) {
    $values = ['APP_KEY' => $key, 'DB_HOST' => $host, 'DB_PORT' => $port, 'DB_DATABASE' => $database, 'DB_USERNAME' => 'orbit_crm_app', 'DB_PASSWORD' => $password];
    if ($file === '.env.testing') {
        $values['APP_ENV'] = 'testing';
    } else {
        $values['OPENAI_API_KEY'] = $existing['OPENAI_API_KEY'] ?? '';
    }
    $content = $template;
    foreach ($values as $name => $value) {
        $content = preg_replace_callback('/^'.preg_quote($name, '/').'=.*$/m', fn () => $name.'='.$value, $content);
    }
    file_put_contents($root.'/'.$file, $content);
}
echo "Local app/test databases and ignored environment files are ready on {$host}:{$port}.\n";
