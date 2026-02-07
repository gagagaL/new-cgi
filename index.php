<?php
/**
 * トップページ - お題一覧
 */

require_once __DIR__ . '/includes/template.php';

// タイムゾーン設定
$config = getConfig();
date_default_timezone_set($config['timezone']);

// データベース初期化
$db = getDb();
if (!$db->tableExists('topics')) {
    $db->initializeTables();
}

// ページネーション
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = $config['topics_per_page'];

// お題を取得（凍結以外）
$total = $db->count('topics', 'status != 6');
$pagination = getPagination($total, $perPage, $page);

$topics = $db->select("
    SELECT * FROM topics
    WHERE status != 6
    ORDER BY
        CASE
            WHEN status = 0 THEN
                CASE
                    WHEN post_start > datetime('now') THEN 4
                    WHEN post_end IS NULL OR post_end > datetime('now') THEN 1
                    WHEN vote_end IS NULL OR vote_end > datetime('now') THEN 2
                    ELSE 3
                END
            ELSE status
        END ASC,
        created_at DESC
    LIMIT ? OFFSET ?
", [$perPage, $pagination['offset']]);

// 出力
renderHeader();
?>

<h1>お題一覧</h1>

<?php if ($config['site_description']): ?>
    <p class="site-description"><?= h($config['site_description']) ?></p>
<?php endif; ?>

<?php if (empty($topics)): ?>
    <p>お題がまだありません。</p>
<?php else: ?>
    <div class="topic-list">
        <?php foreach ($topics as $topic): ?>
            <?php renderTopicCard($topic); ?>
        <?php endforeach; ?>
    </div>

    <?php renderPagination($pagination); ?>
<?php endif; ?>

<?php renderFooter(); ?>
