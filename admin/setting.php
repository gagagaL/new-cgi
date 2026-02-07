<?php
/**
 * サイト設定
 */

require_once __DIR__ . '/../includes/template.php';

$config = getConfig();
date_default_timezone_set($config['timezone']);

if (!isAdmin()) {
    setFlash('error', 'ログインしてください。');
    redirect('login.php');
}

$db = getDb();

// 現在の設定を取得
$settings = [];
$rows = $db->select("SELECT * FROM settings");
foreach ($rows as $row) {
    $settings[$row['key']] = $row['value'];
}

// POST処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';

    if (!validateToken($token)) {
        setFlash('error', 'セッションが無効です。');
        redirect('setting.php');
    }

    $newSettings = [
        'site_name' => trim($_POST['site_name'] ?? ''),
        'site_description' => trim($_POST['site_description'] ?? ''),
        'topics_per_page' => (int) ($_POST['topics_per_page'] ?? 10),
        'posts_per_page' => (int) ($_POST['posts_per_page'] ?? 20),
        'post_body_limit' => (int) ($_POST['post_body_limit'] ?? 200),
        'comment_body_limit' => (int) ($_POST['comment_body_limit'] ?? 200),
        'name_limit' => (int) ($_POST['name_limit'] ?? 20),
        'ranking_player' => (int) ($_POST['ranking_player'] ?? 50),
        'ranking_post' => (int) ($_POST['ranking_post'] ?? 20),
        'ip_post_limit' => (int) ($_POST['ip_post_limit'] ?? 3),
    ];

    foreach ($newSettings as $key => $value) {
        $exists = $db->count('settings', 'key = ?', [$key]) > 0;
        if ($exists) {
            $db->update('settings', ['value' => $value], 'key = ?', [$key]);
        } else {
            $db->insert('settings', ['key' => $key, 'value' => $value]);
        }
    }

    setFlash('success', '設定を保存しました。');
    redirect('setting.php');
}

// デフォルト値とマージ
$settings = array_merge([
    'site_name' => $config['site_name'],
    'site_description' => $config['site_description'],
    'topics_per_page' => $config['topics_per_page'],
    'posts_per_page' => $config['posts_per_page'],
    'post_body_limit' => $config['post_body_limit'],
    'comment_body_limit' => $config['comment_body_limit'],
    'name_limit' => $config['name_limit'],
    'ranking_player' => $config['ranking_player'],
    'ranking_post' => $config['ranking_post'],
    'ip_post_limit' => $config['ip_post_limit'],
], $settings);

renderHeader('サイト設定', ['admin' => true]);
?>

<h1>サイト設定</h1>

<form method="post" class="setting-form">
    <input type="hidden" name="token" value="<?= generateToken() ?>">

    <fieldset>
        <legend>基本設定</legend>

        <label>
            サイト名
            <input type="text" name="site_name" value="<?= h($settings['site_name']) ?>">
        </label>

        <label>
            サイト説明
            <textarea name="site_description" rows="3"><?= h($settings['site_description']) ?></textarea>
        </label>
    </fieldset>

    <fieldset>
        <legend>表示設定</legend>

        <label>
            お題一覧の表示件数
            <input type="number" name="topics_per_page" value="<?= $settings['topics_per_page'] ?>" min="1" max="100">
        </label>

        <label>
            投稿一覧の表示件数
            <input type="number" name="posts_per_page" value="<?= $settings['posts_per_page'] ?>" min="1" max="100">
        </label>

        <label>
            プレイヤーランキング表示件数
            <input type="number" name="ranking_player" value="<?= $settings['ranking_player'] ?>" min="1" max="200">
        </label>

        <label>
            作品ランキング表示件数
            <input type="number" name="ranking_post" value="<?= $settings['ranking_post'] ?>" min="1" max="200">
        </label>
    </fieldset>

    <fieldset>
        <legend>制限設定</legend>

        <label>
            名前の文字数上限
            <input type="number" name="name_limit" value="<?= $settings['name_limit'] ?>" min="1" max="100">
        </label>

        <label>
            投稿本文の文字数上限
            <input type="number" name="post_body_limit" value="<?= $settings['post_body_limit'] ?>" min="1" max="10000">
        </label>

        <label>
            コメントの文字数上限
            <input type="number" name="comment_body_limit" value="<?= $settings['comment_body_limit'] ?>" min="1" max="10000">
        </label>

        <label>
            1IPあたりの投稿上限（お題ごと）
            <input type="number" name="ip_post_limit" value="<?= $settings['ip_post_limit'] ?>" min="1" max="100">
        </label>
    </fieldset>

    <div class="form-actions">
        <button type="submit">保存</button>
    </div>
</form>

<p><a href="index.php">&laquo; 管理画面に戻る</a></p>

<?php renderFooter(); ?>
