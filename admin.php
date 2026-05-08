<?php
// admin.php (基于 MySQL 架构重构版 - Part 1)

session_start();

spl_autoload_register(function ($className) {
    $file = __DIR__ . "/classes/{$className}.php";
    if (file_exists($file)) {
        require_once $file;
    }
});

// 如果没有配置文件，引导去安装
if (!file_exists(__DIR__ . '/database.php') && basename($_SERVER['PHP_SELF']) !== 'install.php') {
    die('<meta charset="UTF-8"><h3 style="text-align:center;margin-top:50px;">系统未安装！请先运行 <a href="install.php">install.php</a></h3>');
}

$allowedActions = [
    'login' => 'views/login.php',
    'dashboard' => 'views/dashboard.php',
    'classification' => 'views/classification.php',
    'phonenumber' => 'views/phonenumber.php',
    'verification_code' => 'views/verification_code.php',
    'verification_code_list' => 'views/verification_code.php',
    'expired_phones' => 'views/expired_phones.php',
    'code_manager' => 'views/code_manager.php',
    'system_settings' => 'views/system_settings.php', // [新增] 系统设置页面
    
    // 以下为纯逻辑动作，没有独立视图
    'system_settings_save' => null, 
    'used_codes_save' => null,
    'used_codes_delete' => null,
    'used_codes_bulk_delete' => null,
    'classification_save' => null,
    'classification_delete' => null,
    'classification_bulk_delete' => null,
    'classification_edit' => null,
    'phonenumber_save' => null,
    'phonenumber_delete' => null,
    'phonenumber_edit' => null,
    'phonenumber_bulk_delete' => null,
    'phonenumber_bulk_save' => null,
    'verification_code_save' => null,
    'verification_code_sync' => null,
    'verification_code_bulk_delete' => null,
    'verification_code_bulk_update' => null,
    'verification_code_edit' => null,
    'verification_code_delete' => null,
    'verification_code_bulk_save' => null,
    'verification_code_bulk_move' => null,
    'verification_code_move_expired' => null,
    'verification_code_update_all_content' => null,
    'expired_phones_bulk_delete' => null,
    'expired_phones_delete' => null,
    'expired_phones_edit' => null,
    'export_selected_combinations' => null,
    'export_all_combinations' => null,
    'logout' => null,
    'export_phonenumbers' => null,
    'export_selected_phonenumbers' => null,
];

$action = $_GET['action'] ?? 'dashboard';
$content = null;
$error_message = '';
$verificationData = [];

// =========================================================================
// 核心数据函数区 (将原 JSON 逻辑完美映射为 MySQL，确保前端试图0修改兼容)
// =========================================================================

function getUsedCodesData() {
    $stmt = Db::get()->query("SELECT code FROM used_codes");
    return $stmt->fetchAll(PDO::FETCH_COLUMN); // 返回如 ['CODE1', 'CODE2']
}

function generate_unique_verification_code() {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $pdo = Db::get();
    
    do {
        $random_code = '';
        for ($i = 0; $i < 10; $i++) {
            $random_code .= $characters[rand(0, $charactersLength - 1)];
        }
        // 查库排重
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM used_codes WHERE code = ?");
        $stmt->execute([$random_code]);
    } while ($stmt->fetchColumn() > 0);

    // 自动入库
    $stmt = $pdo->prepare("INSERT INTO used_codes (code) VALUES (?)");
    $stmt->execute([$random_code]);
    return $random_code;
}

function getClassificationData() {
    $stmt = Db::get()->query("SELECT * FROM classifications");
    $data = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // 将以逗号分割的字符串还原为前端需要的数组格式
        $row['match_keywords'] = empty($row['match_keywords']) ? [] : explode(',', $row['match_keywords']);
        $data[] = $row;
    }
    return $data;
}

function getPhoneNumberData() {
    $stmt = Db::get()->query("SELECT * FROM phonenumbers");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getAllVerificationData() {
    $stmt = Db::get()->query("SELECT * FROM verification_data WHERE is_expired = 0");
    $data = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $code = $row['code'];
        $data[$code] = [
            'category' => $row['category'],
            'host' => $row['host'],
            'port' => $row['port'],
            'user' => $row['user'],
            'pass' => $row['pass'],
            'match_keywords' => empty($row['match_keywords']) ? [] : explode(',', $row['match_keywords']),
            'match_sender' => [$row['match_sender']],
            'releasedate' => [$row['releasedate']],
            'expirationtime' => [$row['expirationtime']],
            'combination' => [$row['combination']]
        ];
    }
    return $data;
}

function getExpiredPhonesData() {
    $stmt = Db::get()->query("SELECT * FROM verification_data WHERE is_expired = 1");
    $data = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $code = $row['code'];
        $data[$code] = [
            'category' => $row['category'],
            'host' => $row['host'],
            'port' => $row['port'],
            'user' => $row['user'],
            'pass' => $row['pass'],
            'match_keywords' => empty($row['match_keywords']) ? [] : explode(',', $row['match_keywords']),
            'match_sender' => [$row['match_sender']],
            'releasedate' => [$row['releasedate']],
            'expirationtime' => [$row['expirationtime']],
            'combination' => [$row['combination']]
        ];
    }
    return $data;
}

