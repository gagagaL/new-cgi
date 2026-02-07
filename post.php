<?php
/**
 * 投稿・投票・コメント処理
 */

require_once __DIR__ . '/includes/functions.php';

$config = getConfig();
date_default_timezone_set($config['timezone']);
startSession();

// POST以外はリダイレクト
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

$db = getDb();

$action = $_POST['action'] ?? '';
$token = $_POST['token'] ?? '';
$topicId = (int) ($_POST['topic_id'] ?? 0);

// CSRFトークン検証
if (!validateToken($token)) {
    setFlash('error', 'セッションが無効です。もう一度お試しください。');
    redirect($topicId ? "page.php?id={$topicId}" : 'index.php');
}

// お題を取得
$topic = $db->selectOne("SELECT * FROM topics WHERE id = ?", [$topicId]);
if (!$topic) {
    setFlash('error', 'お題が見つかりません。');
    redirect('index.php');
}

$status = getTopicStatus($topic);
$clientIp = getClientIp();

// IPブロックチェック
if (isIpBlocked($clientIp)) {
    setFlash('error', 'あなたのIPアドレスはブロックされています。');
    redirect("page.php?id={$topicId}");
}

switch ($action) {
    case 'post':
        handlePost($db, $topic, $status, $clientIp, $config);
        break;

    case 'vote':
        handleVote($db, $topic, $status, $clientIp);
        break;

    case 'comment':
        handleComment($db, $topic, $status, $clientIp, $config);
        break;

    default:
        setFlash('error', '不正なアクションです。');
        redirect("page.php?id={$topicId}");
}

/**
 * 投稿処理
 */
function handlePost(Database $db, array $topic, int $status, string $clientIp, array $config): void
{
    $topicId = $topic['id'];

    // ステータス確認
    if ($status !== 1) {
        setFlash('error', '現在投稿を受け付けていません。');
        redirect("page.php?id={$topicId}");
    }

    // プレビューデータ取得
    if (empty($_SESSION['preview_data']) || $_SESSION['preview_data']['topic_id'] !== $topicId) {
        setFlash('error', 'プレビューデータが見つかりません。もう一度投稿してください。');
        redirect("page.php?id={$topicId}");
    }

    $data = $_SESSION['preview_data'];
    unset($_SESSION['preview_data']);

    // 投稿上限チェック
    $myPostCount = $db->count('posts', 'topic_id = ? AND ip = ?', [$topicId, $clientIp]);
    if ($myPostCount >= $config['ip_post_limit']) {
        setFlash('error', "このお題への投稿上限（{$config['ip_post_limit']}件）に達しました。");
        redirect("page.php?id={$topicId}");
    }

    // 投稿保存
    $db->insert('posts', [
        'topic_id' => $topicId,
        'name' => $data['name'],
        'url' => $data['url'] ?: null,
        'content' => $data['content'],
        'ip' => $clientIp,
        'score' => 0,
    ]);

    // トークンリセット
    unset($_SESSION['csrf_token']);

    setFlash('success', '投稿が完了しました。');
    redirect("page.php?id={$topicId}");
}

/**
 * 投票処理
 */
function handleVote(Database $db, array $topic, int $status, string $clientIp): void
{
    $topicId = $topic['id'];
    $postId = (int) ($_POST['post_id'] ?? 0);
    $point = (int) ($_POST['point'] ?? 0);

    // ステータス確認
    if ($status !== 2) {
        setFlash('error', '現在採点を受け付けていません。');
        redirect("page.php?id={$topicId}");
    }

    // 投稿存在確認
    $post = $db->selectOne("SELECT * FROM posts WHERE id = ? AND topic_id = ?", [$postId, $topicId]);
    if (!$post) {
        setFlash('error', '投稿が見つかりません。');
        redirect("page.php?id={$topicId}");
    }

    // 自分への投票チェック
    if (!$topic['self_vote'] && $post['ip'] === $clientIp) {
        setFlash('error', '自分の投稿には投票できません。');
        redirect("page.php?id={$topicId}");
    }

    // 重複投票チェック
    $existingVote = $db->count('votes', 'topic_id = ? AND post_id = ? AND ip = ?', [$topicId, $postId, $clientIp]);
    if ($existingVote > 0) {
        setFlash('error', 'この投稿には既に投票済みです。');
        redirect("page.php?id={$topicId}");
    }

    // ポイント検証
    $validPoints = [$topic['point_a'], $topic['point_b'], $topic['point_c']];
    if (!in_array($point, $validPoints, true)) {
        setFlash('error', '不正なポイントです。');
        redirect("page.php?id={$topicId}");
    }

    // 投票保存
    $db->insert('votes', [
        'topic_id' => $topicId,
        'post_id' => $postId,
        'point' => $point,
        'ip' => $clientIp,
    ]);

    // スコア更新
    $db->update('posts', ['score' => $post['score'] + $point], 'id = ?', [$postId]);

    setFlash('success', '投票が完了しました。');
    redirect("page.php?id={$topicId}");
}

/**
 * コメント処理
 */
function handleComment(Database $db, array $topic, int $status, string $clientIp, array $config): void
{
    $topicId = $topic['id'];
    $postId = (int) ($_POST['post_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $url = trim($_POST['url'] ?? '');
    $content = trim($_POST['content'] ?? '');

    // コメント受付確認
    if (!$topic['comment_accept']) {
        setFlash('error', 'このお題ではコメントを受け付けていません。');
        redirect("page.php?id={$topicId}");
    }

    // ステータス確認（採点中または結果発表中のみ）
    if ($status !== 2 && $status !== 3) {
        setFlash('error', '現在コメントを受け付けていません。');
        redirect("page.php?id={$topicId}");
    }

    // 投稿存在確認
    $post = $db->selectOne("SELECT * FROM posts WHERE id = ? AND topic_id = ?", [$postId, $topicId]);
    if (!$post) {
        setFlash('error', '投稿が見つかりません。');
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

    if (!validateLength($content, $config['comment_body_limit'])) {
        $errors[] = "コメントは1〜{$config['comment_body_limit']}文字で入力してください。";
    }

    if ($errors) {
        setFlash('error', implode('<br>', $errors));
        redirect("page.php?id={$topicId}");
    }

    // コメント保存
    $db->insert('comments', [
        'topic_id' => $topicId,
        'post_id' => $postId,
        'name' => $name,
        'url' => $url ?: null,
        'content' => $content,
        'ip' => $clientIp,
    ]);

    // 名前・URL記憶
    $_SESSION['last_name'] = $name;
    $_SESSION['last_url'] = $url;

    setFlash('success', 'コメントを投稿しました。');
    redirect("page.php?id={$topicId}#post-{$postId}");
}
