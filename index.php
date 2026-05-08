<?php
require_once __DIR__ . '/classes/Db.php';

// 模式一：HTML带字体样式输出 (设置为 true)
// 模式二：纯文本输出 (设置为 false)
$enableHtmlOutput = true;
//$enableHtmlOutput = false;

// 当使用HTML模式时，可以在这里修改字体大小
$fontSize = "15px";
// 新增：手机端 (屏幕宽度 <= 670px) 的字体大小
$mobileFontSize = "17px";

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

// --------- 核心截取规则函数 (原封不动保留) ---------
function apply_custom_extraction_rules($content) {
    $start_markers = ['fd.com> ', 'View in browser'];
    $end_markers = ['To  message, launch','To t message','YOUR ACCOUNT <https','如要回复此消息',
                    '要回复此短信，','请回复此电子邮件或访问','您的账号'];
    $found_start_marker = null; $found_start_pos = -1; $found_end_marker = null; $found_end_pos = -1;
    foreach ($start_markers as $marker) { $pos = strpos($content, $marker); if ($pos !== false) { $found_start_marker = $marker; $found_start_pos = $pos; break; } }
    foreach ($end_markers as $marker) { $pos = strpos($content, $marker); if ($pos !== false) { $found_end_marker = $marker; $found_end_pos = $pos; break; } }
    if ($found_start_marker !== null && $found_end_marker !== null) { if ($found_end_pos > $found_start_pos) { $slice_start = $found_start_pos + strlen($found_start_marker); $slice_length = $found_end_pos - $slice_start; return substr($content, $slice_start, $slice_length); } }
    if ($found_start_marker !== null && $found_end_marker === null) { $slice_start = $found_start_pos + strlen($found_start_marker); return substr($content, $slice_start); }
    if ($found_start_marker === null && $found_end_marker !== null) { return substr($content, 0, $found_end_pos); }
    return $content;
}

// --------- IMAP 结构解析函数 (原封不动保留) ---------
function find_best_part_structure($structure, $mime_type, &$found_html = null) {
    if (isset($structure->parts)) {
        foreach ($structure->parts as $index => $part) {
            $part->part_number = isset($structure->part_number) ? $structure->part_number . '.' . ($index + 1) : ($index + 1);
            if ($part->type == 0 && strtoupper($part->subtype) == 'PLAIN' && $mime_type == 'text/plain') return $part;
            if (isset($part->parts)) { $found = find_best_part_structure($part, $mime_type, $found_html); if ($found) return $found; }
            if ($part->type == 0 && strtoupper($part->subtype) == 'HTML' && $found_html === null) $found_html = $part;
        }
    }
    if ($mime_type == 'text/html' && $found_html !== null) return $found_html;
    if ($structure->type == 0 && strtoupper($structure->subtype) == 'PLAIN' && $mime_type == 'text/plain') { if (!isset($structure->part_number)) $structure->part_number = 1; return $structure; }
    if ($structure->type == 0 && strtoupper($structure->subtype) == 'HTML' && $mime_type == 'text/html') { if (!isset($structure->part_number)) $structure->part_number = 1; return $structure; }
    return null;
}