function isPhoneNumberInCategory($phoneNumber, $categoryName, $excludeCode = null) {
    // 检查是否存在相同号码且同分类的数据（包括已过期和未过期）
    $sql = "SELECT COUNT(*) FROM verification_data WHERE category = ? AND combination LIKE ? AND code != ?";
    $stmt = Db::get()->prepare($sql);
    $likeStr = $phoneNumber . '---%';
    $stmt->execute([$categoryName, $likeStr, $excludeCode ?? '']);
    return $stmt->fetchColumn() > 0;
}

function getAllVerificationCodes() {
    return getUsedCodesData();
}

// 登出与登录拦截逻辑保持不变...
if ($action === 'logout') {
    Auth::doLogout();
    header('Location: admin.php?action=login');
    exit;
}

// =========================================================================
// 补充缺失的 login 登录验证逻辑 (替换掉您代码里的占位符)
// =========================================================================
if ($action === 'login') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_SESSION['login_locked_until']) && time() < $_SESSION['login_locked_until']) {
            $remaining_time = ceil(($_SESSION['login_locked_until'] - time()) / 60);
            $error_message = "登录尝试次数过多，请在 {$remaining_time} 分钟后再试。";
        } else {
            if (!isset($_POST['captcha']) || !isset($_SESSION['captcha_text']) || strtolower($_POST['captcha']) !== strtolower($_SESSION['captcha_text'])) {
                $error_message = '验证码不正确。';
                unset($_SESSION['captcha_text']);
            } else {
                unset($_SESSION['captcha_text']);
                if (Auth::doLogin($_POST['username'], $_POST['password'])) {
                    header('Location: admin.php');
                    exit;
                } else {
                    if (isset($_SESSION['login_locked_until']) && time() < $_SESSION['login_locked_until']) {
                        $remaining_time = ceil(($_SESSION['login_locked_until'] - time()) / 60);
                        $error_message = "用户名或密码错误。尝试次数过多，请在 {$remaining_time} 分钟后再试。";
                    } else {
                        $attempts_left = 3 - ($_SESSION['login_attempts'] ?? 0);
                        $error_message = "用户名或密码错误。还剩 {$attempts_left} 次尝试机会。";
                    }
                }
            }
        }
    }
    require 'views/login.php';
    exit;
}

// =========================================================================
// 接码管理的 批量添加、编辑、同步、一键更新 (放在动作处理区)
// =========================================================================

// 批量添加接码数据
if ($action === 'verification_code_bulk_save') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['bulk_data']) && !empty($_POST['bulk_category_name'])) {
        $lines = explode("\n", $_POST['bulk_data']);
        $categoryName = $_POST['bulk_category_name'];
        $daysToExpire = (float)($_POST['bulk_days_to_expire'] ?? 0);
        $user_content = $_POST['bulk_user_content'] ?? '';
        
        $pdo = Db::get();
        // 获取分类
        $stmt = $pdo->prepare("SELECT * FROM classifications WHERE id = ?");
        $stmt->execute([$categoryName]);
        $matchedClassification = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$matchedClassification) {
            $_SESSION['error_message'] = '选择的分类不存在。';
            header('Location: admin.php?action=verification_code');
            exit;
        }

        $successCount = 0; $errorMessages = []; $failedLines = []; $successful_combinations = [];

        // 预查电话表
        $phones = [];
        $stmt = $pdo->query("SELECT * FROM phonenumbers");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { $phones[$row['phonenumber']] = $row; }

        $pdo->beginTransaction();
        try {
            $insertStmt = $pdo->prepare("INSERT INTO verification_data (code, category, host, port, user, pass, match_sender, match_keywords, releasedate, expirationtime, combination, is_expired) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)");
            $codeStmt = $pdo->prepare("INSERT IGNORE INTO used_codes (code) VALUES (?)");
            $checkCodeStmt = $pdo->prepare("SELECT COUNT(*) FROM used_codes WHERE code = ?");

            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                
                $parts = preg_split('/\s*---\s*|\s+/', $line, -1, PREG_SPLIT_NO_EMPTY);
                $phonenumber = trim($parts[0]);
                $verification_code = isset($parts[1]) ? trim($parts[1]) : '';

                if (empty($verification_code)) {
                    // 使用内置函数生成
                    $verification_code = generate_unique_verification_code();
                } else {
                    $checkCodeStmt->execute([$verification_code]);
                    if ($checkCodeStmt->fetchColumn() > 0) {
                        $errorMessages[] = "查询码已存在: {$verification_code}"; $failedLines[] = $line; continue;
                    }
                }

                if (isPhoneNumberInCategory($phonenumber, $categoryName)) { 
                    $errorMessages[] = "电话({$phonenumber})在分类({$categoryName})已存在"; $failedLines[] = $line; continue; 
                }
                
                if (!isset($phones[$phonenumber])) { 
                    $errorMessages[] = "电话号不存在: {$phonenumber}"; $failedLines[] = $line; continue; 
                }
                
                $matchedPhoneNumber = $phones[$phonenumber];
                $combination = $phonenumber . '---' . $user_content . '/' . $verification_code;
                $releasedate = (new DateTime())->format('Y-m-d H:i:s');
                $expirationtime = $daysToExpire . '天';

                $insertStmt->execute([
                    $verification_code, $categoryName, $matchedPhoneNumber['host'], $matchedPhoneNumber['port'] ?? '995',
                    $matchedPhoneNumber['user'], $matchedPhoneNumber['pass'], $matchedPhoneNumber['match_sender'],
                    $matchedClassification['match_keywords'], $releasedate, $expirationtime, $combination
                ]);
                $codeStmt->execute([$verification_code]);
                
                $successful_combinations[] = $combination; 
                $successCount++;
            }
            $pdo->commit();
            if ($successCount > 0) $_SESSION['bulk_add_success_details'] = $successful_combinations;
        } catch (Exception $e) {
            $pdo->rollBack();
            $errorMessages[] = "数据库错误: " . $e->getMessage();
        }

        if (!empty($errorMessages)) {
            $_SESSION['error_message'] = "部分数据添加失败：<br>" . implode("<br>", $errorMessages);
            $_SESSION['bulk_data_to_retain'] = implode("\n", $failedLines);
        } else {
            $_SESSION['success_message'] = "成功批量添加 {$successCount} 条数据。";
        }
    }
    header('Location: admin.php?action=verification_code');
    exit;
}

