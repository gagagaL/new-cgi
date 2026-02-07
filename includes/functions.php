<?php
/**
 * 共通関数
 */

/**
 * 設定を読み込む
 */
function getConfig(): array
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/config.php';
    }
    return $config;
}

/**
 * データベースインスタンスを取得
 */
function getDb(): Database
{
    require_once __DIR__ . '/Database.php';
    $config = getConfig();
    return Database::getInstance($config['db_path']);
}

/**
 * HTMLエスケープ
 */
function h(string $str): string
{
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/**
 * CSRFトークン生成
 */
function generateToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * CSRFトークン検証
 */
function validateToken(string $token): bool
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * セッション開始
 */
function startSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        $config = getConfig();
        session_name($config['session_name']);
        session_start();
    }
}

/**
 * 管理者ログイン確認
 */
function isAdmin(): bool
{
    startSession();
    return !empty($_SESSION['is_admin']);
}

/**
 * 管理者ログイン
 */
function adminLogin(string $password): bool
{
    $config = getConfig();
    if ($password === $config['password']) {
        startSession();
        $_SESSION['is_admin'] = true;
        return true;
    }
    return false;
}

/**
 * 管理者ログアウト
 */
function adminLogout(): void
{
    startSession();
    unset($_SESSION['is_admin']);
    session_destroy();
}

/**
 * クライアントIPアドレスを取得
 */
function getClientIp(): string
{
    return $_SERVER['HTTP_X_FORWARDED_FOR']
        ?? $_SERVER['HTTP_X_REAL_IP']
        ?? $_SERVER['REMOTE_ADDR']
        ?? '0.0.0.0';
}

/**
 * IPがブロックされているか確認
 */
function isIpBlocked(string $ip): bool
{
    $db = getDb();
    return $db->count('blocked_ips', 'ip = ?', [$ip]) > 0;
}

/**
 * お題のステータスを判定
 * 0: 自動判定, 1: 投稿中, 2: 採点中, 3: 結果表示, 4: 準備中, 5: 掲示, 6: 凍結
 */
function getTopicStatus(array $topic): int
{
    $status = (int) $topic['status'];

    // 固定ステータスの場合はそのまま返す
    if ($status !== 0) {
        return $status;
    }

    // 自動判定
    $now = new DateTime();
    $postStart = $topic['post_start'] ? new DateTime($topic['post_start']) : null;
    $postEnd = $topic['post_end'] ? new DateTime($topic['post_end']) : null;
    $voteStart = $topic['vote_start'] ? new DateTime($topic['vote_start']) : null;
    $voteEnd = $topic['vote_end'] ? new DateTime($topic['vote_end']) : null;
    $resultDate = $topic['result_date'] ? new DateTime($topic['result_date']) : null;

    // 準備中
    if ($postStart && $now < $postStart) {
        return 4;
    }

    // 投稿期間
    if ($postStart && $now >= $postStart && (!$postEnd || $now < $postEnd)) {
        return 1;
    }

    // 採点期間
    if ($voteStart && $now >= $voteStart && (!$voteEnd || $now < $voteEnd)) {
        return 2;
    }

    // 結果表示
    if ($resultDate && $now >= $resultDate) {
        return 3;
    }

    // デフォルトは投稿中
    return 1;
}

/**
 * ステータスラベルを取得
 */
function getStatusLabel(int $status): string
{
    $labels = [
        1 => '投稿受付中',
        2 => '採点中',
        3 => '結果発表',
        4 => '準備中',
        5 => 'お知らせ',
        6 => '凍結',
    ];
    return $labels[$status] ?? '不明';
}

/**
 * ステータスに応じたCSSクラスを取得
 */
function getStatusClass(int $status): string
{
    $classes = [
        1 => 'status-posting',
        2 => 'status-voting',
        3 => 'status-result',
        4 => 'status-waiting',
        5 => 'status-notice',
        6 => 'status-frozen',
    ];
    return $classes[$status] ?? '';
}

/**
 * ページネーション情報を取得
 */
function getPagination(int $total, int $perPage, int $currentPage): array
{
    $totalPages = max(1, (int) ceil($total / $perPage));
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $perPage;

    return [
        'total' => $total,
        'per_page' => $perPage,
        'current_page' => $currentPage,
        'total_pages' => $totalPages,
        'offset' => $offset,
        'has_prev' => $currentPage > 1,
        'has_next' => $currentPage < $totalPages,
    ];
}

/**
 * 日時をフォーマット
 */
function formatDate(?string $datetime, string $format = 'Y/m/d H:i'): string
{
    if (!$datetime) {
        return '';
    }
    return (new DateTime($datetime))->format($format);
}

/**
 * 相対時間を取得
 */
function timeAgo(string $datetime): string
{
    $now = new DateTime();
    $then = new DateTime($datetime);
    $diff = $now->getTimestamp() - $then->getTimestamp();

    if ($diff < 60) {
        return 'たった今';
    } elseif ($diff < 3600) {
        return floor($diff / 60) . '分前';
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . '時間前';
    } elseif ($diff < 604800) {
        return floor($diff / 86400) . '日前';
    } else {
        return formatDate($datetime, 'Y/m/d');
    }
}

/**
 * リダイレクト
 */
function redirect(string $url): never
{
    header("Location: {$url}");
    exit;
}

/**
 * JSONレスポンス
 */
function jsonResponse(array $data, int $statusCode = 200): never
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * フラッシュメッセージを設定
 */
function setFlash(string $type, string $message): void
{
    startSession();
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * フラッシュメッセージを取得（取得後削除）
 */
function getFlash(): ?array
{
    startSession();
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

/**
 * 文字数をチェック
 */
function validateLength(string $str, int $max, int $min = 1): bool
{
    $len = mb_strlen($str);
    return $len >= $min && $len <= $max;
}

/**
 * URLを検証
 */
function validateUrl(string $url): bool
{
    if (empty($url)) {
        return true; // 空は許可
    }
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

/**
 * nl2br + リンク自動変換
 */
function formatContent(string $content): string
{
    $content = h($content);
    $content = nl2br($content);
    // URLをリンクに変換
    $content = preg_replace(
        '/(https?:\/\/[^\s<]+)/i',
        '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>',
        $content
    );
    return $content;
}

/**
 * プレイヤーランキングを取得
 */
function getPlayerRanking(int $limit = 50): array
{
    $db = getDb();
    return $db->select("
        SELECT
            p.name,
            p.url,
            SUM(p.score) as total_score,
            COUNT(DISTINCT p.topic_id) as topic_count,
            COUNT(p.id) as post_count
        FROM posts p
        INNER JOIN topics t ON p.topic_id = t.id
        WHERE (t.status = 3 OR (t.status = 0 AND t.result_date <= datetime('now')))
        GROUP BY p.name
        ORDER BY total_score DESC
        LIMIT ?
    ", [$limit]);
}

/**
 * 作品ランキングを取得
 */
function getPostRanking(int $limit = 20): array
{
    $db = getDb();
    return $db->select("
        SELECT
            p.*,
            t.title as topic_title
        FROM posts p
        INNER JOIN topics t ON p.topic_id = t.id
        WHERE (t.status = 3 OR (t.status = 0 AND t.result_date <= datetime('now')))
        ORDER BY p.score DESC
        LIMIT ?
    ", [$limit]);
}
