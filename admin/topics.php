<?php
/**
 * お題一覧・管理
 */

require_once __DIR__ . '/../includes/template.php';

$config = getConfig();
date_default_timezone_set($config['timezone']);

if (!isAdmin()) {
    setFlash('error', 'ログインしてください。');
    redirect('login.php');
}

$db = getDb();

// 削除処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    $deleteId = (int) $_POST['delete'];
    $token = $_POST['token'] ?? '';

    if (validateToken($token)) {
        $db->delete('topics', 'id = ?', [$deleteId]);
        setFlash('success', 'お題を削除しました。');
    }
    redirect('topics.php');
}

// ページネーション
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;
$total = $db->count('topics');
$pagination = getPagination($total, $perPage, $page);

$topics = $db->select("
    SELECT * FROM topics
    ORDER BY created_at DESC
    LIMIT ? OFFSET ?
", [$perPage, $pagination['offset']]);

renderHeader('お題一覧', ['admin' => true]);
?>

<h1>お題一覧</h1>

<p><a href="topic.php" role="button">新規作成</a></p>

<?php if (empty($topics)): ?>
    <p>お題がありません。</p>
<?php else: ?>
    <table class="admin-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>タイトル</th>
                <th>ステータス</th>
                <th>作成日</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($topics as $topic): ?>
                <?php $status = getTopicStatus($topic); ?>
                <tr>
                    <td><?= $topic['id'] ?></td>
                    <td><a href="../page.php?id=<?= $topic['id'] ?>" target="_blank"><?= h($topic['title']) ?></a></td>
                    <td><span class="status-badge <?= getStatusClass($status) ?>"><?= getStatusLabel($status) ?></span></td>
                    <td><?= formatDate($topic['created_at']) ?></td>
                    <td class="actions">
                        <a href="topic.php?id=<?= $topic['id'] ?>">編集</a>
                        <form method="post" style="display: inline;" onsubmit="return confirm('本当に削除しますか？')">
                            <input type="hidden" name="token" value="<?= generateToken() ?>">
                            <button type="submit" name="delete" value="<?= $topic['id'] ?>" class="delete-btn">削除</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php renderPagination($pagination, '?'); ?>
<?php endif; ?>

<p><a href="index.php">&laquo; 管理画面に戻る</a></p>

<?php renderFooter(); ?>
