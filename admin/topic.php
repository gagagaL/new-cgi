<?php
/**
 * お題作成・編集
 */

require_once __DIR__ . '/../includes/template.php';

$config = getConfig();
date_default_timezone_set($config['timezone']);

if (!isAdmin()) {
    setFlash('error', 'ログインしてください。');
    redirect('login.php');
}

$db = getDb();

// 編集モードかどうか
$topicId = (int) ($_GET['id'] ?? 0);
$isEdit = $topicId > 0;
$topic = null;

if ($isEdit) {
    $topic = $db->selectOne("SELECT * FROM topics WHERE id = ?", [$topicId]);
    if (!$topic) {
        setFlash('error', 'お題が見つかりません。');
        redirect('topics.php');
    }
}

// POST処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';

    if (!validateToken($token)) {
        setFlash('error', 'セッションが無効です。');
        redirect($isEdit ? "topic.php?id={$topicId}" : 'topic.php');
    }

    $data = [
        'title' => trim($_POST['title'] ?? ''),
        'content' => trim($_POST['content'] ?? ''),
        'topic_type' => $_POST['topic_type'] ?? 'normal',
        'line_before' => trim($_POST['line_before'] ?? ''),
        'line_after' => trim($_POST['line_after'] ?? ''),
        'post_start' => $_POST['post_start'] ?: null,
        'post_end' => $_POST['post_end'] ?: null,
        'vote_start' => $_POST['vote_start'] ?: null,
        'vote_end' => $_POST['vote_end'] ?: null,
        'result_date' => $_POST['result_date'] ?: null,
        'status' => (int) ($_POST['status'] ?? 0),
        'point_a' => (int) ($_POST['point_a'] ?? 3),
        'point_b' => (int) ($_POST['point_b'] ?? 2),
        'point_c' => (int) ($_POST['point_c'] ?? 1),
        'point_a_limit' => (int) ($_POST['point_a_limit'] ?? 1),
        'point_b_limit' => (int) ($_POST['point_b_limit'] ?? 3),
        'point_c_limit' => (int) ($_POST['point_c_limit'] ?? 5),
        'comment_accept' => isset($_POST['comment_accept']) ? 1 : 0,
        'self_vote' => isset($_POST['self_vote']) ? 1 : 0,
        'updated_at' => date('Y-m-d H:i:s'),
    ];

    // 画像処理
    if (!empty($_FILES['image']['tmp_name'])) {
        $imageInfo = getimagesize($_FILES['image']['tmp_name']);
        if ($imageInfo) {
            $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif'];
            if (isset($allowedTypes[$imageInfo['mime']])) {
                $data['image'] = base64_encode(file_get_contents($_FILES['image']['tmp_name']));
                $data['image_ext'] = $allowedTypes[$imageInfo['mime']];
            }
        }
    } elseif (isset($_POST['delete_image']) && $isEdit) {
        $data['image'] = null;
        $data['image_ext'] = null;
    }

    // バリデーション
    $errors = [];
    if (empty($data['title'])) {
        $errors[] = 'タイトルは必須です。';
    }

    if ($errors) {
        setFlash('error', implode('<br>', $errors));
    } else {
        if ($isEdit) {
            $db->update('topics', $data, 'id = ?', [$topicId]);
            setFlash('success', 'お題を更新しました。');
        } else {
            $db->insert('topics', $data);
            setFlash('success', 'お題を作成しました。');
        }
        redirect('topics.php');
    }
}

$pageTitle = $isEdit ? 'お題編集' : 'お題作成';
renderHeader($pageTitle, ['admin' => true]);
?>

<h1><?= h($pageTitle) ?></h1>