try {
    // 1. 获取用户通过 GET 传递的查询码
    $code = ltrim($_GET['code'] ?? '', '/');
    if (empty($code)) throw new Exception('链接错误！！！');

    // 2. 从 MySQL 数据库中查找对应的接码数据 (替代了原版 require('config.php'))
    $pdo = Db::get();
    $stmt = $pdo->prepare("SELECT v.*, p.host, p.port, p.user, p.pass, p.match_sender, c.match_keywords 
                           FROM verification_data v 
                           LEFT JOIN phonenumbers p ON v.phonenumber = p.phonenumber 
                           LEFT JOIN classifications c ON v.category = c.id 
                           WHERE v.code = ?");
    $stmt->execute([$code]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) throw new Exception('查询码错误！！！');

    // 将数据库中存储的逗号分割字符串转回原版代码需要的数组格式
    $row['match_sender'] = empty($row['match_sender']) ? [] : [$row['match_sender']];
    $row['match_keywords'] = empty($row['match_keywords']) ? [] : explode(',', $row['match_keywords']);

    // Data Validation Logic
    if (empty($row['host']) || empty($row['port']) || empty($row['user']) || empty($row['pass'])) {
        throw new Exception("调试数据错误！！！");
    }

    // 3. 时间过期校验逻辑
    if (!empty($row['releasedate']) && !empty($row['expirationtime'])) {
        $release_date_str = str_replace('上架日期：', '', $row['releasedate']);
        
        // 使用正则表达式匹配天数
        preg_match('/([\d.]+)/', $row['expirationtime'], $matches);
        $days_to_add = isset($matches[1]) ? floatval($matches[1]) : 0;

        if ($days_to_add > 0) {
            $release_date = new DateTime($release_date_str);
            $seconds_to_add = (int)($days_to_add * 86400); // 1 day = 86400 seconds
            $release_date->modify("+$seconds_to_add seconds");
            
            // 校验是否超过过期时间或者数据库中已被标记为过期
            if (time() > $release_date->getTimestamp() || $row['is_expired'] == 1) {
                // 如果时间已过但数据库未标记，自动更新为已过期
                if ($row['is_expired'] == 0) {
                    $pdo->prepare("UPDATE verification_data SET is_expired = 1 WHERE code = ?")->execute([$code]);
                }
                throw new Exception('已过期，请联系商家！！！');
            }
        }
    }

    // 4. 根据端口号动态判断使用IMAP还是POP3协议
    if ($row['port'] == 993) {
        $protocol = "/imap/ssl/novalidate-cert";
    } elseif ($row['port'] == 995) {
        $protocol = "/pop3/ssl/novalidate-cert";
    } else {
        $protocol = "/pop3/notls";
    }
  
    $mailbox_string = "{{$row['host']}:{$row['port']}{$protocol}}INBOX";

    $inbox = imap_open($mailbox_string, $row['user'], $row['pass']);
    if ($inbox === false) {
        throw new Exception("调试数据错误！！！");
    }

    $emails = imap_search($inbox, 'ALL');
    $final_result = null;

    if ($emails) {
        rsort($emails);
        $emails_to_check = array_slice($emails, 0, 3);

        foreach ($emails_to_check as $msg_number) {
            $header = imap_headerinfo($inbox, $msg_number);

            if (!empty($row['match_sender'])) {
                $sender_match = false;
                $addresses_to_check = $header->fromaddress ?? '';
                if (isset($header->to) && is_array($header->to)) {
                    foreach ($header->to as $recipient) {
                        $addresses_to_check .= ' ' . ($recipient->mailbox ?? '') . '@' . ($recipient->host ?? '');
                    }
                }
                
                $raw_header = imap_fetchheader($inbox, $msg_number);
                if ($raw_header && preg_match('/^X-Forwarded-For:\s*(.*)$/im', $raw_header, $matches)) {
                    $addresses_to_check .= ' ' . trim($matches[1]);
                }

                foreach ($row['match_sender'] as $sender_keyword) {
                    if (!empty($sender_keyword) && stripos($addresses_to_check, $sender_keyword) !== false) {
                        $sender_match = true;
                        break;
                    }
                }
                if (!$sender_match) continue;
            }

            $date_string = $header->date;
            $formatted_date = '';
            try {
                $datetime = new DateTime($date_string);
                $datetime->setTimezone(new DateTimeZone('Asia/Shanghai'));
                $formatted_date = $datetime->format('Y-m-d H:i:s');
            } catch (Exception $e) {
                $now = new DateTime();
                $now->setTimezone(new DateTimeZone('Asia/Shanghai'));
                $formatted_date = $now->format('Y-m-d H:i:s');
            }
            
            $structure = imap_fetchstructure($inbox, $msg_number);
            $body = ''; $is_html = false;
            $part_structure = find_best_part_structure($structure, 'text/plain');
            if (!$part_structure) { $part_structure = find_best_part_structure($structure, 'text/html'); if ($part_structure) $is_html = true; }

            if ($part_structure) {
                $part_number = $part_structure->part_number;
                $body = imap_fetchbody($inbox, $msg_number, $part_number);
                if ($part_structure->encoding == 3) { $body = base64_decode($body); } elseif ($part_structure->encoding == 4) { $body = quoted_printable_decode($body); }
                $charset = 'UTF-8';
                if (!empty($part_structure->parameters)) { foreach ($part_structure->parameters as $param) { if (strtoupper($param->attribute) == 'CHARSET') { $charset = $param->value; break; } } }
                $body = iconv(strtoupper($charset), 'UTF-8//IGNORE', $body);
                if ($is_html && is_string($body)) {
                    $body = preg_replace(['/<style.*?<\/style>/is', '/<script.*?<\/script>/is'], '', $body);
                    $body = str_ireplace(['<br>','<br/>','</p>','</div>','</tr>','</li>'], "\n", $body);
                    $body = strip_tags($body);
                    $body = html_entity_decode($body, ENT_QUOTES | ENT_HTML5);
                }
            }
            
            $content = preg_replace('/\s+/', ' ', $body ?? '');
            $content = apply_custom_extraction_rules($content);
            $final_string = $formatted_date . ' | ' . trim($content);

            if (!empty($row['match_keywords'])) {
                $keyword_match = false;
                if (empty(trim(explode('|', $final_string, 2)[1] ?? ''))) {
                    $keyword_match = false;
                } else {
                    foreach ($row['match_keywords'] as $keyword) {
                        if (!empty($keyword) && strpos($final_string, $keyword) !== false) {
                            $keyword_match = true;
                            break;
                        }
                    }
                }
                if (!$keyword_match) continue;
            }
            
            $final_result = $final_string;
            break;
        }
    }

    imap_close($inbox);
    
    // --------- 输出样式判定 (原封不动保留) ---------
    if ($enableHtmlOutput) {
        header('Content-Type: text/html; charset=utf-8');
        echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
        echo "<style>
            .content-output {
                font-size: {$fontSize};
                font-weight: 500;
                font-family: 'Microsoft YaHei Bold', 'Microsoft YaHei', sans-serif;
                word-wrap: break-word;
                white-space: pre-wrap;
                margin: 0;
            }
            @media (max-width: 670px) {
                .content-output {
                    font-size: {$mobileFontSize};
                }
            }
        </style>";
        
        if ($final_result !== null) {
            $output = htmlspecialchars($final_result);
            echo "<pre class='content-output'>{$output}</pre>";
        } else {
            echo "<div class='content-output'>☹ 没有新短信！！！</div>";
        }
    } else {
        header('Content-Type: text/plain; charset=utf-8');
        if ($final_result !== null) {
            echo $final_result;
        } else {
            echo "☹ 没有新短信！！！";
        }
    }
    exit;

} catch (Exception $e) {
    if ($enableHtmlOutput) {
        header('Content-Type: text/html; charset=utf-8');
        echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
        echo "<style>
            .content-output {
                font-size: {$fontSize};
                font-weight: 500;
                font-family: 'Microsoft YaHei Bold', 'Microsoft YaHei', sans-serif;
                word-wrap: break-word;
                white-space: pre-wrap;
                margin: 0;
            }
            @media (max-width: 670px) {
                .content-output {
                    font-size: {$mobileFontSize};
                }
            }
        </style>";
        echo "<div class='content-output'>☹ " . htmlspecialchars($e->getMessage()) . "</div>";
    } else {
        header('Content-Type: text/plain; charset=utf-8');
        echo $e->getMessage();
    }
    exit;
}
