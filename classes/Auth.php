<?php
// classes/Auth.php
require_once __DIR__ . '/Db.php';

class Auth {
    public static function checkLogin() {
        return isset($_SESSION['logged_in']);
    }

    public static function doLogin($username, $password) {
        if (isset($_SESSION['login_locked_until']) && time() < $_SESSION['login_locked_until']) {
            return false;
        }

        $stmt = Db::get()->prepare("SELECT * FROM admin_users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // 使用系统内置的哈希验证机制验证密码
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['logged_in'] = true;
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['domain'] = $user['domain'] ?? '';
            $_SESSION['global_prefix'] = $user['global_prefix'] ?? '';
            $_SESSION['login_attempts'] = 0;
            unset($_SESSION['login_locked_until']);
            return true;
        } else {
            $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
            if ($_SESSION['login_attempts'] >= 3) {
                $_SESSION['login_locked_until'] = time() + (5 * 60); // 错3次锁定5分钟
            }
            return false;
        }
    }

    public static function doLogout() {
        session_destroy();
    }
}
