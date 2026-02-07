<?php
/**
 * SQLite データベースラッパークラス
 * シンプルなCRUD操作とテーブル自動作成機能
 */

class Database
{
    private PDO $pdo;
    private static ?Database $instance = null;

    private function __construct(string $dbPath)
    {
        $dir = dirname($dbPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $this->pdo = new PDO(
            "sqlite:{$dbPath}",
            null,
            null,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        // WALモードで高速化
        $this->pdo->exec('PRAGMA journal_mode=WAL');
        $this->pdo->exec('PRAGMA foreign_keys=ON');
    }

    public static function getInstance(string $dbPath): Database
    {
        if (self::$instance === null) {
            self::$instance = new self($dbPath);
        }
        return self::$instance;
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * テーブルが存在するか確認
     */
    public function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT name FROM sqlite_master WHERE type='table' AND name=?"
        );
        $stmt->execute([$table]);
        return $stmt->fetch() !== false;
    }

    /**
     * 初期テーブルを作成
     */
    public function initializeTables(): void
    {
        // 設定テーブル
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS settings (
                key TEXT PRIMARY KEY,
                value TEXT
            )
        ");

        // お題テーブル
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS topics (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                content TEXT,
                topic_type TEXT DEFAULT 'normal',
                line_before TEXT,
                line_after TEXT,
                post_start DATETIME,
                post_end DATETIME,
                vote_start DATETIME,
                vote_end DATETIME,
                result_date DATETIME,
                status INTEGER DEFAULT 0,
                point_a INTEGER DEFAULT 3,
                point_b INTEGER DEFAULT 2,
                point_c INTEGER DEFAULT 1,
                point_a_limit INTEGER DEFAULT 1,
                point_b_limit INTEGER DEFAULT 3,
                point_c_limit INTEGER DEFAULT 5,
                comment_accept INTEGER DEFAULT 1,
                self_vote INTEGER DEFAULT 0,
                image BLOB,
                image_ext TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // 投稿テーブル
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS posts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                topic_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                url TEXT,
                content TEXT NOT NULL,
                ip TEXT,
                score INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (topic_id) REFERENCES topics(id) ON DELETE CASCADE
            )
        ");

        // 投票テーブル
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS votes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                topic_id INTEGER NOT NULL,
                post_id INTEGER NOT NULL,
                name TEXT,
                url TEXT,
                point INTEGER NOT NULL,
                ip TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (topic_id) REFERENCES topics(id) ON DELETE CASCADE,
                FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
            )
        ");

        // コメントテーブル
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS comments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                topic_id INTEGER NOT NULL,
                post_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                url TEXT,
                content TEXT NOT NULL,
                ip TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (topic_id) REFERENCES topics(id) ON DELETE CASCADE,
                FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
            )
        ");

        // IPブロックテーブル
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS blocked_ips (
                ip TEXT PRIMARY KEY,
                reason TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // インデックス作成
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_posts_topic ON posts(topic_id)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_votes_topic ON votes(topic_id)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_votes_post ON votes(post_id)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_comments_post ON comments(post_id)");
    }

    /**
     * SELECT クエリ実行
     */
    public function select(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * SELECT 1件取得
     */
    public function selectOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * INSERT 実行
     */
    public function insert(string $table, array $data): int
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($data));

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * UPDATE 実行
     */
    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $set = implode(' = ?, ', array_keys($data)) . ' = ?';
        $sql = "UPDATE {$table} SET {$set} WHERE {$where}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge(array_values($data), $whereParams));

        return $stmt->rowCount();
    }

    /**
     * DELETE 実行
     */
    public function delete(string $table, string $where, array $params = []): int
    {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    /**
     * カウント取得
     */
    public function count(string $table, string $where = '1=1', array $params = []): int
    {
        $sql = "SELECT COUNT(*) as cnt FROM {$table} WHERE {$where}";
        $result = $this->selectOne($sql, $params);
        return (int) ($result['cnt'] ?? 0);
    }
}