// 编辑单条接码数据
if ($action === 'verification_code_edit') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['original_code']) && !empty($_POST['phonenumber']) && !empty($_POST['category_name']) && !empty($_POST['verification_code'])) {
        $newCode = trim($_POST['verification_code']);
        $originalCode = $_POST['original_code'];
        $newCategory = $_POST['category_name'];
        $newPhoneNumber = $_POST['phonenumber'];
        $pdo = Db::get();

        if ($originalCode !== $newCode) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM used_codes WHERE code = ?");
            $stmt->execute([$newCode]);
            if ($stmt->fetchColumn() > 0) {
                $_SESSION['error_message'] = '新查询码已存在，请重新填写。';
                header('Location: admin.php?action=verification_code');
                exit;
            }
        }
        
        if (isPhoneNumberInCategory($newPhoneNumber, $newCategory, $originalCode)) {
            $_SESSION['error_message'] = '该电话在同分类中已存在。';
            header('Location: admin.php?action=verification_code');
            exit;
        }

        $stmt = $pdo->prepare("SELECT * FROM phonenumbers WHERE phonenumber = ?");
        $stmt->execute([$newPhoneNumber]);
        $matchedPhoneNumber = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("SELECT match_keywords FROM classifications WHERE id = ?");
        $stmt->execute([$newCategory]);
        $match_keywords = $stmt->fetchColumn();

        if ($matchedPhoneNumber) {
            $combination = $newPhoneNumber . '---' . ($_POST['user_content'] ?? '') . '/' . $newCode;
            $expirationtime = ((float)($_POST['days_to_expire'] ?? 0)) . '天';
            $releasedate = (new DateTime())->format('Y-m-d H:i:s');

            $stmt = $pdo->prepare("UPDATE verification_data SET code=?, category=?, host=?, port=?, user=?, pass=?, match_sender=?, match_keywords=?, releasedate=?, expirationtime=?, combination=? WHERE code=?");
            $stmt->execute([
                $newCode, $newCategory, $matchedPhoneNumber['host'], $matchedPhoneNumber['port'] ?? '995',
                $matchedPhoneNumber['user'], $matchedPhoneNumber['pass'], $matchedPhoneNumber['match_sender'],
                $match_keywords, $releasedate, $expirationtime, $combination, $originalCode
            ]);
            
            if ($originalCode !== $newCode) {
                $pdo->prepare("DELETE FROM used_codes WHERE code = ?")->execute([$originalCode]);
                $pdo->prepare("INSERT IGNORE INTO used_codes (code) VALUES (?)")->execute([$newCode]);
            }
            $_SESSION['success_message'] = '已成功编辑该条数据。';
        }
    }
    header('Location: admin.php?action=verification_code');
    exit;
}

// 恢复过期电话
if ($action === 'expired_phones_edit') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['original_code'])) {
        // ... (与 verification_code_edit 几乎一致，只需要在 UPDATE 语句中加上 is_expired = 0)
        $pdo = Db::get();
        $stmt = $pdo->prepare("UPDATE verification_data SET is_expired = 0, releasedate=?, expirationtime=? WHERE code=?");
        $stmt->execute([(new DateTime())->format('Y-m-d H:i:s'), ((float)($_POST['days_to_expire'] ?? 0)) . '天', $_POST['original_code']]);
        $_SESSION['success_message'] = '已成功恢复该数据到正常接码列表中。';
    }
    header('Location: admin.php?action=expired_phones');
    exit;
}
if (!Auth::checkLogin()) {
    header('Location: admin.php?action=login');
    exit;
}
// =========================================================================
// 动作处理区：系统设置、电话、分类的管理 (基于 MySQL)
// =========================================================================

