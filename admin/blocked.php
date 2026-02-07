<?php
/**
 * IPブロック管理
 */

require_once __DIR__ . '/../includes/template.php';

$config = getConfig();
date_default_timezone_set($config['timezone']);

if (!isAdmin()) {
    setFlash('error', 'ログインしてください。');
    redirect('login.php');
}

$db = getDb();

// POST処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';

    if (!validateToken($token)) {
        setFlash('error', 'セッションが無効です。');
        redirect('blocked.php');
    }

    if (isset($_POST['add_ip'])) {
        $ip = trim($_POST['ip'] ?? '');
        $reason = trim($_POST['reason'] ?? '');

        if ($ip && filter_var($ip, FILTER_VALIDATE_IP)) {
            $exists = $db->count('blocked_ips', 'ip = ?', [$ip]) > 0;
            if ($exists) {
                setFlash('error', 'このIPは既にブロックされています。');
            } else {
                $db->insert('blocked_ips', [
                    'ip' => $ip,
                    'reason' => $reason ?: null,
                ]);
                setFlash('success', 'IPをブロックしました。');
            }
        } else {
            setFlash('error', '有効なIPアドレスを入力してください。');
        }
    } elseif (isset($_POST['delete_ip'])) {
        $db->delete('blocked_ips', 'ip = ?', [$_POST['delete_ip']]);
        setFlash('success', 'IPブロックを解除しました。');
    }

    redirect('blocked.php');
}

// ブロック一覧
$blockedIps = $db->select("SELECT * FROM blocked_ips ORDER BY created_at DESC");

renderHeader('IPブロック管理', ['admin' => true]);
?>

<h1>IPブロック管理</h1>

<form method="post" class="add-ip-form">
    <input type="hidden" name="token" value="<?= generateToken() ?>">

    <fieldset>
        <legend>IPをブロック</legend>

        <div class="form-row">
            <label>
                IPアドレス
                <input type="text" name="ip" placeholder="192.168.1.1" required>
            </label>
            <label>
                理由（任意）
                <input type="text" name="reason" placeholder="スパム投稿">
            </label>
        </div>

        <button type="submit" name="add_ip" value="1">ブロック追加</button>
    </fieldset>
</form>

<h2>ブロック中のIP</h2>

<?php if (empty($blockedIps)): ?>
    <p>ブロックしているIPはありません。</p>
<?php else: ?>
    <table class="admin-table">
        <thead>
            <tr>
                <th>IPアドレス</th>
                <th>理由</th>
                <th>ブロック日時</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($blockedIps as $blocked): ?>
                <tr>
                    <td><?= h($blocked['ip']) ?></td>
                    <td><?= h($blocked['reason'] ?? '-') ?></td>
                    <td><?= formatDate($blocked['created_at']) ?></td>
                    <td>
                        <form method="post" style="display: inline;" onsubmit="return confirm('ブロックを解除しますか？')">
                            <input type="hidden" name="token" value="<?= generateToken() ?>">
                            <button type="submit" name="delete_ip" value="<?= h($blocked['ip']) ?>" class="delete-btn">解除</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<p><a href="index.php">&laquo; 管理画面に戻る</a></p>

<?php renderFooter(); ?>
