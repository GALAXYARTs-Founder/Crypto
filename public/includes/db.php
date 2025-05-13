<?php
/**
 * Класс для работы с базой данных
 * CryptoLogoWall
 */

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $charset;
    private $pdo;
    private $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    
    public function __construct() {
        $this->host = DB_HOST;
        $this->db_name = DB_NAME;
        $this->username = DB_USER;
        $this->password = DB_PASS;
        $this->charset = DB_CHARSET;
        
        $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset={$this->charset}";
        
        try {
            $this->pdo = new PDO($dsn, $this->username, $this->password, $this->options);
        } catch (PDOException $e) {
            // Логирование ошибки без раскрытия деталей в продакшн
            if (DEV_MODE) {
                die("Connection failed: " . $e->getMessage());
            } else {
                error_log("DB Connection Error: " . $e->getMessage());
                die("Database connection error. Please contact administrator.");
            }
        }
    }
    
    // Выполнение запроса с параметрами
    public function query($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            if (DEV_MODE) {
                die("Query failed: " . $e->getMessage() . " in query: " . $sql);
            } else {
                error_log("Query Error: " . $e->getMessage() . " SQL: " . $sql);
                die("Database query error. Please contact administrator.");
            }
        }
    }
    
    // Получение одной записи
    public function fetchOne($sql, $params = []) {
        $result = $this->query($sql, $params);
        return $result->fetch();
    }
    
    // Получение всех записей
    public function fetchAll($sql, $params = []) {
        $result = $this->query($sql, $params);
        return $result->fetchAll();
    }
    
    // Получение первого значения
    public function fetchColumn($sql, $params = []) {
        $result = $this->query($sql, $params);
        return $result->fetchColumn();
    }
    
    // Добавление записи и возврат ID
    public function insert($table, $data) {
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');
        
        $columnsStr = implode(', ', $columns);
        $placeholdersStr = implode(', ', $placeholders);
        
        $sql = "INSERT INTO {$table} ({$columnsStr}) VALUES ({$placeholdersStr})";
        
        $this->query($sql, array_values($data));
        return $this->pdo->lastInsertId();
    }
    
    // Обновление записи
    public function update($table, $data, $where, $whereParams = []) {
        $setClauses = [];
        $params = [];
        
        // Формируем SET часть запроса с позиционными параметрами
        foreach ($data as $column => $value) {
            $setClauses[] = "{$column} = ?";
            $params[] = $value;
        }
        
        $setClauseStr = implode(', ', $setClauses);
        
        $sql = "UPDATE {$table} SET {$setClauseStr} WHERE {$where}";
        
        // Объединяем параметры SET и WHERE
        $params = array_merge($params, $whereParams);
        
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
    
    // Удаление записи
    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
    
    // Проверка существования записи
    public function exists($table, $where, $params = []) {
        $sql = "SELECT 1 FROM {$table} WHERE {$where} LIMIT 1";
        $result = $this->query($sql, $params);
        return $result->rowCount() > 0;
    }
    
    // Безопасное получение count
    public function count($table, $where = '', $params = []) {
        $sql = "SELECT COUNT(*) FROM {$table}";
        if (!empty($where)) {
            $sql .= " WHERE {$where}";
        }
        return (int) $this->fetchColumn($sql, $params);
    }
    
    // Начать транзакцию
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }
    
    // Зафиксировать транзакцию
    public function commit() {
        return $this->pdo->commit();
    }
    
    // Откатить транзакцию
    public function rollBack() {
        return $this->pdo->rollBack();
    }
    
    // Экранирование имен таблиц/столбцов
    public function quote($value) {
        return $this->pdo->quote($value);
    }
    
    // Получение PDO объекта
    public function getPDO() {
        return $this->pdo;
    }
}

// Создаем экземпляр класса для использования
$db = new Database();