// [新增] 系统设置保存逻辑
if ($action === 'system_settings_save') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $new_username = trim($_POST['username']);
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        $user_id = $_SESSION['user_id']; // 从登录 session 中获取当前管理员ID

        if (empty($new_username)) {
            $_SESSION['error_message'] = "账号名不能为空！";
        } elseif (!empty($new_password) && $new_password !== $confirm_password) {
            $_SESSION['error_message'] = "两次输入的新密码不一致！";
        } else {
            $pdo = Db::get();
            try {
                if (!empty($new_password)) {
                    $hashed_pass = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE admin_users SET username = ?, password = ? WHERE id = ?");
                    $stmt->execute([$new_username, $hashed_pass, $user_id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE admin_users SET username = ? WHERE id = ?");
                    $stmt->execute([$new_username, $user_id]);
                }
                $_SESSION['username'] = $new_username; // 更新session中的名字
                $_SESSION['success_message'] = "系统设置修改成功！";
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "修改失败，可能是账号名已存在。";
            }
        }
    }
    header('Location: admin.php?action=system_settings');
    exit;
}

// ---------------- 分类管理 (Classifications) ----------------

if ($action === 'classification_save') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['category_name']) && !empty($_POST['match_keywords'])) {
        $pdo = Db::get();
        $id = $_POST['category_name'];
        $match_keywords = implode(',', array_map('trim', explode(',', $_POST['match_keywords'])));
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM classifications WHERE id = ?");
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() > 0) {
            header('Location: admin.php?action=classification&error=duplicate');
            exit;
        }
        
        $stmt = $pdo->prepare("INSERT INTO classifications (id, category_name, match_keywords) VALUES (?, ?, ?)");
        $stmt->execute([$id, $id, $match_keywords]);
    }
    header('Location: admin.php?action=classification');
    exit;
}

if ($action === 'classification_delete') {
    if (isset($_GET['id'])) {
        $stmt = Db::get()->prepare("DELETE FROM classifications WHERE id = ?");
        $stmt->execute([$_GET['id']]);
    }
    header('Location: admin.php?action=classification');
    exit;
}

if ($action === 'classification_bulk_delete') {
    if (isset($_POST['selected_items']) && is_array($_POST['selected_items'])) {
        $pdo = Db::get();
        $inQuery = implode(',', array_fill(0, count($_POST['selected_items']), '?'));
        $stmt = $pdo->prepare("DELETE FROM classifications WHERE id IN ($inQuery)");
        $stmt->execute($_POST['selected_items']);
    }
    header('Location: admin.php?action=classification');
    exit;
}

if ($action === 'classification_edit') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id']) && !empty($_POST['category_name'])) {
        $match_keywords = implode(',', array_map('trim', explode(',', $_POST['match_keywords'])));
        $stmt = Db::get()->prepare("UPDATE classifications SET category_name = ?, match_keywords = ? WHERE id = ?");
        $stmt->execute([$_POST['category_name'], $match_keywords, $_POST['id']]);
    }
    header('Location: admin.php?action=classification');
    exit;
}

// ---------------- 电话管理 (Phone Numbers) ----------------

if ($action === 'phonenumber_save') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['phonenumber'])) {
        $stmt = Db::get()->prepare("INSERT INTO phonenumbers (phonenumber, host, port, user, pass, match_sender) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE host=VALUES(host), port=VALUES(port), user=VALUES(user), pass=VALUES(pass), match_sender=VALUES(match_sender)");
        $stmt->execute([
            $_POST['phonenumber'],
            $_POST['host'] ?? '',
            $_POST['port'] ?? '995',
            $_POST['user'] ?? '',
            $_POST['pass'] ?? '',
            $_POST['match_sender'] ?? ''
        ]);
    }
    header('Location: admin.php?action=phonenumber');
    exit;
}

if ($action === 'phonenumber_delete') {
    if (isset($_GET['id'])) {
        $stmt = Db::get()->prepare("DELETE FROM phonenumbers WHERE phonenumber = ?");
        $stmt->execute([$_GET['id']]);
    }
    header('Location: admin.php?action=phonenumber');
    exit;
}

if ($action === 'phonenumber_edit') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['original_id']) && !empty($_POST['phonenumber'])) {
        $stmt = Db::get()->prepare("UPDATE phonenumbers SET phonenumber=?, host=?, port=?, user=?, pass=?, match_sender=? WHERE phonenumber=?");
        $stmt->execute([
            $_POST['phonenumber'],
            $_POST['host'] ?? '',
            $_POST['port'] ?? '995',
            $_POST['user'] ?? '',
            $_POST['pass'] ?? '',
            $_POST['match_sender'] ?? '',
            $_POST['original_id']
        ]);
    }
    header('Location: admin.php?action=phonenumber');
    exit;
}

