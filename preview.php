<?php
/**
 * 投稿プレビュー
 */

require_once __DIR__ . '/includes/template.php';

$config = getConfig();
date_default_timezone_set($config['timezone']);
startSession();

// POST以外はリダイレクト
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

$db = getDb();

$action = $_POST['action'] ?? '';
$topicId = (int) ($_POST['topic_id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$url = trim($_POST['url'] ?? '');
$content = trim($_POST['content'] ?? '');

// お題を取得
$topic = $db->selectOne("SELECT * FROM topics WHERE id = ?", [$topicId]);
if (!$topic) {
    setFlash('error', 'お題が見つかりません。');
    redirect('index.php');
}

$status = getTopicStatus($topic);
if ($status !== 1) {
    setFlash('error', '現在投稿を受け付けていません。');
    redirect("page.php?id={$topicId}");
}

// バリデーション
$errors = [];

if (!validateLength($name, $config['name_limit'])) {
    $errors[] = "名前は1〜{$config['name_limit']}文字で入力してください。";
}

if (!validateUrl($url)) {
    $errors[] = 'URLの形式が正しくありません。';
}

if (!validateLength($content, $config['post_body_limit'])) {
    $errors[] = "回答は1〜{$config['post_body_limit']}文字で入力してください。";
}

// IP制限チェック
$clientIp = getClientIp();
if (isIpBlocked($clientIp)) {
    $errors[] = 'あなたのIPアドレスはブロックされています。';
}

$myPostCount = $db->count('posts', 'topic_id = ? AND ip = ?', [$topicId, $clientIp]);
if ($myPostCount >= $config['ip_post_limit']) {
    $errors[] = "このお題への投稿上限（{$config['ip_post_limit']}件）に達しました。";
}

// エラーがあれば戻る
if ($errors) {
    setFlash('error', implode('<br>', $errors));
    redirect("page.php?id={$topicId}");
}

// トークン生成
$token = generateToken();

// セッションに保存
$_SESSION['preview_data'] = [
    'topic_id' => $topicId,
    'name' => $name,
    'url' => $url,
    'content' => $content,
];
$_SESSION['last_name'] = $name;
$_SESSION['last_url'] = $url;

renderHeader('投稿プレビュー');
?>

<h1>投稿プレビュー</h1>

<article class="preview-card">
    <header>
        <strong><?= h($name) ?></strong>
        <?php if ($url): ?>
            <a href="<?= h($url) ?>" target="_blank" rel="noopener"><?= h($url) ?></a>
        <?php endif; ?>
    </header>

    <div class="preview-content">
        <?php if ($topic['topic_type'] === 'line'): ?>
            <span class="line-before"><?= h($topic['line_before']) ?></span>
            <?= formatContent($content) ?>
            <span class="line-after"><?= h($topic['line_after']) ?></span>
        <?php else: ?>
            <?= formatContent($content) ?>
        <?php endif; ?>
    </div>
</article>

<p>この内容で投稿しますか？</p>

<div class="preview-actions">
    <form method="post" action="post.php" style="display: inline;">
        <input type="hidden" name="action" value="post">
        <input type="hidden" name="topic_id" value="<?= $topicId ?>">
        <input type="hidden" name="token" value="<?= h($token) ?>">
        <button type="submit" class="primary">投稿する</button>
    </form>

    <a href="page.php?id=<?= $topicId ?>" role="button" class="secondary">戻って修正</a>
</div>

<?php renderFooter(); ?>
