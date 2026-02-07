<?php
/**
 * 管理者ログイン
 */

require_once __DIR__ . '/../includes/template.php';

$config = getConfig();
date_default_timezone_set($config['timezone']);
startSession();

// 既にログイン済みならリダイレクト
if (isAdmin()) {
    redirect('index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';

    if (adminLogin($password)) {
        setFlash('success', 'ログインしました。');
        redirect('index.php');
    } else {
        $error = 'パスワードが正しくありません。';
    }
}

renderHeader('管理者ログイン', ['admin' => true]);
?>

<h1>管理者ログイン</h1>

<?php if ($error): ?>
    <article class="flash flash-error"><?= h($error) ?></article>
<?php endif; ?>

<form method="post" class="login-form">
    <label>
        パスワード
        <input type="password" name="password" required autofocus>
    </label>
    <button type="submit">ログイン</button>
</form>

<p><a href="../index.php">&laquo; トップに戻る</a></p>

<?php renderFooter(); ?>