if ($action === 'phonenumber_bulk_delete') {
    if (isset($_POST['selected_items']) && is_array($_POST['selected_items'])) {
        $inQuery = implode(',', array_fill(0, count($_POST['selected_items']), '?'));
        $stmt = Db::get()->prepare("DELETE FROM phonenumbers WHERE phonenumber IN ($inQuery)");
        $stmt->execute($_POST['selected_items']);
    }
    header('Location: admin.php?action=phonenumber');
    exit;
}

if ($action === 'phonenumber_bulk_save') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['bulk_data'])) {
        $pdo = Db::get();
        $lines = explode("\n", $_POST['bulk_data']);
        $stmt = $pdo->prepare("INSERT INTO phonenumbers (host, port, user, pass, match_sender, phonenumber) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE host=VALUES(host), port=VALUES(port), user=VALUES(user), pass=VALUES(pass), match_sender=VALUES(match_sender)");
        
        $pdo->beginTransaction();
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            $parts = preg_split('/\s*---\s*|\s+/', $line, -1, PREG_SPLIT_NO_EMPTY);
            if (count($parts) === 6) {
                $stmt->execute([trim($parts[0]), trim($parts[1]), trim($parts[2]), trim($parts[3]), trim($parts[4]), trim($parts[5])]);
            }
        }
        $pdo->commit();
    }
    header('Location: admin.php?action=phonenumber');
    exit;
}

// ---------------- 查询码管理 (Used Codes) ----------------
if ($action === 'used_codes_save') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['verification_code'])) {
        $newCode = trim($_POST['verification_code']);
        try {
            $stmt = Db::get()->prepare("INSERT INTO used_codes (code) VALUES (?)");
            $stmt->execute([$newCode]);
            $_SESSION['success_message'] = '查询码添加成功，已加入已使用列表。';
        } catch (PDOException $e) {
            $_SESSION['error_message'] = '该查询码已存在于列表中。';
        }
    }
    header('Location: admin.php?action=code_manager');
    exit;
}

if ($action === 'used_codes_delete') {
    if (isset($_GET['code'])) {
        $stmt = Db::get()->prepare("DELETE FROM used_codes WHERE code = ?");
        $stmt->execute([$_GET['code']]);
        $_SESSION['success_message'] = '查询码删除成功 (该码现在可以被再次生成了)。';
    }
    header('Location: admin.php?action=code_manager');
    exit;
}

if ($action === 'used_codes_bulk_delete') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_items']) && is_array($_POST['selected_items'])) {
        $inQuery = implode(',', array_fill(0, count($_POST['selected_items']), '?'));
        $stmt = Db::get()->prepare("DELETE FROM used_codes WHERE code IN ($inQuery)");
        $stmt->execute($_POST['selected_items']);
        $_SESSION['success_message'] = '批量删除成功。';
    }
    header('Location: admin.php?action=code_manager');
    exit;
}
// =========================================================================
// 核心接码管理 (Verification Code) 逻辑 (完全重写为 MySQL)
// =========================================================================

if ($action === 'verification_code_save') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['phonenumber']) && !empty($_POST['category_name']) && isset($_POST['days_to_expire'])) {
        $verification_code = trim($_POST['verification_code']);
        
        if (empty($verification_code)) {
            $verification_code = generate_unique_verification_code();
        } else {
            // 查库看码是否已存在
            $stmt = Db::get()->prepare("SELECT COUNT(*) FROM used_codes WHERE code = ?");
            $stmt->execute([$verification_code]);
            if ($stmt->fetchColumn() > 0) {
                $_SESSION['error_message'] = '自定义查询码已存在，请重新填写。';
                header('Location: admin.php?action=verification_code');
                exit;
            }
        }

        if (isPhoneNumberInCategory($_POST['phonenumber'], $_POST['category_name'])) {
            $_SESSION['error_message'] = '该电话('.htmlspecialchars($_POST['phonenumber']).')在同分类('.htmlspecialchars($_POST['category_name']).')已存在。';
            header('Location: admin.php?action=verification_code');
            exit;
        }

        $pdo = Db::get();
        // 获取电话详情
        $stmt = $pdo->prepare("SELECT * FROM phonenumbers WHERE phonenumber = ?");
        $stmt->execute([$_POST['phonenumber']]);
        $matchedPhoneNumber = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$matchedPhoneNumber) {
            $_SESSION['error_message'] = '暂无(' . htmlspecialchars($_POST['phonenumber']) . ')这个电话号的资料';
            header('Location: admin.php?action=verification_code');
            exit;
        }

        // 获取分类详情
        $stmt = $pdo->prepare("SELECT * FROM classifications WHERE id = ?");
        $stmt->execute([$_POST['category_name']]);
        $matchedClassification = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($matchedClassification) {
            $combination = $matchedPhoneNumber['phonenumber'] . '---' . $_POST['user_content'] . '/' . $verification_code;
            $releasedate = (new DateTime())->format('Y-m-d H:i:s');
            $expirationtime = ((float)$_POST['days_to_expire']) . '天';

            try {
                $pdo->beginTransaction();
                // 插入接码数据
                $stmt = $pdo->prepare("INSERT INTO verification_data (code, category, host, port, user, pass, match_sender, match_keywords, releasedate, expirationtime, combination, is_expired) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)");
                $stmt->execute([
                    $verification_code, $_POST['category_name'], $matchedPhoneNumber['host'], 
                    $matchedPhoneNumber['port'] ?? '995', $matchedPhoneNumber['user'], $matchedPhoneNumber['pass'], 
                    $matchedPhoneNumber['match_sender'], $matchedClassification['match_keywords'], 
                    $releasedate, $expirationtime, $combination
                ]);
                
                // 记录已使用码
                $stmt = $pdo->prepare("INSERT IGNORE INTO used_codes (code) VALUES (?)");
                $stmt->execute([$verification_code]);
                
                $pdo->commit();
                $_SESSION['success_message'] = '接码数据添加成功！';
            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION['error_message'] = '添加失败：' . $e->getMessage();
            }
        }
    }
    header('Location: admin.php?action=verification_code');
    exit;
}

