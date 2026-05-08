<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>后台管理 - PopAPI</title>
    <link href="https://cdn.bootcdn.net/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link href="assets/style.css" rel="stylesheet">
</head>
<body>

    <div class="mobile-header">
        <span style="font-weight: bold; font-size: 18px;">PopAPI 后台</span>
        <button id="menu-toggle" style="border:none; background:transparent; font-size:22px; color:#fff; cursor:pointer;">
            <i class="fas fa-bars"></i>
        </button>
    </div>

    <div class="admin-overlay" id="overlay"></div>

    <div class="admin-container">
        <div class="admin-sidebar" id="sidebar">
            <div class="sidebar-header">
                <a href="admin.php">PopAPI Admin</a>
            </div>
            
            <?php $current_action = $_GET['action'] ?? 'dashboard'; ?>

            <ul class="nav">
                <li class="<?php echo ($current_action === 'dashboard') ? 'active' : ''; ?>">
                    <a href="admin.php?action=dashboard"><i class="fas fa-tachometer-alt"></i> 仪表盘</a>
                </li>
                <li class="<?php echo ($current_action === 'phonenumber') ? 'active' : ''; ?>">
                    <a href="admin.php?action=phonenumber"><i class="fas fa-phone-alt"></i> 电话管理</a>
                </li>
                <li class="<?php echo ($current_action === 'classification') ? 'active' : ''; ?>">
                    <a href="admin.php?action=classification"><i class="fas fa-tags"></i> 分类管理</a>
                </li>
                <li class="<?php echo ($current_action === 'verification_code') ? 'active' : ''; ?>">
                    <a href="admin.php?action=verification_code"><i class="fas fa-sms"></i> 接码管理</a>
                </li>
                <li class="<?php echo ($current_action === 'code_manager') ? 'active' : ''; ?>">
                    <a href="admin.php?action=code_manager"><i class="fas fa-tasks"></i> 查询码管理</a>
                </li>

                <li class="sidebar-menu-item <?php echo ($current_action === 'verification_code_list') ? 'active' : ''; ?>">
                    <a href="#" id="toggle-submenu">
                        <i class="fas fa-list-ul"></i> 接码分类列表 
                        <i class="fas fa-angle-<?php echo ($current_action === 'verification_code_list') ? 'up' : 'down'; ?> pull-right" style="float:right; margin-top:3px;"></i>
                    </a>
                    <ul class="sidebar-submenu" style="<?php echo ($current_action === 'verification_code_list') ? 'display:block;' : ''; ?>">
                        <?php
                        if (function_exists('getClassificationData')) {
                            $classifications = getClassificationData();
                            foreach ($classifications as $classification) {
                                $category = htmlspecialchars($classification['id'] ?? '');
                                $category_name = htmlspecialchars($classification['category_name'] ?? '');
                                $current_category = $_GET['category'] ?? '';
                                $active_class = ($current_action === 'verification_code_list' && $current_category === $category) ? 'current' : '';
                                echo '<li><a href="admin.php?action=verification_code_list&category=' . urlencode($category) . '" class="' . $active_class . '"><i class="far fa-circle" style="font-size:12px;"></i> ' . $category_name . '</a></li>';
                            }
                        }
                        ?>
                    </ul>
                </li>
                <li class="<?php echo ($current_action === 'system_settings') ? 'active' : ''; ?>">
                    <a href="admin.php?action=system_settings"><i class="fas fa-cog"></i> 系统设置</a>
                </li>
                <li>
                    <a href="admin.php?action=logout"><i class="fas fa-sign-out-alt"></i> 退出登录</a>
                </li>
            </ul>
        </div>

        <div class="admin-content">
            <div class="content-card">
                <?php 
                    if (isset($content) && $content) {
                        require $content;
                    }
                ?>
            </div>
        </div>
    </div>

    <script src="assets/main.js"></script>
</body>
</html>
