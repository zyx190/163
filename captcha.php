<?php
session_start();

// 生成一个随机的5字符字符串
$captcha_text = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 5);

// 将验证码文本存储在会话中
$_SESSION['captcha_text'] = $captcha_text;

// 创建图像
$width = 120;
$height = 40;
$image = imagecreatetruecolor($width, $height);

// 定义颜色
$bg_color = imagecolorallocate($image, 240, 240, 240); // 浅灰色背景
$text_color = imagecolorallocate($image, 50, 50, 50);   // 深灰色文本
$line_color = imagecolorallocate($image, 200, 200, 200); // 用于噪点的浅灰色

// 填充背景
imagefilledrectangle($image, 0, 0, $width, $height, $bg_color);

// 添加一些随机线条作为噪点
for ($i = 0; $i < 5; $i++) {
    imageline($image, 0, rand() % $height, $width, rand() % $height, $line_color);
}

// 添加一些随机像素点作为噪点
for ($i = 0; $i < 1000; $i++) {
    imagesetpixel($image, rand() % $width, rand() % $height, $line_color);
}

// 使用一个通用字体（如果可用）。如果服务器上没有字体文件，PHP会尝试使用内置字体，但效果可能不佳。
// 建议下载一个中文字体或英文字体（如 arial.ttf），并将其放在与 captcha.php 相同的目录中。
$font = __DIR__ . '/arial.ttf'; 

if (file_exists($font)) {
    // 使用TTF字体绘制验证码字符串
    imagettftext($image, 20, rand(-5, 5), 10, 30, $text_color, $font, $captcha_text);
} else {
    // 如果找不到字体文件，则使用内置的简单字体
    imagestring($image, 5, 20, 10,  $captcha_text, $text_color);
}


// 输出图像
header('Content-Type: image/png');
imagepng($image);
imagedestroy($image);
?>