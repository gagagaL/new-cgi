<?php
/**
 * Aboutページ
 */

require_once __DIR__ . '/includes/template.php';

renderHeader('About');
?>

<h1>BokeSys について</h1>

<article>
    <h2>このアプリについて</h2>
    <p>
    BokeSys は、お題に対してユーザーが回答を投稿し、
        みんなで採点してランキングを競う参加型ゲームサイトです。
    </p>

    <h3>遊び方</h3>
    <ol>
        <li><strong>投稿期間</strong>: 出題されたお題に対して回答を投稿します</li>
        <li><strong>採点期間</strong>: 他の人の回答を採点します</li>
        <li><strong>結果発表</strong>: 採点結果が発表され、ランキングが更新されます</li>
    </ol>

    <h3>技術情報</h3>
    <ul>
        <li>PHP 7.4+ / SQLite</li>
        <li>Pico CSS</li>
    </ul>

    <h3>ライセンス</h3>
    <p>MIT License</p>
</article>

<p><a href="index.php">&laquo; トップに戻る</a></p>

<?php renderFooter(); ?>
