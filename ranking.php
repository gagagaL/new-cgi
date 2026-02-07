<?php
/**
 * ランキングページ
 */

require_once __DIR__ . '/includes/template.php';

$config = getConfig();
date_default_timezone_set($config['timezone']);

$type = $_GET['type'] ?? 'player';

if ($type === 'post') {
    $title = '作品ランキング';
    $rankings = getPostRanking($config['ranking_post']);
} else {
    $title = '総合得点ランキング';
    $rankings = getPlayerRanking($config['ranking_player']);
}

renderHeader($title);
?>

<h1><?= h($title) ?></h1>

<nav class="ranking-nav">
    <a href="?type=player" <?= $type === 'player' ? 'aria-current="page"' : '' ?>>総合得点</a>
    <a href="?type=post" <?= $type === 'post' ? 'aria-current="page"' : '' ?>>作品</a>
</nav>

<?php if (empty($rankings)): ?>
    <p>まだランキングデータがありません。</p>
<?php elseif ($type === 'player'): ?>
    <table class="ranking-table">
        <thead>
            <tr>
                <th>順位</th>
                <th>名前</th>
                <th>総合得点</th>
                <th>参加お題数</th>
                <th>投稿数</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rankings as $i => $row): ?>
                <tr>
                    <td class="rank"><?= $i + 1 ?></td>
                    <td>
                        <?php if ($row['url']): ?>
                            <a href="<?= h($row['url']) ?>" target="_blank" rel="noopener"><?= h($row['name']) ?></a>
                        <?php else: ?>
                            <?= h($row['name']) ?>
                        <?php endif; ?>
                    </td>
                    <td class="score"><?= number_format($row['total_score']) ?>点</td>
                    <td><?= $row['topic_count'] ?></td>
                    <td><?= $row['post_count'] ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <table class="ranking-table">
        <thead>
            <tr>
                <th>順位</th>
                <th>作品</th>
                <th>投稿者</th>
                <th>お題</th>
                <th>得点</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rankings as $i => $row): ?>
                <tr>
                    <td class="rank"><?= $i + 1 ?></td>
                    <td class="post-content"><?= h(mb_substr($row['content'], 0, 50)) ?><?= mb_strlen($row['content']) > 50 ? '...' : '' ?></td>
                    <td>
                        <?php if ($row['url']): ?>
                            <a href="<?= h($row['url']) ?>" target="_blank" rel="noopener"><?= h($row['name']) ?></a>
                        <?php else: ?>
                            <?= h($row['name']) ?>
                        <?php endif; ?>
                    </td>
                    <td><a href="page.php?id=<?= $row['topic_id'] ?>"><?= h($row['topic_title']) ?></a></td>
                    <td class="score"><?= number_format($row['score']) ?>点</td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<p><a href="index.php">&laquo; トップに戻る</a></p>

<?php renderFooter(); ?>
