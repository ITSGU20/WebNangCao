<?php
require_once __DIR__ . '/config.php';

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Không expose lỗi chi tiết ra ngoài
            error_log('DB Connection error: ' . $e->getMessage());
            die('<div style="font-family:sans-serif;padding:2rem;color:#c0392b">
                    ⚠️ Không thể kết nối cơ sở dữ liệu. Vui lòng thử lại sau.
                 </div>');
        }
    }
    return $pdo;
}

// Helper: fetch all rows
function db_query(string $sql, array $params = []): array {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Helper: fetch one row
function db_row(string $sql, array $params = []): ?array {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row ?: null;
}

// Helper: execute INSERT/UPDATE/DELETE, return affected rows
function db_exec(string $sql, array $params = []): int {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

// Helper: execute INSERT, return last insert id
function db_insert(string $sql, array $params = []): int {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return (int) db()->lastInsertId();
}

// Helper: fetch single value
function db_val(string $sql, array $params = []): mixed {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_NUM);
    return $row ? $row[0] : null;
}

// Paginate helper: returns ['items'=>[], 'total'=>n, 'pages'=>n, 'page'=>n]
function db_paginate(string $sql, array $params, int $page, int $perPage = PAGE_SIZE): array {
    // Count total
    $countSql = 'SELECT COUNT(*) FROM (' . $sql . ') AS _cnt';
    $total = (int) db_val($countSql, $params);
    $pages = max(1, (int) ceil($total / $perPage));
    $page  = max(1, min($page, $pages));
    $offset = ($page - 1) * $perPage;

    $items = db_query($sql . " LIMIT $perPage OFFSET $offset", $params);
    return ['items' => $items, 'total' => $total, 'pages' => $pages, 'page' => $page, 'perPage' => $perPage];
}
