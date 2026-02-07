<?php
/**
 * テンプレート関数
 */

require_once __DIR__ . '/functions.php';

/**
 * ベースURLを取得
 */
function baseUrl(): string
{
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path = dirname($_SERVER['SCRIPT_NAME']);
    return rtrim("{$protocol}://{$host}{$path}", '/');
}

/**
 * HTMLヘッダーを出力
 */
function renderHeader(string $title = '', array $options = []): void
{
    $config = getConfig();
    $siteName = $config['site_name'];
    $pageTitle = $title ? "{$title} | {$siteName}" : $siteName;
    $isAdmin = $options['admin'] ?? false;
    $flash = getFlash();
    startSession();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css">
    <link rel="stylesheet" href="<?= $isAdmin ? '../' : '' ?>assets/css/style.css">
</head>
<body>
    <header class="container">
        <nav>
            <ul>
                <li><a href="<?= $isAdmin ? '../' : '' ?>index.php" class="site-title"><strong><?= h($siteName) ?></strong></a></li>
            </ul>
            <ul>
                <li><a href="<?= $isAdmin ? '../' : '' ?>ranking.php?type=player">総合ランキング</a></li>
                <li><a href="<?= $isAdmin ? '../' : '' ?>ranking.php?type=post">作品ランキング</a></li>
                <?php if (isAdmin()): ?>
                    <li><a href="<?= $isAdmin ? '' : 'admin/' ?>index.php">管理画面</a></li>
                    <li><a href="<?= $isAdmin ? '' : 'admin/' ?>logout.php">ログアウト</a></li>
                <?php else: ?>
                    <li><a href="<?= $isAdmin ? '' : 'admin/' ?>login.php">管理者</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>

    <main class="container">
        <?php if ($flash): ?>
            <article class="flash flash-<?= h($flash['type']) ?>">
                <?= h($flash['message']) ?>
            </article>
        <?php endif; ?>
<?php
}

/**
 * HTMLフッターを出力
 */
function renderFooter(): void
{
    $config = getConfig();
?>
    </main>

    <footer class="container">
        <small>
            <a href="about.php">Bokegram</a> - <?= h($config['site_description']) ?>
        </small>
    </footer>
</body>
</html>
<?php
}

/**
 * ページネーションを出力
 */
function renderPagination(array $pagination, string $baseUrl = '?'): void
{
    if ($pagination['total_pages'] <= 1) {
        return;
    }

    $current = $pagination['current_page'];
    $total = $pagination['total_pages'];
?>
    <nav class="pagination-nav">
        <ul class="pagination">
            <?php if ($pagination['has_prev']): ?>
                <li><a href="<?= $baseUrl ?>page=<?= $current - 1 ?>">&laquo; 前</a></li>
            <?php endif; ?>

            <?php
            $start = max(1, $current - 2);
            $end = min($total, $current + 2);

            if ($start > 1): ?>
                <li><a href="<?= $baseUrl ?>page=1">1</a></li>
                <?php if ($start > 2): ?><li><span>...</span></li><?php endif; ?>
            <?php endif; ?>

            <?php for ($i = $start; $i <= $end; $i++): ?>
                <li>
                    <?php if ($i === $current): ?>
                        <strong><?= $i ?></strong>
                    <?php else: ?>
                        <a href="<?= $baseUrl ?>page=<?= $i ?>"><?= $i ?></a>
                    <?php endif; ?>
                </li>
            <?php endfor; ?>

            <?php if ($end < $total): ?>
                <?php if ($end < $total - 1): ?><li><span>...</span></li><?php endif; ?>
                <li><a href="<?= $baseUrl ?>page=<?= $total ?>"><?= $total ?></a></li>
            <?php endif; ?>

            <?php if ($pagination['has_next']): ?>
                <li><a href="<?= $baseUrl ?>page=<?= $current + 1 ?>">次 &raquo;</a></li>
            <?php endif; ?>
        </ul>
    </nav>
<?php
}

/**
 * お題カードを出力
 */
function renderTopicCard(array $topic): void
{
    $status = getTopicStatus($topic);
    $statusLabel = getStatusLabel($status);
    $statusClass = getStatusClass($status);
?>
    <article class="topic-card <?= $statusClass ?>">
        <header>
            <span class="status-badge <?= $statusClass ?>"><?= $statusLabel ?></span>
            <h3><a href="page.php?id=<?= $topic['id'] ?>"><?= h($topic['title']) ?></a></h3>
        </header>

        <?php if ($topic['image']): ?>
            <div class="topic-image">
                <img src="data:image/<?= h($topic['image_ext'] ?: 'png') ?>;base64,<?= $topic['image'] ?>" alt="">
            </div>
        <?php endif; ?>

        <?php if ($topic['content']): ?>
            <p class="topic-content"><?= formatContent($topic['content']) ?></p>
        <?php endif; ?>

        <footer>
            <small>
                <?php if ($topic['post_start']): ?>
                    投稿開始: <?= formatDate($topic['post_start']) ?>
                <?php endif; ?>
                <?php if ($topic['result_date']): ?>
                    | 結果発表: <?= formatDate($topic['result_date']) ?>
                <?php endif; ?>
            </small>
        </footer>
    </article>
<?php
}

/**
 * 投稿カードを出力
 */
