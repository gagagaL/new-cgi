<?php
/**
 * 投稿管理
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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';

    if (validateToken($token)) {
        if (isset($_POST['delete_post'])) {
            $db->delete('posts', 'id = ?', [(int) $_POST['delete_post']]);
            setFlash('success', '投稿を削除しました。');
        } elseif (isset($_POST['delete_comment'])) {
            $db->delete('comments', 'id = ?', [(int) $_POST['delete_comment']]);
            setFlash('success', 'コメントを削除しました。');
        } elseif (isset($_POST['delete_vote'])) {
            $voteId = (int) $_POST['delete_vote'];
            $vote = $db->selectOne("SELECT * FROM votes WHERE id = ?", [$voteId]);
            if ($vote) {
                // スコアを減算
                $db->update(
                    'posts',
                    ['score' => $db->selectOne("SELECT score FROM posts WHERE id = ?", [$vote['post_id']])['score'] - $vote['point']],
                    'id = ?',
                    [$vote['post_id']]
                );
                $db->delete('votes', 'id = ?', [$voteId]);
            }
            setFlash('success', '投票を削除しました。');
        }
    }
    redirect('posts.php' . (isset($_GET['topic_id']) ? '?topic_id=' . (int) $_GET['topic_id'] : ''));
}

// フィルタ
$topicId = (int) ($_GET['topic_id'] ?? 0);
$view = $_GET['view'] ?? 'posts';

// お題リスト
$topicsList = $db->select("SELECT id, title FROM topics ORDER BY created_at DESC");

// ページネーション
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 30;

$whereClause = $topicId ? 'topic_id = ?' : '1=1';
$whereParams = $topicId ? [$topicId] : [];

if ($view === 'comments') {
    $total = $db->count('comments', $whereClause, $whereParams);
    $pagination = getPagination($total, $perPage, $page);
    $items = $db->select("
        SELECT c.*, p.content as post_content, t.title as topic_title
        FROM comments c
        JOIN posts p ON c.post_id = p.id
        JOIN topics t ON c.topic_id = t.id
        WHERE c.{$whereClause}
        ORDER BY c.created_at DESC
        LIMIT ? OFFSET ?
    ", array_merge($whereParams, [$perPage, $pagination['offset']]));
} elseif ($view === 'votes') {
    $total = $db->count('votes', $whereClause, $whereParams);
    $pagination = getPagination($total, $perPage, $page);
    $items = $db->select("
        SELECT v.*, p.content as post_content, p.name as post_name, t.title as topic_title
        FROM votes v
        JOIN posts p ON v.post_id = p.id
        JOIN topics t ON v.topic_id = t.id
        WHERE v.{$whereClause}
        ORDER BY v.created_at DESC
        LIMIT ? OFFSET ?
    ", array_merge($whereParams, [$perPage, $pagination['offset']]));
} else {
    $total = $db->count('posts', $whereClause, $whereParams);
    $pagination = getPagination($total, $perPage, $page);
    $items = $db->select("
        SELECT p.*, t.title as topic_title
        FROM posts p
        JOIN topics t ON p.topic_id = t.id
        WHERE p.{$whereClause}
        ORDER BY p.created_at DESC
        LIMIT ? OFFSET ?
    ", array_merge($whereParams, [$perPage, $pagination['offset']]));
}

renderHeader('投稿管理', ['admin' => true]);
?>

<h1>投稿管理</h1>

<form method="get" class="filter-form">
    <select name="topic_id" onchange="this.form.submit()">
        <option value="">全てのお題</option>
        <?php foreach ($topicsList as $t): ?>
            <option value="<?= $t['id'] ?>" <?= $topicId === $t['id'] ? 'selected' : '' ?>><?= h($t['title']) ?></option>
        <?php endforeach; ?>
    </select>
    <input type="hidden" name="view" value="<?= h($view) ?>">
</form>

<nav class="view-tabs">
    <a href="?view=posts<?= $topicId ? "&topic_id={$topicId}" : '' ?>" <?= $view === 'posts' ? 'aria-current="page"' : '' ?>>投稿</a>
    <a href="?view=votes<?= $topicId ? "&topic_id={$topicId}" : '' ?>" <?= $view === 'votes' ? 'aria-current="page"' : '' ?>>投票</a>
    <a href="?view=comments<?= $topicId ? "&topic_id={$topicId}" : '' ?>" <?= $view === 'comments' ? 'aria-current="page"' : '' ?>>コメント</a>
</nav>

<?php if (empty($items)): ?>
    <p>データがありません。</p>
<?php elseif ($view === 'comments'): ?>
    <table class="admin-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>お題</th>
                <th>投稿者</th>
                <th>内容</th>
                <th>IP</th>
                <th>日時</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= $item['id'] ?></td>
                    <td><?= h(mb_substr($item['topic_title'], 0, 20)) ?></td>
                    <td><?= h($item['name']) ?></td>
                    <td><?= h(mb_substr($item['content'], 0, 30)) ?></td>
                    <td><small><?= h($item['ip']) ?></small></td>
                    <td><?= formatDate($item['created_at']) ?></td>
                    <td>
                        <form method="post" style="display: inline;" onsubmit="return confirm('削除しますか？')">
                            <input type="hidden" name="token" value="<?= generateToken() ?>">
                            <button type="submit" name="delete_comment" value="<?= $item['id'] ?>" class="delete-btn">削除</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php elseif ($view === 'votes'): ?>
    <table class="admin-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>お題</th>
                <th>投稿</th>
                <th>点数</th>
                <th>IP</th>
                <th>日時</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= $item['id'] ?></td>
                    <td><?= h(mb_substr($item['topic_title'], 0, 20)) ?></td>
                    <td><?= h(mb_substr($item['post_content'], 0, 20)) ?></td>
                    <td><?= $item['point'] ?>点</td>
                    <td><small><?= h($item['ip']) ?></small></td>
                    <td><?= formatDate($item['created_at']) ?></td>
                    <td>
                        <form method="post" style="display: inline;" onsubmit="return confirm('削除しますか？スコアも減算されます。')">
                            <input type="hidden" name="token" value="<?= generateToken() ?>">
                            <button type="submit" name="delete_vote" value="<?= $item['id'] ?>" class="delete-btn">削除</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <table class="admin-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>お題</th>
                <th>投稿者</th>
                <th>内容</th>
                <th>得点</th>
                <th>IP</th>
                <th>日時</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= $item['id'] ?></td>
                    <td><?= h(mb_substr($item['topic_title'], 0, 15)) ?></td>
                    <td><?= h($item['name']) ?></td>
                    <td><?= h(mb_substr($item['content'], 0, 25)) ?></td>
                    <td><?= $item['score'] ?>点</td>
                    <td><small><?= h($item['ip']) ?></small></td>
                    <td><?= formatDate($item['created_at']) ?></td>
                    <td>
                        <form method="post" style="display: inline;" onsubmit="return confirm('削除しますか？関連する投票・コメントも削除されます。')">
                            <input type="hidden" name="token" value="<?= generateToken() ?>">
                            <button type="submit" name="delete_post" value="<?= $item['id'] ?>" class="delete-btn">削除</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php
$paginationBaseUrl = "?view={$view}" . ($topicId ? "&topic_id={$topicId}" : '') . '&';
renderPagination($pagination, $paginationBaseUrl);
?>

<p><a href="index.php">&laquo; 管理画面に戻る</a></p>

<?php renderFooter(); ?>