if ($action === 'verification_code_delete') {
    if (isset($_GET['code'])) {
        $stmt = Db::get()->prepare("DELETE FROM verification_data WHERE code = ?");
        $stmt->execute([$_GET['code']]);
        $_SESSION['success_message'] = '已成功删除该条数据。';
    }
    header('Location: admin.php?action=verification_code');
    exit;
}

if ($action === 'verification_code_bulk_delete') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_items']) && is_array($_POST['selected_items'])) {
        $inQuery = implode(',', array_fill(0, count($_POST['selected_items']), '?'));
        $stmt = Db::get()->prepare("DELETE FROM verification_data WHERE code IN ($inQuery)");
        $stmt->execute($_POST['selected_items']);
        $_SESSION['success_message'] = '已成功批量删除选定的数据。';
    }
    header('Location: admin.php?action=verification_code');
    exit;
}

// =========================================================================
// 接码数据的高级同步与更新操作
// =========================================================================

// 1. 一键更新所有接码数据的“输入填写的内容”
if ($action === 'verification_code_update_all_content') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_all_user_content'])) {
        $new_content = $_POST['update_all_user_content'];
        $pdo = Db::get();
        $stmt = $pdo->query("SELECT code, combination FROM verification_data");
        $updateStmt = $pdo->prepare("UPDATE verification_data SET combination = ? WHERE code = ?");
        
        $pdo->beginTransaction();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $original_combination = $row['combination'] ?? '';
            $phone_and_content = explode('---', $original_combination, 2);
            $phone_number = $phone_and_content[0];
            $content_and_code = $phone_and_content[1] ?? '';
            $last_slash_pos = strrpos($content_and_code, '/');
            
            $verification_code_from_combo = ($last_slash_pos !== false) ? substr($content_and_code, $last_slash_pos + 1) : '';
            
            if (!empty($phone_number) && !empty($verification_code_from_combo)) {
                $new_combo = $phone_number . '---' . $new_content . '/' . $verification_code_from_combo;
                $updateStmt->execute([$new_combo, $row['code']]);
            }
        }
        $pdo->commit();
        $_SESSION['success_message'] = '已成功更新所有接码数据的“输入填写的内容”。';
    } else {
        $_SESSION['error_message'] = '未能获取要更新的内容。';
    }
    header('Location: admin.php?action=verification_code');
    exit;
}

// 2. 一键同步所有接码数据（当修改了电话密码或分类关键字后，一键同步到已生成的接码数据中）
if ($action === 'verification_code_sync') {
    $pdo = Db::get();
    $phones = [];
    $stmt = $pdo->query("SELECT * FROM phonenumbers");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { $phones[$row['phonenumber']] = $row; }

    $classes = [];
    $stmt = $pdo->query("SELECT * FROM classifications");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { $classes[$row['id']] = $row; }

    $stmt = $pdo->query("SELECT code, combination, category FROM verification_data");
    $updateStmt = $pdo->prepare("UPDATE verification_data SET host=?, port=?, user=?, pass=?, match_sender=?, match_keywords=? WHERE code=?");

    $pdo->beginTransaction();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $phone = explode('---', $row['combination'])[0] ?? '';
        $cat = $row['category'];
        
        if (isset($phones[$phone])) {
            $p = $phones[$phone];
            $c = $classes[$cat] ?? ['match_keywords' => ''];
            $updateStmt->execute([
                $p['host'], $p['port'] ?? '995', $p['user'], $p['pass'], $p['match_sender'],
                $c['match_keywords'], $row['code']
            ]);
        }
    }
    $pdo->commit();
    $_SESSION['success_message'] = '已成功同步所有接码数据。';
    header('Location: admin.php?action=verification_code');
    exit;
}

