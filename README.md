# Bokegram

お題投稿・採点・ランキングゲームプラットフォーム

## 特徴

- PHP 7.4+ / SQLite（設定不要のファイルベースDB）
- Pico CSS（軽量モダンCSS）
- サーバーにアップロードするだけで動作
- Docker対応

---

## クイックスタート（Docker）

### 必要なもの
- Docker

### 起動方法

```bash
# new-cgiディレクトリに移動
cd new-cgi

# Dockerイメージをビルド
docker build -t bokegram-php .

# コンテナを起動（8080ポートで公開）
docker run -d --name bokegram -p 8080:80 -v $(pwd):/var/www/html bokegram-php
```

### アクセス

- **サイト**: http://localhost:8080
- **管理画面**: http://localhost:8080/admin/login.php
  - 初期パスワード: `admin123`

### 停止・再起動

```bash
# 停止
docker stop bokegram

# 再起動
docker start bokegram

# 完全削除
docker rm -f bokegram
```

### データの永続化

`data/` フォルダにSQLiteデータベースが保存されます。
コンテナを削除してもデータは残ります。

### データを完全にリセットしたい場合

```bash
rm data/bokegram.sqlite*
docker restart bokegram
```

---

## 本番サーバーへのデプロイ

### 動作要件
- PHP 7.4以上
- PDO SQLite拡張（通常はPHPに含まれている）
- Apache（.htaccess対応）またはNginx

### インストール手順

1. `new-cgi` ディレクトリをサーバーにアップロード

2. `includes/config.php` の `password` を変更
   ```php
   'password' => 'your-secure-password',
   ```

3. `data` ディレクトリに書き込み権限を付与
   ```bash
   chmod 755 data
   # または
   chmod 777 data
   ```

4. ブラウザでアクセス → 自動でDBが作成される

### Nginx設定例

```nginx
server {
    listen 80;
    server_name example.com;
    root /var/www/html/new-cgi;
    index index.php;

    # dataディレクトリへのアクセスを禁止
    location /data {
        deny all;
        return 403;
    }

    location / {
        try_files $uri $uri/ =404;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php7.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

---

## 使い方

### 管理者として

1. `/admin/login.php` にアクセス
2. パスワードでログイン
3. 「お題作成」から新しいお題を作成
4. スケジュール設定（投稿期間・採点期間・結果発表日）

### ユーザーとして

1. トップページでお題を選択
2. **投稿期間中**: 回答を投稿
3. **採点期間中**: 他の人の回答に投票（3段階評価）
4. **結果発表後**: ランキングを確認

---

## ディレクトリ構成

```
new-cgi/
├── index.php           # トップページ
├── page.php            # お題詳細
├── post.php            # 投稿処理
├── preview.php         # プレビュー
├── ranking.php         # ランキング
├── about.php           # About
├── admin/              # 管理画面
│   ├── index.php       # 管理トップ
│   ├── login.php       # ログイン
│   ├── logout.php      # ログアウト
│   ├── topic.php       # お題作成・編集
│   ├── topics.php      # お題一覧
│   ├── posts.php       # 投稿管理
│   ├── setting.php     # サイト設定
│   └── blocked.php     # IPブロック
├── includes/
│   ├── config.php      # 設定ファイル
│   ├── Database.php    # SQLiteラッパー
│   ├── functions.php   # 共通関数
│   └── template.php    # テンプレート関数
├── assets/
│   └── css/style.css   # カスタムCSS
├── data/
│   └── bokegram.sqlite # データベース（自動生成）
├── docker-compose.yml  # Docker設定
├── Dockerfile          # Dockerイメージ定義
└── .htaccess           # Apache設定
```

---

## 設定項目

`includes/config.php` で以下を設定可能：

| 項目 | デフォルト | 説明 |
|------|------------|------|
| `password` | `admin123` | 管理者パスワード（要変更） |
| `site_name` | `Bokegram` | サイト名 |
| `site_description` | - | サイト説明文 |
| `topics_per_page` | `10` | お題一覧の表示件数 |
| `posts_per_page` | `20` | 投稿一覧の表示件数 |
| `post_body_limit` | `200` | 投稿の文字数上限 |
| `comment_body_limit` | `200` | コメントの文字数上限 |
| `ip_post_limit` | `3` | 1IPあたりの投稿上限（お題ごと） |
| `ranking_player` | `50` | プレイヤーランキング表示件数 |
| `ranking_post` | `20` | 作品ランキング表示件数 |

---

## お題のステータス

| ステータス | 説明 |
|------------|------|
| 自動判定 | 日時設定に基づいて自動で切り替わる |
| 投稿受付中 | ユーザーが回答を投稿できる |
| 採点中 | ユーザーが回答に投票できる |
| 結果発表 | 得点とランキングが表示される |
| 準備中 | 投稿開始前の状態 |
| お知らせ | 投稿・採点なしの掲示用 |
| 凍結 | 非表示（管理者のみ閲覧可） |

---

## トラブルシューティング

### Dockerが起動しない
```bash
# ログを確認
docker-compose logs

# イメージを再ビルド
docker-compose up -d --build --force-recreate
```

### 「Permission denied」エラー
```bash
# dataフォルダの権限を確認
chmod 777 data
```

### データベースエラー
```bash
# SQLiteファイルを削除して再作成
rm data/bokegram.sqlite*
```

### CSSが読み込まれない
- ブラウザのキャッシュをクリア
- Pico CSSはCDNから読み込まれるためインターネット接続が必要

---

## ライセンス

MIT License