function renderPostCard(array $post, array $topic, bool $showVoteForm = false): void
{
    $topicStatus = getTopicStatus($topic);
?>
    <article class="post-card" id="post-<?= $post['id'] ?>">
        <header>
            <strong class="post-name">
                <?php if ($post['url']): ?>
                    <a href="<?= h($post['url']) ?>" target="_blank" rel="noopener"><?= h($post['name']) ?></a>
                <?php else: ?>
                    <?= h($post['name']) ?>
                <?php endif; ?>
            </strong>
            <small class="post-time"><?= timeAgo($post['created_at']) ?></small>
        </header>

        <div class="post-content">
            <?php if ($topic['topic_type'] === 'line'): ?>
                <span class="line-before"><?= h($topic['line_before']) ?></span>
                <?= formatContent($post['content']) ?>
                <span class="line-after"><?= h($topic['line_after']) ?></span>
            <?php else: ?>
                <?= formatContent($post['content']) ?>
            <?php endif; ?>
        </div>

        <?php if ($topicStatus === 3): // 結果表示中 ?>
            <footer>
                <span class="score"><?= $post['score'] ?>点</span>
            </footer>
        <?php elseif ($showVoteForm && $topicStatus === 2): // 採点中 ?>
            <footer>
                <form method="post" action="post.php" class="vote-form">
                    <input type="hidden" name="action" value="vote">
                    <input type="hidden" name="topic_id" value="<?= $topic['id'] ?>">
                    <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                    <input type="hidden" name="token" value="<?= generateToken() ?>">
                    <div class="vote-buttons">
                        <button type="submit" name="point" value="<?= $topic['point_a'] ?>" class="vote-btn vote-a">
                            <?= $topic['point_a'] ?>点
                        </button>
                        <button type="submit" name="point" value="<?= $topic['point_b'] ?>" class="vote-btn vote-b">
                            <?= $topic['point_b'] ?>点
                        </button>
                        <button type="submit" name="point" value="<?= $topic['point_c'] ?>" class="vote-btn vote-c">
                            <?= $topic['point_c'] ?>点
                        </button>
                    </div>
                </form>
            </footer>
        <?php endif; ?>
    </article>
<?php
}

/**
 * コメントを出力
 */
function renderComment(array $comment): void
{
?>
    <div class="comment">
        <header>
            <strong>
                <?php if ($comment['url']): ?>
                    <a href="<?= h($comment['url']) ?>" target="_blank" rel="noopener"><?= h($comment['name']) ?></a>
                <?php else: ?>
                    <?= h($comment['name']) ?>
                <?php endif; ?>
            </strong>
            <small><?= timeAgo($comment['created_at']) ?></small>
        </header>
        <p><?= formatContent($comment['content']) ?></p>
    </div>
<?php
}

/**
 * 投稿フォームを出力
 */
function renderPostForm(array $topic): void
{
    $config = getConfig();
?>
    <form method="post" action="preview.php" class="post-form">
        <input type="hidden" name="action" value="post">
        <input type="hidden" name="topic_id" value="<?= $topic['id'] ?>">

        <label>
            名前 <small>(必須・<?= $config['name_limit'] ?>文字以内)</small>
            <input type="text" name="name" required maxlength="<?= $config['name_limit'] ?>"
                   value="<?= h($_SESSION['last_name'] ?? '') ?>">
        </label>

        <label>
            URL <small>(任意)</small>
            <input type="url" name="url" placeholder="https://"
                   value="<?= h($_SESSION['last_url'] ?? '') ?>">
        </label>

        <label>
            回答 <small>(必須・<?= $config['post_body_limit'] ?>文字以内)</small>
            <?php if ($topic['topic_type'] === 'line'): ?>
                <div class="line-input">
                    <span class="line-before"><?= h($topic['line_before']) ?></span>
                    <input type="text" name="content" required maxlength="<?= $config['post_body_limit'] ?>">
                    <span class="line-after"><?= h($topic['line_after']) ?></span>
                </div>
            <?php else: ?>
                <textarea name="content" required maxlength="<?= $config['post_body_limit'] ?>" rows="4"></textarea>
            <?php endif; ?>
        </label>

        <button type="submit">プレビュー</button>
    </form>
<?php
}

/**
 * コメントフォームを出力
 */
function renderCommentForm(int $topicId, int $postId): void
{
    $config = getConfig();
?>
    <form method="post" action="post.php" class="comment-form">
        <input type="hidden" name="action" value="comment">
        <input type="hidden" name="topic_id" value="<?= $topicId ?>">
        <input type="hidden" name="post_id" value="<?= $postId ?>">
        <input type="hidden" name="token" value="<?= generateToken() ?>">

        <div class="form-row">
            <input type="text" name="name" required placeholder="名前" maxlength="<?= $config['name_limit'] ?>"
                   value="<?= h($_SESSION['last_name'] ?? '') ?>">
            <input type="url" name="url" placeholder="URL (任意)"
                   value="<?= h($_SESSION['last_url'] ?? '') ?>">
        </div>

        <textarea name="content" required placeholder="コメント" maxlength="<?= $config['comment_body_limit'] ?>" rows="2"></textarea>

        <button type="submit">コメント投稿</button>
    </form>
<?php
}
