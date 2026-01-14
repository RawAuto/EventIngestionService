<?php

declare(strict_types=1);

namespace EventIngestion;

use PDO;
use PDOException;

/**
 * SQLite database connection singleton.
 * Configures WAL mode for better concurrent access.
 */
final class Database
{
    private static ?PDO $instance = null;
    private static string $dbPath = '/app/data/events.db';

    private function __construct()
    {
    }

    public static function setPath(string $path): void
    {
        self::$dbPath = $path;
        self::$instance = null;
    }

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            self::$instance = self::createConnection();
        }

        return self::$instance;
    }

    private static function createConnection(): PDO
    {
        $dsn = 'sqlite:' . self::$dbPath;

        try {
            $pdo = new PDO($dsn, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            // Enable WAL mode for better concurrent access
            $pdo->exec('PRAGMA journal_mode=WAL');
            
            // Enable foreign keys
            $pdo->exec('PRAGMA foreign_keys=ON');
            
            // Set busy timeout to 5 seconds
            $pdo->exec('PRAGMA busy_timeout=5000');

            return $pdo;
        } catch (PDOException $e) {
            throw new PDOException(
                'Database connection failed: ' . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }
    }

    public static function isConnected(): bool
    {
        try {
            $pdo = self::getConnection();
            $pdo->query('SELECT 1');
            return true;
        } catch (PDOException) {
            return false;
        }
    }

    public static function reset(): void
    {
        self::$instance = null;
    }
}