// 3. 批量更新选中的接码数据（同上，但只针对勾选的数据）
if ($action === 'verification_code_bulk_update') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_items']) && is_array($_POST['selected_items'])) {
        $pdo = Db::get();
        $phones = [];
        $stmt = $pdo->query("SELECT * FROM phonenumbers");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { $phones[$row['phonenumber']] = $row; }

        $classes = [];
        $stmt = $pdo->query("SELECT * FROM classifications");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { $classes[$row['id']] = $row; }

        $inQuery = implode(',', array_fill(0, count($_POST['selected_items']), '?'));
        $stmt = $pdo->prepare("SELECT code, combination, category FROM verification_data WHERE code IN ($inQuery)");
        $stmt->execute($_POST['selected_items']);

        $updateStmt = $pdo->prepare("UPDATE verification_data SET host=?, port=?, user=?, pass=?, match_sender=?, match_keywords=? WHERE code=?");

        $pdo->beginTransaction();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $phone = explode('---', $row['combination'])[0] ?? '';
            $cat = $row['category'];
            if (isset($phones[$phone])) {
                $p = $phones[$phone];
                $c = $classes[$cat] ?? ['match_keywords' => ''];
                $updateStmt->execute([
                    $p['host'], $p['port'] ?? '995', $p['user'], $p['pass'], $p['match_sender'],
                    $c['match_keywords'], $row['code']
                ]);
            }
        }
        $pdo->commit();
        $_SESSION['success_message'] = '已成功批量更新选定的数据。';
    }
    header('Location: admin.php?action=verification_code');
    exit;
}

if ($action === 'verification_code_move_expired') {
    $pdo = Db::get();
    $stmt = $pdo->query("SELECT code, releasedate, expirationtime FROM verification_data WHERE is_expired = 0");
    $movedCount = 0;
    
    $updateStmt = $pdo->prepare("UPDATE verification_data SET is_expired = 1 WHERE code = ?");
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $releaseDate = $row['releasedate'];
        preg_match('/^(\d+(\.\d+)?)天$/', $row['expirationtime'] ?? '0天', $matches);
        $daysToAdd = (float)($matches[1] ?? 0);
        $expirationTimestamp = $releaseDate ? (new DateTime($releaseDate))->getTimestamp() + ($daysToAdd * 24 * 3600) : 0;
        
        if (time() > $expirationTimestamp) {
            $updateStmt->execute([$row['code']]);
            $movedCount++;
        }
    }
    
    $_SESSION['success_message'] = "已成功移动 {$movedCount} 条过期数据到过期列表。";
    header('Location: admin.php?action=verification_code');
    exit;
}

if ($action === 'verification_code_bulk_move') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_items']) && is_array($_POST['selected_items'])) {
        $inQuery = implode(',', array_fill(0, count($_POST['selected_items']), '?'));
        $stmt = Db::get()->prepare("UPDATE verification_data SET is_expired = 1 WHERE code IN ($inQuery)");
        $stmt->execute($_POST['selected_items']);
        $movedCount = $stmt->rowCount();
        $_SESSION['success_message'] = "已成功移动 {$movedCount} 条数据到过期列表。";
    }
    header('Location: admin.php?action=verification_code');
    exit;
}

if ($action === 'expired_phones_bulk_delete') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_items']) && is_array($_POST['selected_items'])) {
        $inQuery = implode(',', array_fill(0, count($_POST['selected_items']), '?'));
        $stmt = Db::get()->prepare("DELETE FROM verification_data WHERE is_expired = 1 AND code IN ($inQuery)");
        $stmt->execute($_POST['selected_items']);
        $_SESSION['success_message'] = '已成功批量删除选定的过期数据。';
    }
    header('Location: admin.php?action=expired_phones');
    exit;
}

if ($action === 'expired_phones_delete') {
    if (isset($_GET['code'])) {
        $stmt = Db::get()->prepare("DELETE FROM verification_data WHERE is_expired = 1 AND code = ?");
        $stmt->execute([$_GET['code']]);
        $_SESSION['success_message'] = '已成功删除该条过期数据。';
    }
    header('Location: admin.php?action=expired_phones');
    exit;
}

