<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>系统登录 - PopAPI</title>
    <link href="assets/style.css" rel="stylesheet">
</head>
<body class="login-page-body">

<div class="login-container">
    <h2>系统登录</h2>
    
    <?php if (isset($error_message) && !empty($error_message)): ?>
        <div class="error-msg"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <?php if (isset($_SESSION['login_locked_until']) && time() < $_SESSION['login_locked_until']): ?>
        <?php
            $remaining_time = ceil(($_SESSION['login_locked_until'] - time()) / 60);
            echo "<div class='error-msg'>登录尝试次数过多，请在 {$remaining_time} 分钟后再试。</div>";
        ?>
    <?php else: ?>
        <form method="post" action="admin.php?action=login">
            <div class="form-item" style="margin-bottom:15px; width:100%;">
                <label for="username">用户名</label>
                <input type="text" id="username" name="username" required autocomplete="username" placeholder="请输入管理员账号">
            </div>
            
            <div class="form-item" style="margin-bottom:15px; width:100%;">
                <label for="password">密码</label>
                <input type="password" id="password" name="password" required autocomplete="current-password" placeholder="请输入密码">
            </div>
            
            <div class="form-item" style="margin-bottom:20px; width:100%;">
                <label for="captcha">验证码</label>
                <div class="captcha-container">
                    <input type="text" id="captcha" name="captcha" required autocomplete="off" placeholder="验证码" style="flex:1;">
                    <img src="captcha.php" alt="验证码" onclick="this.src='captcha.php?'+Math.random();" title="点击刷新验证码">
                </div>
            </div>
            
            <button type="submit" class="btn-primary" style="width: 100%; height: 40px; font-size: 16px;">登 录</button>
        </form>
    <?php endif; ?>
</div>

</body>
</html>
