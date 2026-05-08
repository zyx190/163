<?php
// classes/Db.php
class Db {
    private static $pdo = null;

    public static function get() {
        if (!self::$pdo) {
            $config_file = __DIR__ . '/../database.php';
            if (!file_exists($config_file)) {
                die('数据库配置文件缺失，请先运行 install.php 进行系统安装。');
            }
            $config = require $config_file;
            try {
                $dsn = "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8mb4";
                self::$pdo = new PDO($dsn, $config['db_user'], $config['db_pass']);
                self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } catch (PDOException $e) {
                die('数据库连接失败：' . $e->getMessage());
            }
        }
        return self::$pdo;
    }
}
