<?php
/**
 * 管理画面トップ
 */

require_once __DIR__ . '/../includes/template.php';

$config = getConfig();
date_default_timezone_set($config['timezone']);

// ログインチェック
if (!isAdmin()) {
    setFlash('error', 'ログインしてください。');
    redirect('login.php');
}

$db = getDb();

// テーブル初期化
if (!$db->tableExists('topics')) {
    $db->initializeTables();
}

// 統計情報
$topicCount = $db->count('topics');
$postCount = $db->count('posts');
$voteCount = $db->count('votes');
$commentCount = $db->count('comments');

renderHeader('管理画面', ['admin' => true]);
?>

<h1>管理画面</h1>

<div class="admin-stats">
    <article>
        <header>お題数</header>
        <p class="stat-number"><?= number_format($topicCount) ?></p>
    </article>
    <article>
        <header>投稿数</header>
        <p class="stat-number"><?= number_format($postCount) ?></p>
    </article>
    <article>
        <header>投票数</header>
        <p class="stat-number"><?= number_format($voteCount) ?></p>
    </article>
    <article>
        <header>コメント数</header>
        <p class="stat-number"><?= number_format($commentCount) ?></p>
    </article>
</div>

<nav class="admin-menu">
    <h2>メニュー</h2>
    <ul>
        <li><a href="topic.php">お題作成</a></li>
        <li><a href="topics.php">お題一覧・編集</a></li>
        <li><a href="posts.php">投稿管理</a></li>
        <li><a href="setting.php">サイト設定</a></li>
        <li><a href="blocked.php">IPブロック管理</a></li>
    </ul>
</nav>

<p><a href="../index.php">&laquo; サイトに戻る</a></p>

<?php renderFooter(); ?>
