<?php
declare(strict_types=1);

final class Database
{
    private ?PDO $conn = null;

    public function getConnection(): PDO
    {
        if ($this->conn instanceof PDO) {
            return $this->conn;
        }

        $configPath = dirname(__DIR__) . '/config.local.php';
        if (!is_file($configPath)) {
            throw new RuntimeException('Falta config.local.php. Copia config.example.php y completa la conexión local.');
        }

        $config = require $configPath;
        $host = (string) ($config['host'] ?? '127.0.0.1');
        $port = (int) ($config['port'] ?? 3306);
        $database = (string) ($config['database'] ?? 'biblioteca_kodigo');
        $username = (string) ($config['username'] ?? 'root');
        $password = (string) ($config['password'] ?? '');
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $database);

        $this->conn = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return $this->conn;
    }
}