<form method="post" enctype="multipart/form-data" class="topic-form">
    <input type="hidden" name="token" value="<?= generateToken() ?>">

    <fieldset>
        <legend>基本情報</legend>

        <label>
            タイトル <small>(必須)</small>
            <input type="text" name="title" required value="<?= h($topic['title'] ?? '') ?>">
        </label>

        <label>
            説明
            <textarea name="content" rows="4"><?= h($topic['content'] ?? '') ?></textarea>
        </label>

        <label>
            画像
            <input type="file" name="image" accept="image/jpeg,image/png,image/gif">
        </label>

        <?php if ($isEdit && $topic['image']): ?>
            <div class="current-image">
                <img src="data:image/<?= h($topic['image_ext']) ?>;base64,<?= $topic['image'] ?>" alt="" style="max-width: 200px;">
                <label>
                    <input type="checkbox" name="delete_image" value="1"> 画像を削除
                </label>
            </div>
        <?php endif; ?>
    </fieldset>

    <fieldset>
        <legend>お題形式</legend>

        <label>
            <input type="radio" name="topic_type" value="normal" <?= ($topic['topic_type'] ?? 'normal') === 'normal' ? 'checked' : '' ?>>
            通常形式
        </label>
        <label>
            <input type="radio" name="topic_type" value="line" <?= ($topic['topic_type'] ?? '') === 'line' ? 'checked' : '' ?>>
            一行形式
        </label>

        <div class="line-options" style="<?= ($topic['topic_type'] ?? 'normal') !== 'line' ? 'display:none' : '' ?>">
            <label>
                前置きテキスト
                <input type="text" name="line_before" value="<?= h($topic['line_before'] ?? '') ?>">
            </label>
            <label>
                後置きテキスト
                <input type="text" name="line_after" value="<?= h($topic['line_after'] ?? '') ?>">
            </label>
        </div>
    </fieldset>

    <fieldset>
        <legend>スケジュール</legend>

        <label>
            ステータス
            <select name="status">
                <option value="0" <?= ($topic['status'] ?? 0) == 0 ? 'selected' : '' ?>>自動判定</option>
                <option value="1" <?= ($topic['status'] ?? 0) == 1 ? 'selected' : '' ?>>投稿受付中</option>
                <option value="2" <?= ($topic['status'] ?? 0) == 2 ? 'selected' : '' ?>>採点中</option>
                <option value="3" <?= ($topic['status'] ?? 0) == 3 ? 'selected' : '' ?>>結果発表</option>
                <option value="4" <?= ($topic['status'] ?? 0) == 4 ? 'selected' : '' ?>>準備中</option>
                <option value="5" <?= ($topic['status'] ?? 0) == 5 ? 'selected' : '' ?>>お知らせ</option>
                <option value="6" <?= ($topic['status'] ?? 0) == 6 ? 'selected' : '' ?>>凍結</option>
            </select>
        </label>

        <div class="date-fields">
            <label>
                投稿開始
                <input type="datetime-local" name="post_start" value="<?= $topic['post_start'] ? date('Y-m-d\TH:i', strtotime($topic['post_start'])) : '' ?>">
            </label>
            <label>
                投稿終了
                <input type="datetime-local" name="post_end" value="<?= $topic['post_end'] ? date('Y-m-d\TH:i', strtotime($topic['post_end'])) : '' ?>">
            </label>
            <label>
                採点開始
                <input type="datetime-local" name="vote_start" value="<?= $topic['vote_start'] ? date('Y-m-d\TH:i', strtotime($topic['vote_start'])) : '' ?>">
            </label>
            <label>
                採点終了
                <input type="datetime-local" name="vote_end" value="<?= $topic['vote_end'] ? date('Y-m-d\TH:i', strtotime($topic['vote_end'])) : '' ?>">
            </label>
            <label>
                結果発表
                <input type="datetime-local" name="result_date" value="<?= $topic['result_date'] ? date('Y-m-d\TH:i', strtotime($topic['result_date'])) : '' ?>">
            </label>
        </div>
    </fieldset>

    <fieldset>
        <legend>採点設定</legend>

        <div class="point-settings">
            <label>
                高評価 (点)
                <input type="number" name="point_a" value="<?= $topic['point_a'] ?? 3 ?>" min="1">
            </label>
            <label>
                上限回数
                <input type="number" name="point_a_limit" value="<?= $topic['point_a_limit'] ?? 1 ?>" min="1">
            </label>
        </div>
        <div class="point-settings">
            <label>
                中評価 (点)
                <input type="number" name="point_b" value="<?= $topic['point_b'] ?? 2 ?>" min="1">
            </label>
            <label>
                上限回数
                <input type="number" name="point_b_limit" value="<?= $topic['point_b_limit'] ?? 3 ?>" min="1">
            </label>
        </div>
        <div class="point-settings">
            <label>
                低評価 (点)
                <input type="number" name="point_c" value="<?= $topic['point_c'] ?? 1 ?>" min="1">
            </label>
            <label>
                上限回数
                <input type="number" name="point_c_limit" value="<?= $topic['point_c_limit'] ?? 5 ?>" min="1">
            </label>
        </div>

        <label>
            <input type="checkbox" name="comment_accept" value="1" <?= ($topic['comment_accept'] ?? 1) ? 'checked' : '' ?>>
            コメントを許可
        </label>
        <label>
            <input type="checkbox" name="self_vote" value="1" <?= ($topic['self_vote'] ?? 0) ? 'checked' : '' ?>>
            自分への投票を許可
        </label>
    </fieldset>

    <div class="form-actions">
        <button type="submit"><?= $isEdit ? '更新' : '作成' ?></button>
        <a href="topics.php" role="button" class="secondary">キャンセル</a>
    </div>
</form>

<script>
document.querySelectorAll('input[name="topic_type"]').forEach(radio => {
    radio.addEventListener('change', () => {
        document.querySelector('.line-options').style.display =
            document.querySelector('input[name="topic_type"]:checked').value === 'line' ? '' : 'none';
    });
});
</script>

<?php renderFooter(); ?>
