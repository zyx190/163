<?php
// install.php
header('Content-Type: text/html; charset=utf-8');
$db_file = __DIR__ . '/database.php';

// 安全防御：如果已安装，必须删除配置才能重装
if (file_exists($db_file)) {
    die('<h3 style="text-align:center;margin-top:50px;color:red;">系统已安装！如需重新安装，请先删除根目录下的 database.php 文件。</h3>');
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $db_host = $_POST['db_host'] ?? '127.0.0.1';
    $db_port = $_POST['db_port'] ?? '3306';
    $db_name = $_POST['db_name'] ?? '';
    $db_user = $_POST['db_user'] ?? '';
    $db_pass = $_POST['db_pass'] ?? '';
    $admin_user = $_POST['admin_user'] ?? 'admin';
    $admin_pass = $_POST['admin_pass'] ?? '123456';

    try {
        $pdo = new PDO("mysql:host=$db_host;port=$db_port;charset=utf8mb4", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `$db_name`");

        // 构建数据表结构
        $sql = "
        CREATE TABLE IF NOT EXISTS admin_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            domain VARCHAR(255) DEFAULT ''
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS classifications (
            id VARCHAR(100) PRIMARY KEY,
            category_name VARCHAR(100) NOT NULL,
            match_keywords TEXT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS phonenumbers (
            phonenumber VARCHAR(50) PRIMARY KEY,
            host VARCHAR(100),
            port VARCHAR(10),
            user VARCHAR(100),
            pass VARCHAR(100),
            match_sender VARCHAR(255)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS used_codes (
            code VARCHAR(50) PRIMARY KEY
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS verification_data (
            code VARCHAR(50) PRIMARY KEY,
            category VARCHAR(100),
            host VARCHAR(100),
            port VARCHAR(10),
            user VARCHAR(100),
            pass VARCHAR(100),
            match_sender TEXT,
            match_keywords TEXT,
            releasedate VARCHAR(50),
            expirationtime VARCHAR(50),
            combination VARCHAR(255),
            is_expired TINYINT(1) DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        $pdo->exec($sql);

        // 注册管理员账号
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_users WHERE username = ?");
        $stmt->execute([$admin_user]);
        if ($stmt->fetchColumn() == 0) {
            $hashed_pass = password_hash($admin_pass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO admin_users (username, password) VALUES (?, ?)");
            $stmt->execute([$admin_user, $hashed_pass]);
        }

        // 写入连接配置
        $config_content = "<?php\n"
            . "// 自动生成的数据库配置文件\n"
            . "return [\n"
            . "    'db_host' => '$db_host',\n"
            . "    'db_port' => '$db_port',\n"
            . "    'db_name' => '$db_name',\n"
            . "    'db_user' => '$db_user',\n"
            . "    'db_pass' => '$db_pass'\n"
            . "];\n";
        file_put_contents($db_file, $config_content);

        $message = '<div class="alert alert-success">建库及安装成功！请务必删除 install.php，然后 <a href="admin.php">点击这里进入后台</a></div>';

    } catch (PDOException $e) {
        $message = '<div class="alert alert-danger">安装失败：' . $e->getMessage() . '</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>PopAPI 数据库自动安装向导</title>
    <link href="https://cdn.bootcdn.net/ajax/libs/twitter-bootstrap/3.3.7/css/bootstrap.min.css" rel="stylesheet">
    <style>body{background:#f4f7f6;} .install-container{max-width: 600px; margin: 50px auto; background:#fff; padding:30px; border-radius:8px; box-shadow:0 0 10px rgba(0,0,0,0.1);}</style>
</head>
<body>
<div class="container install-container">
    <h2 class="text-center" style="margin-bottom: 30px;">系统自动安装向导</h2>
    <?= $message ?>
    <?php if(!file_exists($db_file)): ?>
    <form method="post">
        <h4>1. 数据库配置 (填入宝塔面板分配的库信息)</h4>
        <div class="form-group"><label>数据库地址</label><input type="text" name="db_host" class="form-control" value="127.0.0.1" required></div>
        <div class="form-group"><label>数据库端口</label><input type="text" name="db_port" class="form-control" value="3306" required></div>
        <div class="form-group"><label>数据库名</label><input type="text" name="db_name" class="form-control" required></div>
        <div class="form-group"><label>数据库用户名</label><input type="text" name="db_user" class="form-control" required></div>
        <div class="form-group"><label>数据库密码</label><input type="password" name="db_pass" class="form-control" required></div>
        
        <h4 style="margin-top: 30px;">2. 设置初始后台管理员</h4>
        <div class="form-group"><label>管理员账号</label><input type="text" name="admin_user" class="form-control" value="admin" required></div>
        <div class="form-group"><label>管理员密码</label><input type="text" name="admin_pass" class="form-control" value="123456" required></div>
        
        <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top: 20px;">开始安装建表</button>
    </form>
    <?php endif; ?>
</div>
</body>
</html>
