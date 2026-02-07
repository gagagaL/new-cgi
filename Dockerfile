FROM php:7.4-apache

# SQLite拡張をインストール
RUN apt-get update && apt-get install -y \
    libsqlite3-dev \
    && docker-php-ext-install pdo_sqlite \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Apache mod_rewrite を有効化
RUN a2enmod rewrite headers

# Apache設定（.htaccessを有効化）
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# 作業ディレクトリ
WORKDIR /var/www/html

# ポート
EXPOSE 80
