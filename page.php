<?php
/**
 * お題詳細ページ
 */

require_once __DIR__ . '/includes/template.php';

$config = getConfig();
date_default_timezone_set($config['timezone']);
startSession();

$db = getDb();

// お題ID取得
$topicId = (int) ($_GET['id'] ?? 0);
if (!$topicId) {
    setFlash('error', 'お題が指定されていません。');
    redirect('index.php');
}

// お題を取得
$topic = $db->selectOne("SELECT * FROM topics WHERE id = ?", [$topicId]);
if (!$topic) {
    setFlash('error', 'お題が見つかりません。');
    redirect('index.php');
}

$status = getTopicStatus($topic);

// 凍結中は管理者以外アクセス不可
if ($status === 6 && !isAdmin()) {
    setFlash('error', 'このお題は現在閲覧できません。');
    redirect('index.php');
}

// 投稿を取得
$posts = $db->select("
    SELECT * FROM posts
    WHERE topic_id = ?
    ORDER BY " . ($status === 3 ? "score DESC" : "created_at ASC"),
    [$topicId]
);

// 投稿数を確認（投票フォーム表示判定用）
$clientIp = getClientIp();
$myPostCount = $db->count('posts', 'topic_id = ? AND ip = ?', [$topicId, $clientIp]);

// 投稿済みのpost_idを取得（自分への投票禁止判定用）
$myPostIds = [];
if (!$topic['self_vote']) {
    $myPosts = $db->select("SELECT id FROM posts WHERE topic_id = ? AND ip = ?", [$topicId, $clientIp]);
    $myPostIds = array_column($myPosts, 'id');
}

// 投票済みのpost_idを取得
$myVotedPostIds = [];
$myVotes = $db->select("SELECT post_id FROM votes WHERE topic_id = ? AND ip = ?", [$topicId, $clientIp]);
$myVotedPostIds = array_column($myVotes, 'post_id');

renderHeader($topic['title']);
?>

<article class="topic-detail">
    <header>
        <span class="status-badge <?= getStatusClass($status) ?>"><?= getStatusLabel($status) ?></span>
        <h1><?= h($topic['title']) ?></h1>
    </header>

    <?php if ($topic['image']): ?>
        <div class="topic-image-large">
            <img src="data:image/<?= h($topic['image_ext'] ?: 'png') ?>;base64,<?= $topic['image'] ?>" alt="">
        </div>
    <?php endif; ?>

    <?php if ($topic['content']): ?>
        <div class="topic-description">
            <?= formatContent($topic['content']) ?>
        </div>
    <?php endif; ?>

    <div class="topic-meta">
        <?php if ($topic['post_start']): ?>
            <span>投稿開始: <?= formatDate($topic['post_start']) ?></span>
        <?php endif; ?>
        <?php if ($topic['post_end']): ?>
            <span>投稿終了: <?= formatDate($topic['post_end']) ?></span>
        <?php endif; ?>
        <?php if ($topic['result_date']): ?>
            <span>結果発表: <?= formatDate($topic['result_date']) ?></span>
        <?php endif; ?>
    </div>
</article>

<?php if ($status === 1): // 投稿受付中 ?>
    <section class="post-section">
        <h2>回答を投稿</h2>

        <?php if (isIpBlocked($clientIp)): ?>
            <p class="error">あなたのIPアドレスはブロックされています。</p>
        <?php elseif ($myPostCount >= $config['ip_post_limit']): ?>
            <p class="notice">このお題への投稿上限（<?= $config['ip_post_limit'] ?>件）に達しました。</p>
        <?php else: ?>
            <?php renderPostForm($topic); ?>
        <?php endif; ?>
    </section>
<?php endif; ?>

<section class="posts-section">
    <h2>
        回答一覧
        <small>(<?= count($posts) ?>件)</small>
    </h2>

    <?php if (empty($posts)): ?>
        <p>まだ回答がありません。</p>
    <?php else: ?>
        <div class="post-list">
            <?php foreach ($posts as $post): ?>
                <?php
                // 投票フォーム表示判定
                $showVoteForm = $status === 2
                    && !in_array($post['id'], $myVotedPostIds, true)
                    && !in_array($post['id'], $myPostIds, true)
                    && !isIpBlocked($clientIp);
                ?>
                <?php renderPostCard($post, $topic, $showVoteForm); ?>

                <?php
                // コメント表示
                if ($topic['comment_accept'] && ($status === 2 || $status === 3)):
                    $comments = $db->select(
                        "SELECT * FROM comments WHERE post_id = ? ORDER BY created_at ASC",
                        [$post['id']]
                    );
                ?>
                    <div class="comments-section">
                        <?php foreach ($comments as $comment): ?>
                            <?php renderComment($comment); ?>
                        <?php endforeach; ?>

                        <?php if (!isIpBlocked($clientIp)): ?>
                            <?php renderCommentForm($topicId, $post['id']); ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<p><a href="index.php">&laquo; お題一覧に戻る</a></p>

<?php renderFooter(); ?>
