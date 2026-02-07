<?php
/**
 * Bokesys 設定ファイル
 * サーバーに設置後、必要に応じて編集してください
 */

return [
    // 管理者パスワード（必ず変更してください）
    'password' => 'admin123',

    // サイト基本設定
    'site_name' => 'Bokesys',
    'site_description' => 'お題に答えて楽しむ投稿サイト',

    // データベースファイルパス（dataディレクトリ内）
    'db_path' => __DIR__ . '/../data/bokesys.sqlite',

    // ページネーション
    'topics_per_page' => 10,
    'posts_per_page' => 20,

    // 文字数制限
    'post_body_limit' => 200,
    'comment_body_limit' => 200,
    'name_limit' => 20,

    // ランキング表示件数
    'ranking_player' => 50,
    'ranking_post' => 20,

    // 採点ポイント設定（デフォルト）
    'default_points' => [
        'a' => 3,  // 最高点
        'b' => 2,  // 中間点
        'c' => 1,  // 最低点
    ],

    // 採点ボーナス
    'vote_bonus' => 1,

    // IP制限（投稿数/お題）
    'ip_post_limit' => 3,

    // セッション設定
    'session_name' => 'BOKESYS_SESSION',

    // タイムゾーン
    'timezone' => 'Asia/Tokyo',

    // デバッグモード（本番環境ではfalseに）
    'debug' => false,
];
