<?php
// views/system_settings.php
// 该视图用于管理员修改账号名和密码
?>

<h2><i class="fas fa-cog"></i> 系统设置</h2>

<?php if (isset($_SESSION['success_message'])): ?>
    <div style="color: #67C23A; background: #f0f9eb; padding: 10px; border-radius: 4px; margin-bottom: 20px; border: 1px solid #c2e7b0; font-size: 14px;">
        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
    <div class="error-msg">
        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
    </div>
<?php endif; ?>

<div class="settings-form-container" style="max-width: 500px;">
    <form action="admin.php?action=system_settings_save" method="post">
        <div class="form-item" style="margin-bottom: 20px;">
            <label for="username">当前管理员账号名</label>
            <input type="text" id="username" name="username" value="<?= htmlspecialchars($_SESSION['username'] ?? '') ?>" required placeholder="请输入账号名" style="width: 100%;">
        </div>
        
        <div class="form-item" style="margin-bottom: 20px;">
            <label for="new_password">新密码 (如果不修改密码请留空)</label>
            <input type="password" id="new_password" name="new_password" placeholder="请输入新密码" style="width: 100%;">
        </div>

        <div class="form-item" style="margin-bottom: 25px;">
            <label for="confirm_password">确认新密码</label>
            <input type="password" id="confirm_password" name="confirm_password" placeholder="请再次输入新密码" style="width: 100%;">
        </div>

        <div class="form-item" style="margin-bottom: 20px;">
            <label for="domain">域名配置 (用于接码组合内容)</label>
            <input type="text" id="domain" name="domain" value="<?= htmlspecialchars($_SESSION['domain'] ?? '') ?>" placeholder="例如：http://xxx.xxx/" style="width: 100%;">
        </div>
        
        <div class="form-item" style="margin-bottom: 20px;">
            <label for="global_prefix">自定义全接字符 (用于全接收功能的链接前缀)</label>
            <input type="text" id="global_prefix" name="global_prefix" value="<?= htmlspecialchars($_SESSION['global_prefix'] ?? '') ?>" placeholder="例如：zdy" style="width: 100%;">
        </div>

        <div class="form-item">
            <button type="submit" class="btn-primary" style="width: 100%; height: 40px; font-size: 14px;">
                <i class="fas fa-save" style="margin-right: 8px;"></i> 提交修改
            </button>
        </div>
    </form>
</div>
