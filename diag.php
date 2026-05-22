<?php
/**
 * 诊断脚本 - 上传到服务器根目录，通过浏览器访问
 * 例如：https://dd7dywwlyp.fy.takin.cc/diag.php
 * ⚠️ 查看完毕后请立即删除此文件！
 */

// 报告所有错误
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>系统诊断</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        h1 { color: #333; }
        h2 { color: #555; border-bottom: 1px solid #ddd; padding-bottom: 5px; }
        .ok { color: #28a745; font-weight: bold; }
        .err { color: #dc3545; font-weight: bold; }
        .info { color: #17a2b8; }
        pre { background: #fff; padding: 10px; border: 1px solid #ddd; border-radius: 4px; }
        .box { background: #fff; padding: 15px; border-radius: 6px; margin-bottom: 15px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <h1>🔍 系统诊断报告</h1>
    <p class="info">生成时间：<?php echo date('Y-m-d H:i:s'); ?></p>

    <div class="box">
    <h2>1. PHP 环境</h2>
    <p>PHP 版本：<strong><?php echo phpversion(); ?></strong></p>
    <p>SAPI：<?php echo php_sapi_name(); ?></p>
    <p>操作系统：<?php echo php_uname('s') . ' ' . php_uname('r'); ?></p>
    </div>

    <div class="box">
    <h2>2. 检查重要函数是否可用</h2>
    <p>exec()：<?php echo function_exists('exec') ? '<span class="ok">✅ 可用</span>' : '<span class="err">❌ 不可用</span>'; ?></p>
    <p>shell_exec()：<?php echo function_exists('shell_exec') ? '<span class="ok">✅ 可用</span>' : '<span class="err">❌ 不可用</span>'; ?></p>
    </div>

    <?php
    $filesToCheck = array(
        'config.php',
        'api/notifications.php',
        'api/medical_records.php'
    );
    ?>

    <div class="box">
    <h2>3. 文件语法检查（使用 php -l）</h2>
    <?php
    foreach ($filesToCheck as $file) {
        $fullPath = __DIR__ . '/' . $file;
        echo '<h3>' . htmlspecialchars($file) . '</h3>';
        
        if (!file_exists($fullPath)) {
            echo '<p class="err">❌ 文件不存在：' . htmlspecialchars($fullPath) . '</p>';
            continue;
        }
        
        echo '<p class="info">文件路径：' . htmlspecialchars($fullPath) . '</p>';
        
        // 使用 exec 检查语法
        if (function_exists('exec')) {
            $output = array();
            $ret = 0;
            exec('php -l ' . escapeshellarg($fullPath) . ' 2>&1', $output, $ret);
            if ($ret === 0) {
                echo '<p class="ok">✅ 语法正常</p>';
            } else {
                echo '<p class="err">❌ 语法错误：</p>';
                echo '<pre>' . htmlspecialchars(implode("\n", $output)) . '</pre>';
            }
        } else {
            // exec 不可用，尝试直接 include 检测错误
            echo '<p class="info">⚠️ exec() 不可用，尝试通过 include 检测...</p>';
            ob_start();
            $err = null;
            try {
                include $fullPath;
                $output = ob_get_clean();
                if ($output) {
                    echo '<p class="err">⚠️ 文件有输出：</p>';
                    echo '<pre>' . htmlspecialchars($output) . '</pre>';
                } else {
                    echo '<p class="ok">✅ 文件可被包含（无明显语法错误）</p>';
                }
            } catch (Error $e) {
                ob_end_clean();
                echo '<p class="err">❌ 包含文件时出错：</p>';
                echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
            }
        }
    }
    ?>
    </div>

    <div class="box">
    <h2>4. 直接测试 config.php 包含</h2>
    <?php
    ob_start();
    $errMsg = null;
    try {
        include __DIR__ . '/config.php';
        $output = ob_get_clean();
        if ($output) {
            echo '<p class="err">⚠️ config.php 有输出（可能是错误）：</p>';
            echo '<pre>' . htmlspecialchars($output) . '</pre>';
        } else {
            echo '<p class="ok">✅ config.php 包含成功，无输出</p>';
            
            // 测试数据库
            if (function_exists('getDB')) {
                try {
                    $pdo = getDB();
                    echo '<p class="ok">✅ 数据库连接成功</p>';
                } catch (Exception $e) {
                    echo '<p class="err">❌ 数据库连接失败：' . htmlspecialchars($e->getMessage()) . '</p>';
                }
            }
        }
    } catch (Error $e) {
        $output = ob_get_clean();
        echo '<p class="err">❌ 包含 config.php 时出错：</p>';
        echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
        if ($output) {
            echo '<p>同时有以下输出：</p>';
            echo '<pre>' . htmlspecialchars($output) . '</pre>';
        }
    } catch (Exception $e) {
        $output = ob_get_clean();
        echo '<p class="err">❌ 包含 config.php 时出错：</p>';
        echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
    }
    ?>
    </div>

    <div class="box">
    <h2>5. 服务器 IP 和端口检测</h2>
    <p>服务器 IP：<?php echo isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : '未知'; ?></p>
    <p>服务器端口：<?php echo isset($_SERVER['SERVER_PORT']) ? $_SERVER['SERVER_PORT'] : '未知'; ?></p>
    <p>请求来源 IP：<?php echo isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '未知'; ?></p>
    </div>

    <hr>
    <p><strong>⚠️ 诊断完成后，请立即删除此文件（diag.php）！</strong></p>
    <p>以上信息有助于定位问题。请把浏览器中显示的内容截图或复制发给我。</p>
</body>
</html>