// 导出相关功能
if ($action === 'export_selected_combinations' || $action === 'export_all_combinations') {
    $pdo = Db::get();
    $output = [];
    if ($action === 'export_selected_combinations' && isset($_POST['selected_items']) && is_array($_POST['selected_items'])) {
        $inQuery = implode(',', array_fill(0, count($_POST['selected_items']), '?'));
        $stmt = $pdo->prepare("SELECT combination FROM verification_data WHERE code IN ($inQuery)");
        $stmt->execute($_POST['selected_items']);
    } else {
        $stmt = $pdo->query("SELECT combination FROM verification_data");
    }
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (!empty($row['combination'])) {
            $output[] = $row['combination'];
        }
    }
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="combinations.txt"');
    echo implode("\n", $output);
    exit;
}
// 导出电话管理数据
if ($action === 'export_phonenumbers' || $action === 'export_selected_phonenumbers') {
    $pdo = Db::get();
    $output = [];
    if ($action === 'export_selected_phonenumbers' && isset($_POST['selected_items']) && is_array($_POST['selected_items'])) {
        $inQuery = implode(',', array_fill(0, count($_POST['selected_items']), '?'));
        $stmt = $pdo->prepare("SELECT host, port, user, pass, match_sender, phonenumber FROM phonenumbers WHERE phonenumber IN ($inQuery)");
        $stmt->execute($_POST['selected_items']);
    } else {
        $stmt = $pdo->query("SELECT host, port, user, pass, match_sender, phonenumber FROM phonenumbers");
    }
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // 以空格分隔，兼容你的批量添加格式
        $output[] = sprintf("%s %s %s %s %s %s", 
            $row['host'], 
            $row['port'], 
            $row['user'], 
            $row['pass'], 
            $row['match_sender'], 
            $row['phonenumber']
        );
    }
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="phonenumbers.txt"');
    echo implode("\n", $output);
    exit;
}
// =========================================================================
// 视图与数据组装逻辑 (兼容前端布局)
// =========================================================================

// 获取仪表盘统计数据
function getDashboardData() {
    $pdo = Db::get();
    $dashboardData = [];
    
    $dashboardData['total_phones'] = $pdo->query("SELECT COUNT(*) FROM phonenumbers")->fetchColumn();
    $dashboardData['total_added_codes'] = $pdo->query("SELECT COUNT(*) FROM verification_data WHERE is_expired = 0")->fetchColumn();
    $dashboardData['total_expired_codes'] = $pdo->query("SELECT COUNT(*) FROM verification_data WHERE is_expired = 1")->fetchColumn();
    
    // 按分类统计有效接码
    $stmt = $pdo->query("SELECT category, COUNT(*) as cnt FROM verification_data WHERE is_expired = 0 GROUP BY category");
    $addedData = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { $addedData[$row['category']] = $row['cnt']; }
    $dashboardData['added_codes_by_category'] = $addedData;

    // 按分类统计过期接码
    $stmt = $pdo->query("SELECT category, COUNT(*) as cnt FROM verification_data WHERE is_expired = 1 GROUP BY category");
    $expiredDataByCategory = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { $expiredDataByCategory[$row['category']] = $row['cnt']; }
    $dashboardData['expired_codes_by_category'] = $expiredDataByCategory;
    
    return $dashboardData;
}

// 页面数据分发逻辑
if ($action === 'code_manager') {
    $allCodes = getUsedCodesData();
    $search_term = trim($_GET['search_term'] ?? '');
    if (!empty($search_term)) {
        $verificationData = array_values(array_filter($allCodes, fn($code) => stripos($code, $search_term) !== false));
    } else {
        $verificationData = $allCodes;
    }
} elseif ($action === 'dashboard') {
    $dashboardData = getDashboardData();
} elseif ($action === 'verification_code') {
    $search_term = trim($_GET['search_term'] ?? '');
    if (!empty($search_term)) {
        $stmt = Db::get()->prepare("SELECT * FROM verification_data WHERE code LIKE ? OR combination LIKE ? OR category LIKE ?");
        $likeTerm = "%{$search_term}%";
        $stmt->execute([$likeTerm, $likeTerm, $likeTerm]);
        
        $filteredData = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $code = $row['code'];
            $filteredData[$code] = [
                'category' => $row['category'],
                'host' => $row['host'],
                'port' => $row['port'],
                'user' => $row['user'],
                'pass' => $row['pass'],
                'match_keywords' => empty($row['match_keywords']) ? [] : explode(',', $row['match_keywords']),
                'match_sender' => [$row['match_sender']],
                'releasedate' => [$row['releasedate']],
                'expirationtime' => [$row['expirationtime']],
                'combination' => [$row['combination']],
                'is_expired' => (bool)$row['is_expired']
            ];
        }
        $verificationData = $filteredData;
    } else {
        $verificationData = getAllVerificationData();
    }
} elseif ($action === 'verification_code_list' && isset($_GET['category'])) {
    $stmt = Db::get()->prepare("SELECT * FROM verification_data WHERE category = ? AND is_expired = 0");
    $stmt->execute([$_GET['category']]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $code = $row['code'];
        $verificationData[$code] = [
            'category' => $row['category'],
            'host' => $row['host'],
            'port' => $row['port'],
            'user' => $row['user'],
            'pass' => $row['pass'],
            'match_keywords' => empty($row['match_keywords']) ? [] : explode(',', $row['match_keywords']),
            'match_sender' => [$row['match_sender']],
            'releasedate' => [$row['releasedate']],
            'expirationtime' => [$row['expirationtime']],
            'combination' => [$row['combination']]
        ];
    }
} elseif ($action === 'expired_phones') {
    $verificationData = getExpiredPhonesData();
}

if (array_key_exists($action, $allowedActions)) {
    $content = $allowedActions[$action];
} else {
    header('Location: admin.php?action=dashboard');
    exit;
}

// 最后渲染全局视图
require 'views/layout.php';
?>
