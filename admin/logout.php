<?php
/**
 * 管理者ログアウト
 */

require_once __DIR__ . '/../includes/functions.php';

adminLogout();
setFlash('success', 'ログアウトしました。');
redirect('../index.php');
