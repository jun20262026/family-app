<?php
/**
 * 家庭管理系统 - 主页面
 * 响应式：手机 H5 + 桌面
 */
require_once __DIR__ . '/config.php';
$pdo  = getDB();
$isLoggedIn   = !empty($_SESSION['user']['id']);
$currentRole  = $_SESSION['user']['role']   ?? '';
$currentName  = $_SESSION['user']['brother_name'] ?? '';
$currentId    = $_SESSION['user']['id']     ?? 0;

// 分享参数：?to=模块名&id=记录ID
$shareTo   = $_GET['to']   ?? '';
$shareId   = $_GET['id']   ?? '';
$shareHash = $_GET['hash'] ?? ''; // 用于直接定位到具体记录

// 如果是兄弟，查自己的 user 记录
$brother = null;
if ($isLoggedIn && $currentRole === 'brother') {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$currentId]);
    $brother = $stmt->fetch();
}

// 是否管理员（用于JS）
$isAdmin = ($currentRole === 'admin') ? 'true' : 'false';

// 未登录但有分享参数时，先存到 sessionStorage（登录后使用）
$shareScript = '';
if (!$isLoggedIn && !empty($shareTo) && !empty($shareId)) {
    $shareScript = '<script>sessionStorage.setItem("share_to","'.addslashes($shareTo).'");sessionStorage.setItem("share_id","'.intval($shareId).'");</script>';
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<title>家庭管理系统</title>
<style>
/* ===== 基础重置 ===== */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { font-size: 16px; }
body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "PingFang SC", "Microsoft YaHei", sans-serif;
    background: linear-gradient(135deg, #e0e7ff 0%, #f0f4ff 50%, #e8f0fe 100%);
    color: #333;
    min-height: 100vh;
}

/* ===== 顶部栏 ===== */
.topbar {
    background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
    color: #fff;
    padding: 12px 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: sticky;
    top: 0;
    z-index: 200;
    box-shadow: 0 4px 12px rgba(79,70,229,0.3);
}
.topbar-left { display: flex; align-items: center; gap: 12px; }
.hamburger {
    display: none;
    background: none;
    border: none;
    color: #fff;
    font-size: 1.5rem;
    cursor: pointer;
    padding: 4px;
    border-radius: 6px;
    transition: background 0.2s;
}
.hamburger:hover { background: rgba(255,255,255,0.2); }
.topbar h1 { font-size: 1.15rem; font-weight: 700; letter-spacing: 1px; }
.topbar .user-info { font-size: 0.85rem; opacity: 0.95; font-weight: 500; }
.topbar .btn-logout {
    background: rgba(255,255,255,0.2);
    border: 1.5px solid rgba(255,255,255,0.4);
    color: #fff;
    padding: 6px 14px;
    border-radius: 20px;
    cursor: pointer;
    font-size: 0.8rem;
    transition: all 0.2s;
}
.topbar .btn-logout:hover { background: rgba(255,255,255,0.35); }

/* ===== 侧边栏抽屉 ===== */
.sidebar-overlay {
    display: none;
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.4);
    z-index: 250;
}
.sidebar-overlay.active { display: block; }

.sidebar {
    position: fixed;
    top: 0; left: 0; bottom: 0;
    width: 240px;
    background: #fff;
    z-index: 300;
    transform: translateX(-100%);
    transition: transform 0.3s ease;
    box-shadow: 4px 0 20px rgba(0,0,0,0.15);
    display: flex;
    flex-direction: column;
    overflow-y: auto;
}
.sidebar.active { transform: translateX(0); }

.sidebar-header {
    background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
    color: #fff;
    padding: 20px 16px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.sidebar-header .close-btn {
    background: none;
    border: none;
    color: #fff;
    font-size: 1.3rem;
    cursor: pointer;
    padding: 4px;
    margin-left: auto;
}
.sidebar-header h2 { font-size: 1.1rem; font-weight: 700; }

.sidebar-nav { flex: 1; padding: 8px 0; }

.sidebar-link {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 20px;
    color: #555;
    text-decoration: none;
    font-size: 0.95rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    border-left: 3px solid transparent;
}
.sidebar-link:hover { background: #f5f3ff; color: #4f46e5; }
.sidebar-link.active {
    background: #f5f3ff;
    color: #4f46e5;
    border-left-color: #4f46e5;
    font-weight: 600;
}
.sidebar-link .nav-icon { font-size: 1.2rem; width: 24px; text-align: center; }

.sidebar-footer {
    padding: 12px 16px;
    border-top: 1px solid #eee;
    font-size: 0.75rem;
    color: #999;
    text-align: center;
}

/* ===== 主内容区 ===== */
.main-content {
    margin-left: 0;
    transition: margin-left 0.3s ease;
}
@media (min-width: 768px) {
    .hamburger { display: block; }
    .main-content { margin-left: 0; }
}
@media (max-width: 767px) {
    .hamburger { display: block; }
}

/* ===== 内容区 ===== */
.container { padding: 14px; max-width: 960px; margin: 0 auto; }

/* ===== 页面切换 ===== */
.page { display: none; }
.page.active { display: block; animation: fadeIn 0.3s ease; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }

/* ===== 卡片 ===== */
.card {
    background: #fff;
    border-radius: 16px;
    padding: 18px;
    margin-bottom: 14px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
    border: 1px solid rgba(0,0,0,0.04);
}
.card h3 { font-size: 1.05rem; margin-bottom: 14px; color: #1f2937; font-weight: 700; }

/* ===== 统计卡片 ===== */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 12px;
    margin-bottom: 14px;
}
.stat-card {
    background: #fff;
    border-radius: 16px;
    padding: 18px 14px;
    text-align: center;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
    border: 1px solid rgba(0,0,0,0.04);
    transition: transform 0.2s;
}
.stat-card:hover { transform: translateY(-2px); }
.stat-card h4 { font-size: 0.8rem; color: #6b7280; margin-bottom: 8px; font-weight: 500; }
.stat-card .num { font-size: 1.6rem; font-weight: 800; background: linear-gradient(135deg, #4f46e5, #7c3aed); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }

/* ===== 表单 ===== */
.form-group { margin-bottom: 14px; }
.form-group label { display: block; font-size: 0.85rem; color: #374151; margin-bottom: 6px; font-weight: 600; }
.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 11px 14px;
    border: 1.5px solid #e5e7eb;
    border-radius: 10px;
    font-size: 0.95rem;
    outline: none;
    transition: border 0.2s, box-shadow 0.2s;
    background: #fafafa;
    font-family: inherit;
}
.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus { border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79,70,229,0.1); background: #fff; }

/* ===== 按钮 ===== */
.btn {
    padding: 10px 22px;
    border: none;
    border-radius: 10px;
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.2s;
    font-weight: 600;
    font-family: inherit;
}
.btn-primary { background: linear-gradient(135deg, #4f46e5, #7c3aed); color: #fff; box-shadow: 0 2px 8px rgba(79,70,229,0.3); }
.btn-primary:hover { box-shadow: 0 4px 16px rgba(79,70,229,0.4); transform: translateY(-1px); }
.btn-success { background: linear-gradient(135deg, #059669, #10b981); color: #fff; box-shadow: 0 2px 8px rgba(5,150,105,0.3); }
.btn-success:hover { box-shadow: 0 4px 16px rgba(5,150,105,0.4); }
.btn-danger  { background: linear-gradient(135deg, #dc2626, #ef4444); color: #fff; }
.btn-danger:hover { box-shadow: 0 4px 16px rgba(239,68,68,0.4); }
.btn-warning { background: linear-gradient(135deg, #d97706, #f59e0b); color: #fff; }
.btn-warning:hover { box-shadow: 0 4px 16px rgba(245,158,11,0.4); }
.btn-sm { padding: 6px 14px; font-size: 0.8rem; border-radius: 8px; }

/* ===== 表格 ===== */
table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
th, td { padding: 11px 10px; text-align: left; border-bottom: 1px solid #f3f4f6; }
th { color: #6b7280; font-weight: 600; font-size: 0.8rem; }
tr:hover { background: #fafafe; }
tr:last-child td { border-bottom: none; }

/* ===== 排行徽章 ===== */
.rank-badge {
    display: inline-flex;
    width: 26px; height: 26px;
    border-radius: 50%;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    font-weight: 700;
    color: #fff;
}
.rank-1 { background: linear-gradient(135deg, #f5222d, #ff4d4f); box-shadow: 0 2px 8px rgba(245,34,45,0.4); }
.rank-2 { background: linear-gradient(135deg, #fa8c16, #ffa940); box-shadow: 0 2px 8px rgba(250,140,22,0.4); }
.rank-3 { background: linear-gradient(135deg, #faad14, #ffc53d); box-shadow: 0 2px 8px rgba(250,173,20,0.4); }
.rank-other { background: #d9d9d9; color: #666; }

/* ===== 钱包操作区 ===== */
.wallet-actions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 14px;
}

/* ===== 提示 ===== */
.alert { padding: 11px 16px; border-radius: 10px; font-size: 0.85rem; margin-bottom: 12px; font-weight: 500; }
.alert-success { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
.alert-danger  { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
.alert-info    { background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; }

/* ===== 登录页 ===== */
.login-wrap {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 50%, #a855f7 100%);
}
.login-card {
    background: #fff;
    border-radius: 20px;
    padding: 36px 32px;
    width: 92%;
    max-width: 400px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
}
.login-card h2 { text-align: center; margin-bottom: 28px; color: #1f2937; font-size: 1.4rem; }
.login-card .btn { width: 100%; padding: 13px; font-size: 1rem; letter-spacing: 2px; }

/* ===== 文件上传美化 ===== */
input[type="file"] {
    width: 100%;
    padding: 10px;
    border: 1.5px dashed #d1d5db;
    border-radius: 10px;
    background: #fafafa;
    cursor: pointer;
    font-size: 0.85rem;
    color: #6b7280;
}
input[type="file"]:hover { border-color: #4f46e5; background: #f5f3ff; }

/* ===== 手机优化 ===== */
@media (max-width: 600px) {
    .container { padding: 10px; }
    .card { padding: 14px; border-radius: 14px; }
    .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 8px; }
    .stat-card { padding: 14px 10px; }
    .stat-card .num { font-size: 1.3rem; }
    table { font-size: 0.76rem; }
    th, td { padding: 9px 5px; }
    .wallet-actions { grid-template-columns: 1fr; }
}
/* 桌面优化 */
@media (min-width: 768px) {
    /* sidebar 默认收起，点击汉堡按钮才展开 */
    .sidebar { transform: translateX(-100%); }
    .sidebar.active { transform: translateX(0); }
    .main-content { margin-left: 0; transition: margin-left 0.3s ease; }
    .main-content.shifted { margin-left: 240px; }
    /* 桌面端也显示汉堡按钮 */
    .hamburger { display: block; }
    .sidebar-overlay { display: none !important; }

/* ===== 消息弹窗（使用 modal-overlay 通用框架，此处仅补充消息项样式） ===== */
.message-item {
    display: flex; gap: 12px; padding: 12px 0; border-bottom: 1px solid #f5f5f5;
    cursor: pointer; border-radius: 8px; transition: background 0.15s;
}
.message-item:last-child { border-bottom: none; }
.message-item:hover { background: #f8f9fe; }
.message-icon { font-size: 1.4rem; flex-shrink: 0; }
.message-content { flex: 1; min-width: 0; }
.message-title { font-weight: 600; font-size: 0.9rem; color: #1a1a2e; }
.message-text { font-size: 0.82rem; color: #666; margin-top: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.message-time { font-size: 0.75rem; color: #aaa; margin-top: 4px; }

/* ===== 通用弹窗（证件/备忘录/通讯录等）===== */
.modal-overlay {
    display: none !important;
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    width: 100vw !important;
    height: 100vh !important;
    background: rgba(0,0,0,0.7) !important;
    z-index: 99999 !important;
    justify-content: center !important;
    align-items: center !important;
    box-sizing: border-box !important;
    padding: 16px;
}
/* 显示弹窗：通过 .overlay-active 类控制 */
.modal-overlay.overlay-active {
    display: flex !important;
    background: rgba(0,0,0,0.7) !important;
}
/* 消息弹窗特殊样式（紫色主题） */
#message-modal-overlay.overlay-active {
    display: flex !important;
    background: rgba(0,0,0,0.7) !important;
    justify-content: center !important;
    align-items: center !important;
}

.modal, .message-modal {
    background: #fff;
    border-radius: 16px;
    width: 92%;
    max-width: 520px;
    max-height: 85vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    animation: slideUp 0.3s ease;
}

/* 手机端弹窗更宽 */
@media (max-width: 600px) {
    .modal-overlay {
        padding: 10px !important;
    }
    .modal, .message-modal {
        width: 96% !important;
        max-width: 100% !important;
        margin: 10px !important;
        border-radius: 14px !important;
    }
}
</style>
<?php echo $shareScript; ?>

<!-- ===== APP 模式优化 CSS ===== -->
<style>
/* APP 模式：禁用橡皮筋、优化滚动 */
body.app-mode {
    overscroll-behavior-y: none;
    -webkit-overflow-scrolling: touch;
}

/* 顶部栏适配刘海屏状态栏 */
body.app-mode .topbar {
    padding-top: max(12px, env(safe-area-inset-top));
    min-height: calc(44px + env(safe-area-inset-top));
}

/* 侧边栏顶部也适配 */
body.app-mode .sidebar-header {
    padding-top: max(20px, calc(12px + env(safe-area-inset-top)));
}

/* 底部安全区 */
body.app-mode .container {
    padding-bottom: env(safe-area-inset-bottom);
}
body.app-mode .sidebar-footer {
    padding-bottom: max(12px, env(safe-area-inset-bottom));
}

/* 弹窗底部安全区 */
body.app-mode .modal,
body.app-mode .message-modal {
    padding-bottom: env(safe-area-inset-bottom);
}

/* 触摸优化：移除300ms点击延迟 */
body.app-mode a,
body.app-mode button,
body.app-mode .sidebar-link,
body.app-mode input,
body.app-mode select,
body.app-mode textarea {
    touch-action: manipulation;
    -webkit-tap-highlight-color: transparent;
}

/* 快速点击反馈 */
body.app-mode .sidebar-link:active,
body.app-mode .btn:active,
body.app-mode .stat-card:active,
body.app-mode .sidebar-link.active {
    opacity: 0.85;
    transform: scale(0.98);
    transition: none;
}

/* APP 模式下固定底部导航（如需要可启用） */
body.app-mode .bottom-nav {
    padding-bottom: env(safe-area-inset-bottom);
}

/* 输入框聚焦时避免被键盘遮挡的额外边距 */
body.app-mode .form-group {
    margin-bottom: 16px;
}

/* 列表项更大的点击区域 */
body.app-mode .message-item {
    padding: 14px 0;
}

/* 移动端表格横向滚动优化 */
body.app-mode table {
    display: block;
    overflow-x: auto;
    white-space: nowrap;
    -webkit-overflow-scrolling: touch;
}
</style>

<!-- ===== APP 环境初始化 ===== -->
<script>
(function() {
    // 检测是否在 Capacitor / APP 环境
    var isCapacitor = typeof window !== 'undefined' && (
        window.Capacitor !== undefined ||
        (window.location && window.location.protocol === 'capacitor:')
    );
    var isMobile = /iPad|iPhone|iPod|Android/.test(navigator.userAgent);

    if (isCapacitor || isMobile) {
        document.documentElement.classList.add('app-mode');
        document.body.classList.add('app-mode');

        // Capacitor 插件初始化
        if (window.Capacitor && window.Capacitor.Plugins) {
            var Plugins = window.Capacitor.Plugins;

            // 状态栏：沉浸式 + 白色文字
            if (Plugins.StatusBar) {
                try {
                    Plugins.StatusBar.setOverlaysWebView({ overlay: false });
                    Plugins.StatusBar.setBackgroundColor({ color: '#4f46e5' });
                    Plugins.StatusBar.setStyle({ style: 'LIGHT' });
                } catch (e) { console.log('StatusBar init error', e); }
            }

            // Android 返回键：优先回退，不能再回退时退出APP
            if (Plugins.App) {
                try {
                    Plugins.App.addListener('backButton', function(ev) {
                        // 如果弹窗打开，先关闭弹窗
                        var overlays = document.querySelectorAll('.modal-overlay.overlay-active, #message-modal-overlay.overlay-active, .sidebar.active');
                        if (overlays.length > 0) {
                            overlays.forEach(function(el) {
                                if (el.id === 'sidebar-overlay') return;
                                if (el.id === 'message-modal-overlay') { ignoreMessageModal(); return; }
                                if (el.classList.contains('overlay-active')) { el.classList.remove('overlay-active'); }
                                if (el.classList.contains('active')) { el.classList.remove('active'); }
                            });
                            if (document.getElementById('sidebar')) {
                                document.getElementById('sidebar').classList.remove('active');
                                document.getElementById('sidebar-overlay').classList.remove('active');
                                document.querySelector('.main-content').classList.remove('shifted');
                            }
                            return;
                        }
                        // 如果有页面历史，回退
                        if (window.history.length > 1) {
                            window.history.back();
                        } else {
                            Plugins.App.exitApp();
                        }
                    });
                } catch (e) { console.log('BackButton init error', e); }
            }

            // 键盘优化：自动调整
            if (Plugins.Keyboard) {
                try {
                    Plugins.Keyboard.setResizeMode({ mode: 'native' });
                } catch (e) { console.log('Keyboard init error', e); }
            }

            // ===== APP 版本检测：发现服务器更新时提示刷新 =====
            (function() {
                var CHECK_INTERVAL = 30000; // 30秒检查一次
                var VERSION_URL = 'version.json?_=' + Date.now();
                var currentVersion = null;
                var checkTimer = null;
                var notified = false;

                function fetchVersion() {
                    fetch(VERSION_URL)
                        .then(function(r) { return r.json(); })
                        .then(function(data) {
                            if (!currentVersion) {
                                currentVersion = data.version;
                                console.log('[VersionCheck] current=' + currentVersion);
                                return;
                            }
                            if (data.version !== currentVersion && !notified) {
                                notified = true;
                                console.log('[VersionCheck] new version=' + data.version);
                                if (confirm('发现新版本（' + data.version + '），是否立即刷新？')) {
                                    location.reload(true);
                                }
                            }
                        })
                        .catch(function(e) { console.log('[VersionCheck] error', e); });
                }

                // 首次检测
                fetchVersion();
                // 定时轮询
                checkTimer = setInterval(fetchVersion, CHECK_INTERVAL);
                // 页面可见性变化时立即检测
                document.addEventListener('visibilitychange', function() {
                    if (!document.hidden) fetchVersion();
                });
            })();
        }

        // 微信/浏览器环境：尝试隐藏地址栏（PWA效果）
        window.addEventListener('load', function() {
            setTimeout(function() { window.scrollTo(0, 1); }, 100);
        });
    }
})();
</script>

</head>
<body>

<!-- ===== 未登录：登录页 ===== -->
<?php if (! $isLoggedIn): ?>
<div class="login-wrap">
  <div class="login-card">
    <h2>🔐 家庭管理系统</h2>
    <form id="form-login">
      <div class="form-group">
        <label>用户名</label>
        <input type="text" id="login-user" required autofocus placeholder="请输入用户名">
      </div>
      <div class="form-group">
        <label>密码</label>
        <input type="password" id="login-pass" required placeholder="请输入密码">
      </div>
      <button type="submit" class="btn btn-primary">登 录</button>
    </form>
    <div id="login-msg" style="margin-top:14px"></div>
  </div>
</div>
<script>
document.getElementById('form-login').addEventListener('submit', function(e) {
    e.preventDefault();
    const msg = document.getElementById('login-msg');
    msg.innerHTML = '';
    fetch('api/login.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            username: document.getElementById('login-user').value.trim(),
            password: document.getElementById('login-pass').value
        })
    }).then(r=>r.json()).then(d=>{
        if (d.success) {
            msg.innerHTML = '<div class="alert alert-success">✅ 登录成功，正在跳转…</div>';
            // 无论是否有分享参数，都刷新页面（PHP 会输出正确的主界面）
            setTimeout(()=>location.reload(), 600);
        } else {
            msg.innerHTML = '<div class="alert alert-danger">❌ '+(d.msg||'登录失败')+'</div>';
        }
    }).catch(()=>{
        msg.innerHTML = '<div class="alert alert-danger">❌ 网络错误</div>';
    });
});
</script>
<?php exit; endif; ?>

<!-- ===== 已登录：主界面 ===== -->
<!-- 顶部栏 -->
<div class="topbar">
  <div class="topbar-left">
    <button class="hamburger" onclick="toggleSidebar()">☰</button>
    <h1>🏠 家庭管理系统</h1>
  </div>
  <div style="display:flex;align-items:center;gap:14px;">
    <span class="user-info"><?php echo htmlspecialchars($currentName); ?>
      <?php if ($currentRole==='admin'): ?>(管理员)<?php endif; ?>
    </span>
    <button class="btn-logout" onclick="doLogout()">退出</button>
  </div>
</div>

<!-- 侧边栏遮罩 -->
<div class="sidebar-overlay" id="sidebar-overlay" onclick="closeSidebar()"></div>

<!-- 侧边栏 -->
<div class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <span>💰 善款登记</span>
    <button class="close-btn" onclick="closeSidebar()">✕</button>
  </div>
  <nav class="sidebar-nav">
    <a class="sidebar-link active" data-page="dashboard">
      <span class="nav-icon">🏠</span> 首页
    </a>
    <a class="sidebar-link" data-page="donate">
      <span class="nav-icon">➕</span> 登记捐款
    </a>
    <a class="sidebar-link" data-page="records">
      <span class="nav-icon">📋</span> 捐款记录
    </a>
    <a class="sidebar-link" data-page="rank">
      <span class="nav-icon">🏆</span> 捐款排行
    </a>
    <a class="sidebar-link" data-page="wallet">
      <span class="nav-icon">💰</span> 我的钱包
    </a>
    <a class="sidebar-link" data-page="expense-records">
      <span class="nav-icon">💸</span> 款项用途
    </a>
    <!-- 家庭管理 -->
    <div style="padding: 12px 16px 4px; font-size: 0.75rem; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.05em;">家庭管理</div>
    <a class="sidebar-link" data-page="documents">
      <span class="nav-icon">📁</span> 证件资料
    </a>
    <a class="sidebar-link" data-page="memos">
      <span class="nav-icon">📝</span> 备忘录
    </a>
    <a class="sidebar-link" data-page="contacts">
      <span class="nav-icon">📞</span> 通讯录
    </a>
    <a class="sidebar-link" data-page="announcements">
      <span class="nav-icon">📢</span> 家庭公告
    </a>
    <a class="sidebar-link" data-page="plans">
      <span class="nav-icon">📅</span> 家庭计划
    </a>
    <a class="sidebar-link" data-page="family-expenses">
      <span class="nav-icon">💸</span> 家庭支出
    </a>
    <?php if ($currentRole === 'admin'): ?>
    <a class="sidebar-link" data-page="stats">
      <span class="nav-icon">📊</span> 统计汇总
    </a>
    <a class="sidebar-link" data-page="users">
      <span class="nav-icon">👥</span> 用户管理
    </a>
    <a class="sidebar-link" data-page="audit">
      <span class="nav-icon">✅</span> 审核管理
    </a>
    <a class="sidebar-link" data-page="logs">
      <span class="nav-icon">📋</span> 操作日志
    </a>
    <?php endif; ?>
    <!-- 家庭管理分类 -->
    <div style="padding: 12px 16px 4px; font-size: 0.75rem; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.05em;">家庭管理</div>
    <a class="sidebar-link" data-page="medical-records">
      <span class="nav-icon">🏥</span> 医院就诊记录
    </a>
    <?php if ($currentRole === 'admin'): ?>
    <a class="sidebar-link" data-page="data-mgmt" style="color:#ef4444;">
      <span class="nav-icon">⚙️</span> 数据管理
    </a>
    <?php endif; ?>
  </nav>
  <div class="sidebar-footer">
    <?php echo htmlspecialchars($currentName); ?>
    <?php if ($currentRole==='admin'): ?>(管理员)<?php endif; ?>
    <br>
    <a href="#" onclick="doLogout();return false;" style="color:#4f46e5;font-size:0.85rem;">退出登录</a>
  </div>
</div>

<!-- 内容区 -->
<div class="container">

  <!-- 首页 -->
  <div class="page active" id="page-dashboard">
    <div class="stats-grid">
      <div class="stat-card">
        <h4>总捐款额</h4>
        <div class="num" id="stat-total">-</div>
      </div>
      <div class="stat-card">
        <h4>总笔数</h4>
        <div class="num" id="stat-count">-</div>
      </div>
      <div class="stat-card">
        <h4>个人收款额</h4>
        <div class="num" id="stat-personal-amount">-</div>
      </div>
      <div class="stat-card">
        <h4>个人笔数</h4>
        <div class="num" id="stat-personal-count">-</div>
      </div>
      <div class="stat-card">
        <h4>我的钱包余额</h4>
        <div class="num" id="stat-my-wallet">-</div>
      </div>
      <?php if ($currentRole === 'admin'): ?>
      <div class="stat-card">
        <h4>钱包总额</h4>
        <div class="num" id="stat-wallet-total">-</div>
      </div>
      <?php endif; ?>
    </div>

    <!-- 各钱包余额详情 -->
    <div class="card" style="margin-top:16px;">
      <h3>💰 各钱包余额</h3>
      <table class="data-table">
        <thead>
          <tr><th>姓名</th><th>余额</th><?php if ($currentRole === 'admin'): ?><th>操作</th><?php endif; ?></tr>
        </thead>
        <tbody id="balances-table-body">
          <tr><td colspan="<?php echo $currentRole === 'admin' ? '3' : '2'; ?>" style="text-align:center;color:#999;">加载中...</td></tr>
        </tbody>
      </table>
    </div>

    <!-- 昨日/今日/上月/本月统计 -->
    <div style="margin-top:22px;">
      <h3 style="margin-bottom:14px;font-size:1.05rem;">📊 昨日 / 今日 / 上月 / 本月统计</h3>
      <div style="margin-bottom:10px;font-weight:600;color:#374151;font-size:0.92rem;">💰 捐款统计</div>
      <div class="stats-grid" id="dstats-donations">
        <div class="stat-card" style="border-left:4px solid #22c55e;">
          <h4>今日捐款</h4>
          <div class="num" id="ds-today" style="font-size:1.1rem;">- 笔 / ¥-</div>
        </div>
        <div class="stat-card" style="border-left:4px solid #eab308;">
          <h4>昨日捐款</h4>
          <div class="num" id="ds-yesterday" style="font-size:1.1rem;">- 笔 / ¥-</div>
        </div>
        <div class="stat-card" style="border-left:4px solid #3b82f6;">
          <h4>本月捐款</h4>
          <div class="num" id="ds-this-month" style="font-size:1.1rem;">- 笔 / ¥-</div>
        </div>
        <div class="stat-card" style="border-left:4px solid #8b5cf6;">
          <h4>上月捐款</h4>
          <div class="num" id="ds-last-month" style="font-size:1.1rem;">- 笔 / ¥-</div>
        </div>
      </div>
      <div style="margin:16px 0 10px;font-weight:600;color:#374151;font-size:0.92rem;">💸 支出统计</div>
      <div class="stats-grid" id="dstats-expenses">
        <div class="stat-card" style="border-left:4px solid #f97316;">
          <h4>今日支出</h4>
          <div class="num" id="es-today" style="font-size:1.1rem;">- 笔 / ¥-</div>
        </div>
        <div class="stat-card" style="border-left:4px solid #ef4444;">
          <h4>昨日支出</h4>
          <div class="num" id="es-yesterday" style="font-size:1.1rem;">- 笔 / ¥-</div>
        </div>
        <div class="stat-card" style="border-left:4px solid #06b6d4;">
          <h4>本月支出</h4>
          <div class="num" id="es-this-month" style="font-size:1.1rem;">- 笔 / ¥-</div>
        </div>
        <div class="stat-card" style="border-left:4px solid #ec4899;">
          <h4>上月支出</h4>
          <div class="num" id="es-last-month" style="font-size:1.1rem;">- 笔 / ¥-</div>
        </div>
      </div>
    </div>
  </div>

  <!-- 登记捐款 -->
  <div class="page" id="page-donate">
    <div class="card">
      <h3>➕ 登记捐款（<?php echo htmlspecialchars($currentName); ?>）</h3>
      <form id="form-donate">
        <div class="form-group">
          <label>捐款人姓名</label>
          <input type="text" id="donor-name" required placeholder="请输入捐款人姓名">
        </div>
        <div class="form-group">
          <label>金额（元）</label>
          <input type="number" id="donor-amount" step="0.01" min="0.01" required placeholder="0.00">
        </div>
        <div class="form-group">
          <label>捐款日期</label>
          <input type="date" id="donor-date" required value="<?php echo date('Y-m-d'); ?>">
        </div>
        <div class="form-group">
          <label>来源</label>
          <select id="donor-source">
            <?php foreach (GET_DONATION_SOURCES() as $src): ?>
            <option value="<?php echo $src; ?>"><?php echo $src; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>支付方式</label>
          <select id="donor-pay">
            <option value="微信">微信</option>
            <option value="支付宝">支付宝</option>
            <option value="银行转账">银行转账</option>
            <option value="现金">现金</option>
          </select>
        </div>
        <div class="form-group">
          <label>备注</label>
          <textarea id="donor-note" rows="2" placeholder="可选"></textarea>
        </div>
        <button type="submit" class="btn btn-primary">确认登记</button>
      </form>
      <div id="donate-msg" style="margin-top:12px;"></div>
    </div>
  </div>

  <!-- 捐款记录 -->
  <div class="page" id="page-records">
    <div class="card">
      <h3>📋 捐款记录（最新在前）</h3>
      <?php if ($currentRole === 'admin'): ?>
      <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;margin-bottom:14px;padding:10px 14px;background:#f8fafc;border-radius:10px;">
        <label style="font-weight:600;color:#374151;margin-bottom:0;">批量导入CSV：</label>
        <input type="file" id="import-file" accept=".csv" onchange="doImport()" style="max-width:220px;">
        <button class="btn btn-primary btn-sm" onclick="exportCSV()">📤 导出 CSV</button>
      </div>
      <?php endif; ?>

      <!-- 搜索 / 筛选栏 -->
      <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;margin-bottom:14px;padding:12px 14px;background:#f8fafc;border-radius:12px;">
        <div style="flex:1;min-width:150px;">
          <div style="font-size:0.78rem;color:#6b7280;margin-bottom:4px;">🔍 搜索捐款人/备注</div>
          <input type="text" id="dr-keyword" placeholder="输入姓名或备注关键字"
            style="width:100%;padding:7px 10px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:0.88rem;"
            onkeydown="if(event.key==='Enter')loadDonations(1)">
        </div>
        <div style="min-width:120px;">
          <div style="font-size:0.78rem;color:#6b7280;margin-bottom:4px;">🗂 来源分类</div>
          <select id="dr-source" style="width:100%;padding:7px 10px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:0.88rem;background:#fff;">
            <option value="">全部来源</option>
            <option value="父亲好友">父亲好友</option>
            <option value="大哥好友">大哥好友</option>
            <option value="二哥好友">二哥好友</option>
            <option value="三弟好友">三弟好友</option>
            <option value="本人好友">本人好友</option>
            <option value="亲戚">亲戚</option>
            <option value="同事">同事</option>
            <option value="同学">同学</option>
            <option value="其他">其他</option>
          </select>
        </div>
        <div style="min-width:120px;">
          <div style="font-size:0.78rem;color:#6b7280;margin-bottom:4px;">📅 开始日期</div>
          <input type="date" id="dr-date-from" style="width:100%;padding:7px 10px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:0.88rem;">
        </div>
        <div style="min-width:120px;">
          <div style="font-size:0.78rem;color:#6b7280;margin-bottom:4px;">📅 结束日期</div>
          <input type="date" id="dr-date-to" style="width:100%;padding:7px 10px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:0.88rem;">
        </div>
        <div style="display:flex;gap:6px;align-items:flex-end;">
          <button class="btn btn-primary" onclick="loadDonations(1)" style="padding:7px 16px;font-size:0.88rem;">🔍 搜索</button>
          <button class="btn" onclick="drClear()" style="padding:7px 12px;font-size:0.88rem;background:#e5e7eb;color:#374151;">重置</button>
        </div>
      </div>

      <!-- 汇总条 -->
      <div id="dr-summary" style="display:none;padding:10px 16px;background:linear-gradient(90deg,#eff6ff,#f0fdf4);border:1.5px solid #bfdbfe;border-radius:12px;margin-bottom:12px;font-size:0.9rem;color:#1d4ed8;font-weight:600;"></div>

      <div style="overflow-x:auto">
        <table>
          <thead>
            <tr>
              <th>时间</th>
              <th>捐款人</th>
              <th>金额</th>
              <th>来源</th>
              <th>支付</th>
              <th>备注</th>
              <th>登记人</th>
              <th>操作</th>
            </tr>
          </thead>
          <tbody id="donation-list"></tbody>
        </table>
      </div>

      <div id="import-msg" style="margin-top:10px;"></div>
    </div>
  </div>

  <!-- 捐款排行 -->
  <div class="page" id="page-rank">
    <div class="card">
      <h3>🏆 捐款排行（金额从高到低）</h3>
      <table>
        <thead><tr><th>排名</th><th>捐款人</th><th>总金额</th><th>次数</th></tr></thead>
        <tbody id="rank-list"></tbody>
      </table>
    </div>
  </div>

  <!-- 我的钱包 -->
  <div class="page" id="page-wallet">
    <div class="card" id="wallet-total-card">
      <h3>💰 所有钱包总余额</h3>
      <div style="font-size:1.6rem;font-weight:800;background:linear-gradient(135deg, #4f46e5, #7c3aed);-webkit-background-clip:text;-webkit-text-fill-color:transparent;">¥<span id="wallet-total-balance">0.00</span></div>
    </div>
    <!-- 个人钱包余额 -->
    <div class="card">
      <h3>👤 我的余额（<?php echo htmlspecialchars($currentName); ?>）</h3>
      <table class="data-table">
        <tr><th>姓名</th><th style="text-align:right;">余额</th></tr>
        <tr>
          <td style="font-weight:600;" id="wallet-my-name"><?php echo htmlspecialchars($currentName); ?></td>
          <td id="wallet-my-balance" style="color:#dc2626;font-weight:700;text-align:right;">-</td>
        </tr>
      </table>
    </div>
    <div class="wallet-actions">
      <!-- 消费 -->
      <div class="card">
        <h3>🛒 消费支出</h3>
        <div class="form-group">
          <label>金额（元）</label>
          <input type="number" id="w-expense-amt" step="0.01" min="0.01" placeholder="0.00">
        </div>
        <div class="form-group">
          <label>用途说明</label>
          <input type="text" id="w-expense-desc" placeholder="如：购买药品">
        </div>
        <div class="form-group">
          <label>款项分类</label>
          <select id="w-expense-cat" onchange="this.value=='__custom__'?document.getElementById('w-expense-cat-custom').style.display='block':document.getElementById('w-expense-cat-custom').style.display='none';" style="padding:8px 12px;border:1.5px solid #e5e7eb;border-radius:10px;width:100%;font-size:0.95rem;background:#fafafa;">
            <option value="">未分类</option>
            <option value="餐饮">餐饮</option>
            <option value="交通">交通</option>
            <option value="购物">购物</option>
            <option value="娱乐">娱乐</option>
            <option value="医疗">医疗</option>
            <option value="教育">教育</option>
            <option value="日用品">日用品</option>
            <option value="__custom__">+ 自定义...</option>
          </select>
          <input type="text" id="w-expense-cat-custom" placeholder="请输入自定义分类" style="display:none;margin-top:8px;padding:8px 12px;border:1.5px solid #e5e7eb;border-radius:10px;width:100%;font-size:0.95rem;">
        </div>
        <div class="form-group">
          <label>消费凭证拍照（可选）</label>
          <input type="file" id="w-expense-photo" accept="image/*">
        </div>
        <button class="btn btn-warning" onclick="walletExpense()">确认支出</button>
      </div>
      <!-- 转账 -->
      <div class="card">
        <h3>🔄 转账给兄弟</h3>
        <div class="form-group">
          <label>转给</label>
          <select id="w-transfer-to"></select>
        </div>
        <div class="form-group">
          <label>金额（元）</label>
          <input type="number" id="w-transfer-amt" step="0.01" min="0.01" placeholder="0.00">
        </div>
        <div class="form-group">
          <label>说明</label>
          <input type="text" id="w-transfer-desc" placeholder="如：资助医药费">
        </div>
        <div class="form-group">
          <label>转账凭证拍照（可选）</label>
          <input type="file" id="w-transfer-photo" accept="image/*">
        </div>
        <button class="btn btn-primary" onclick="walletTransfer()">确认转账</button>
      </div>
    </div>
    <!-- 交易记录 -->
    <div class="card" style="margin-top:14px;">
      <h3>📑 我的交易记录</h3>
      <div style="overflow-x:auto">
        <table>
          <thead><tr><th>时间</th><th>类型</th><th>金额</th><th>余额</th><th>说明</th><th>凭证</th></tr></thead>
          <tbody id="wallet-tx-list"></tbody>
        </table>
      </div>
    </div>
  </div>

  <?php if ($currentRole === 'admin'): ?>
  <!-- 统计汇总 -->
  <div class="page" id="page-stats">
    <div class="card">
      <h3>📊 统计汇总</h3>
      <form id="form-stats" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;margin-bottom:14px;">
        <div class="form-group" style="margin-bottom:0;">
          <label>开始日期</label>
          <input type="date" id="stat-start" value="<?php echo date('Y-m-01'); ?>">
        </div>
        <div class="form-group" style="margin-bottom:0;">
          <label>结束日期</label>
          <input type="date" id="stat-end" value="<?php echo date('Y-m-d'); ?>">
        </div>
        <button type="submit" class="btn btn-primary btn-sm">查询</button>
      </form>
      <div id="stats-result"></div>
    </div>
  </div>

  <!-- 用户管理 -->
  <div class="page" id="page-users">
    <div class="card">
      <h3>👥 用户管理 <button class="btn btn-primary btn-sm" onclick="showAddUser()" style="margin-left:8px;">＋ 新增</button></h3>
      <table>
        <thead><tr><th>用户名</th><th>名称</th><th>角色</th><th>操作</th></tr></thead>
        <tbody id="users-list"></tbody>
      </table>
    </div>
  </div>

  <!-- 操作日志 -->
  <div class="page" id="page-logs">
    <div class="card">
      <h3>📋 操作日志</h3>
      <div style="overflow-x:auto">
        <table>
          <thead><tr><th>时间</th><th>用户</th><th>操作</th><th>IP/地区</th><th>详情</th></tr></thead>
          <tbody id="logs-list"></tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- 审核管理 -->
  <div class="page" id="page-audit">
    <div class="card">
      <h3>✅ 审核管理</h3>
      <div style="margin-bottom:14px;">
        <label style="font-weight:600;color:#374151;">审核状态：</label>
        <select id="audit-filter" onchange="loadAuditList()" style="padding:6px 12px;border-radius:8px;border:1.5px solid #e5e7eb;">
          <option value="pending">待审核</option>
          <option value="approved">已通过</option>
          <option value="rejected">已拒绝</option>
          <option value="all">全部</option>
        </select>
      </div>
      <div style="overflow-x:auto">
        <table>
          <thead>
            <tr>
              <th>时间</th>
              <th>申请人</th>
              <th>操作类型</th>
              <th>目标类型</th>
              <th>原数据</th>
              <th>新数据</th>
              <th>状态</th>
              <th>操作</th>
            </tr>
          </thead>
          <tbody id="audit-list"></tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- 数据管理 -->
  <div class="page" id="page-data-mgmt">
    <div class="card">
      <h3>⚙️ 数据管理</h3>

      <div style="margin:20px 0;padding:16px;background:#fef2f2;border:1.5px solid #fecaca;border-radius:12px;">
        <h4 style="color:#dc2626;margin-bottom:12px;">⚠️ 危险操作区（需要管理员密码确认）</h4>
        <p style="font-size:13px;color:#666;">以下操作不可逆，请谨慎操作！</p>

        <div style="display:flex;gap:14px;flex-wrap:wrap;align-items:flex-end;margin-top:14px;">
          <div class="form-group" style="margin-bottom:0;">
            <label style="color:#991b1b;font-weight:600;">管理员密码</label>
            <input type="password" id="dm-password" placeholder="请输入管理员密码" style="border-color:#fca5a5;">
          </div>
          <button class="btn btn-danger" onclick="adminResetWallets()">💰 钱包清零</button>
          <button class="btn btn-danger" onclick="adminClearData()" style="background:#7f1d1d;">🗑️ 清空全部数据</button>
        </div>
        <p style="font-size:11.5px;color:#ef4444;margin-top:8px;">
          · 钱包清零 = 所有钱包余额归0 + 交易记录清空<br>
          · 清空全部数据 = 捐款+交易+钱包余额+审核记录 全部清空（用户账号保留）
        </p>
      </div>

      <hr style="border:none;border-top:2px solid #e5e7eb;margin:24px 0;">

      <div style="margin-bottom:20px;">
        <h4 style="margin-bottom:10px;">📦 数据备份</h4>
        <button class="btn btn-success" onclick="adminBackup()">💾 创建备份</button>
        <span id="backup-status" style="margin-left:10px;font-size:13px;"></span>
      </div>

      <div>
        <h4 style="margin-bottom:10px;">📂 备份文件 &amp; 数据还原</h4>
        <div id="backup-list" style="min-height:60px;padding:12px;background:#f9fafb;border-radius:8px;font-size:13px;">
          加载中...
        </div>
        <div style="margin-top:10px;display:flex;gap:10px;align-items:center;" id="restore-area">
          <select id="restore-file-select" style="padding:6px 12px;border-radius:8px;border:1.5px solid #e5e7eb;">
            <option value="">-- 选择备份文件 --</option>
          </select>
          <input type="password" id="restore-password" placeholder="管理员密码" style="width:160px;padding:6px 10px;border-radius:8px;border:1.5px solid #e5e7eb;">
          <button class="btn btn-warning" onclick="adminRestore()">🔄 一键还原</button>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- 款项用途（所有用户可见） -->
  <div class="page" id="page-expense-records">
    <div class="card">
      <h3>💸 款项用途（所有支出记录）</h3>

      <!-- 统计卡片 -->
      <div id="er-stats" style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:18px;">
        <div style="flex:1;min-width:140px;background:linear-gradient(135deg,#fef3c7,#fde68a);border-radius:12px;padding:14px 18px;">
          <div style="font-size:0.8rem;color:#92400e;margin-bottom:4px;">📅 今日支出</div>
          <div id="er-stat-today" style="font-size:1.3rem;font-weight:700;color:#92400e;">¥0.00</div>
        </div>
        <div style="flex:1;min-width:140px;background:linear-gradient(135deg,#dbeafe,#bfdbfe);border-radius:12px;padding:14px 18px;">
          <div style="font-size:0.8rem;color:#1e40af;margin-bottom:4px;">📆 本月支出</div>
          <div id="er-stat-current" style="font-size:1.3rem;font-weight:700;color:#1e40af;">¥0.00</div>
        </div>
        <div style="flex:1;min-width:140px;background:linear-gradient(135deg,#e0e7ff,#c7d2fe);border-radius:12px;padding:14px 18px;">
          <div style="font-size:0.8rem;color:#3730a3;margin-bottom:4px;">🗓️ 上月支出</div>
          <div id="er-stat-last" style="font-size:1.3rem;font-weight:700;color:#3730a3;">¥0.00</div>
        </div>
        <div style="flex:1;min-width:140px;background:linear-gradient(135deg,#fce7f3,#fbcfe8);border-radius:12px;padding:14px 18px;">
          <div style="font-size:0.8rem;color:#9d174d;margin-bottom:4px;">🔍 筛选合计</div>
          <div id="er-stat-filtered" style="font-size:1.3rem;font-weight:700;color:#9d174d;">¥0.00</div>
        </div>
      </div>

      <!-- 筛选栏 -->
      <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px;align-items:flex-end;">
        <div class="form-group" style="margin-bottom:0;">
          <input type="text" id="er-search" placeholder="搜索姓名/用途..." style="width:180px;padding:8px 12px;border:1.5px solid #e5e7eb;border-radius:10px;">
        </div>
        <div class="form-group" style="margin-bottom:0;">
          <select id="er-category-filter" style="padding:8px 12px;border:1.5px solid #e5e7eb;border-radius:10px;">
            <option value="">全部分类</option>
          </select>
        </div>
        <div class="form-group" style="margin-bottom:0;">
          <label style="font-size:0.75rem;color:#666;display:block;margin-bottom:3px;">开始日期</label>
          <input type="date" id="er-start-date" style="padding:7px 10px;border:1.5px solid #e5e7eb;border-radius:10px;">
        </div>
        <div class="form-group" style="margin-bottom:0;">
          <label style="font-size:0.75rem;color:#666;display:block;margin-bottom:3px;">结束日期</label>
          <input type="date" id="er-end-date" style="padding:7px 10px;border:1.5px solid #e5e7eb;border-radius:10px;">
        </div>
        <div style="display:flex;gap:6px;align-items:flex-end;">
          <button class="btn btn-primary" onclick="erSetMonth('current');loadExpenseRecords(1);" style="padding:6px 12px;font-size:0.8rem;">本月</button>
          <button class="btn" onclick="erSetMonth('last');loadExpenseRecords(1);" style="padding:6px 12px;font-size:0.8rem;background:#e5e7eb;">上月</button>
          <button class="btn" onclick="erClearDate();loadExpenseRecords(1);" style="padding:6px 12px;font-size:0.8rem;background:#e5e7eb;">清空时间</button>
        </div>
        <button class="btn btn-primary" onclick="loadExpenseRecords(1)">搜索</button>
      </div>

      <table class="data-table">
        <thead>
          <tr>
            <th>支出人</th>
            <th>分类</th>
            <th>金额</th>
            <th>用途说明</th>
            <th>消费凭证</th>
            <th>时间</th>
          </tr>
        </thead>
        <tbody id="er-tbody">
          <tr><td colspan="6" style="text-align:center;color:#999;">加载中...</td></tr>
        </tbody>
      </table>
      <div id="er-pagination" style="margin-top:14px;text-align:center;"></div>
    </div>
  </div>

  <!-- 证件资料（所有用户可见） -->
  <div class="page" id="page-documents">
    <div class="card">
      <h3>📁 证件资料</h3>

      <!-- 统计卡片 -->
      <div id="doc-stats" style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:18px;">
        <div style="flex:1;min-width:120px;background:linear-gradient(135deg,#ede9fe,#ddd6fe);border-radius:12px;padding:14px 18px;">
          <div style="font-size:0.8rem;color:#5b21b6;margin-bottom:4px;">📋 证件总数</div>
          <div id="doc-stat-total" style="font-size:1.3rem;font-weight:700;color:#5b21b6;">0</div>
        </div>
        <div style="flex:1;min-width:120px;background:linear-gradient(135deg,#dbeafe,#bfdbfe);border-radius:12px;padding:14px 18px;">
          <div style="font-size:0.8rem;color:#1e40af;margin-bottom:4px;">📎 附件总数</div>
          <div id="doc-stat-files" style="font-size:1.3rem;font-weight:700;color:#1e40af;">0</div>
        </div>
        <div style="flex:1;min-width:120px;background:linear-gradient(135deg,#fce7f3,#fbcfe8);border-radius:12px;padding:14px 18px;">
          <div style="font-size:0.8rem;color:#9d174d;margin-bottom:4px;">👤 保管人</div>
          <div id="doc-stat-keepers" style="font-size:1.3rem;font-weight:700;color:#9d174d;">0</div>
        </div>
      </div>

      <!-- 搜索/筛选栏 -->
      <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px;align-items:center;">
        <input type="text" id="doc-search" placeholder="搜索证件名称..." style="padding:8px 12px;border:1.5px solid #e5e7eb;border-radius:10px;flex:1;min-width:180px;" onkeyup="loadDocuments()">
        <button onclick="showDocumentForm()" class="btn btn-primary" id="btn-add-document">+ 新增证件</button>
      </div>

      <div style="overflow-x:auto">
        <table class="data-table">
          <thead><tr><th>序号</th><th>证件名称</th><th>文件数</th><th>保管人</th><th>上传时间</th><th>操作</th></tr></thead>
          <tbody id="doc-tbody">
            <tr><td colspan="6" style="text-align:center;color:#999;">加载中...</td></tr>
          </tbody>
        </table>
      </div>
    </div>
    <!-- 证件详情弹窗 -->
    <div id="doc-detail-overlay" class="modal-overlay" style="display:none;" onclick="if(event.target===this)closeDocDetail();">
      <div style="background:#fff;border-radius:16px;width:90%;max-width:560px;max-height:80vh;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,0.2);">
        <div style="background:linear-gradient(135deg,#667eea,#764ba2);padding:18px 24px;display:flex;align-items:center;justify-content:space-between;">
          <h3 style="margin:0;color:#fff;font-size:1.1rem;">📁 证件详情</h3>
          <button onclick="closeDocDetail()" style="background:none;border:none;color:#fff;font-size:22px;cursor:pointer;line-height:1;">✕</button>
        </div>
        <div id="doc-detail-body" style="padding:16px;"></div>
      </div>
    </div>
    </div>
  </div>

  <!-- 备忘录（所有用户可见） -->
  <div class="page" id="page-memos">
    <div class="card">
      <h3>📝 备忘录</h3>

      <!-- 统计卡片 -->
      <div id="memo-stats" style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:18px;">
        <div style="flex:1;min-width:120px;background:linear-gradient(135deg,#fce7f3,#fbcfe8);border-radius:12px;padding:14px 18px;">
          <div style="font-size:0.8rem;color:#9d174d;margin-bottom:4px;">📝 备忘总数</div>
          <div id="memo-stat-total" style="font-size:1.3rem;font-weight:700;color:#9d174d;">0</div>
        </div>
        <div style="flex:1;min-width:120px;background:linear-gradient(135deg,#fef3c7,#fde68a);border-radius:12px;padding:14px 18px;">
          <div style="font-size:0.8rem;color:#92400e;margin-bottom:4px;">📅 今日新增</div>
          <div id="memo-stat-today" style="font-size:1.3rem;font-weight:700;color:#92400e;">0</div>
        </div>
        <div style="flex:1;min-width:120px;background:linear-gradient(135deg,#d1fae5,#a7f3d0);border-radius:12px;padding:14px 18px;">
          <div style="font-size:0.8rem;color:#065f46;margin-bottom:4px;">✏️ 近期更新</div>
          <div id="memo-stat-updated" style="font-size:1.3rem;font-weight:700;color:#065f46;">0</div>
        </div>
      </div>

      <!-- 搜索/操作栏 -->
      <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px;align-items:center;">
        <input type="text" id="memo-search" placeholder="搜索备忘内容..." style="padding:8px 12px;border:1.5px solid #e5e7eb;border-radius:10px;flex:1;min-width:180px;" onkeyup="loadMemos()">
        <button onclick="showMemoForm()" class="btn btn-primary">+ 新增备忘</button>
      </div>

      <div id="memo-list" style="max-height:500px;overflow-y:auto"></div>
    </div>
  </div>

  <!-- 通讯录（所有用户可见） -->
  <div class="page" id="page-contacts">
    <div class="card">
      <h3>📞 通讯录</h3>

      <!-- 统计卡片 -->
      <div id="contact-stats" style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:18px;">
        <div style="flex:1;min-width:120px;background:linear-gradient(135deg,#d1fae5,#a7f3d0);border-radius:12px;padding:14px 18px;">
          <div style="font-size:0.8rem;color:#065f46;margin-bottom:4px;">👥 联系人总数</div>
          <div id="contact-stat-total" style="font-size:1.3rem;font-weight:700;color:#065f46;">0</div>
        </div>
        <div style="flex:1;min-width:120px;background:linear-gradient(135deg,#dbeafe,#bfdbfe);border-radius:12px;padding:14px 18px;">
          <div style="font-size:0.8rem;color:#1e40af;margin-bottom:4px;">📱 有电话</div>
          <div id="contact-stat-phone" style="font-size:1.3rem;font-weight:700;color:#1e40af;">0</div>
        </div>
        <div style="flex:1;min-width:120px;background:linear-gradient(135deg,#fef3c7,#fde68a);border-radius:12px;padding:14px 18px;">
          <div style="font-size:0.8rem;color:#92400e;margin-bottom:4px;">📧 有邮箱</div>
          <div id="contact-stat-email" style="font-size:1.3rem;font-weight:700;color:#92400e;">0</div>
        </div>
      </div>

      <!-- 搜索/操作栏 -->
      <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px;align-items:center;">
        <input type="text" id="contact-search" placeholder="搜索姓名/备注..." style="padding:8px 12px;border:1.5px solid #e5e7eb;border-radius:10px;flex:1;min-width:180px;" onkeyup="loadContacts()">
        <button onclick="showContactForm()" class="btn btn-primary" id="btn-add-contact">+ 新增联系人</button>
      </div>

      <div id="contact-list" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px;"></div>
    </div>
  </div>

  <!-- 家庭公告（所有用户可见） -->
  <div class="page" id="page-announcements">
    <div class="card">
      <h3>📢 家庭公告</h3>

      <!-- 统计卡片 -->
      <div id="ann-stats" style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:18px;">
        <div style="flex:1;min-width:120px;background:linear-gradient(135deg,#fef3c7,#fde68a);border-radius:12px;padding:14px 18px;">
          <div style="font-size:0.8rem;color:#92400e;margin-bottom:4px;">📢 公告总数</div>
          <div id="ann-stat-total" style="font-size:1.3rem;font-weight:700;color:#92400e;">0</div>
        </div>
        <div style="flex:1;min-width:120px;background:linear-gradient(135deg,#d1fae5,#a7f3d0);border-radius:12px;padding:14px 18px;">
          <div style="font-size:0.8rem;color:#065f46;margin-bottom:4px;">📅 今日新增</div>
          <div id="ann-stat-today" style="font-size:1.3rem;font-weight:700;color:#065f46;">0</div>
        </div>
        <div style="flex:1;min-width:120px;background:linear-gradient(135deg,#dbeafe,#bfdbfe);border-radius:12px;padding:14px 18px;">
          <div style="font-size:0.8rem;color:#1e40af;margin-bottom:4px;">🔝 置顶公告</div>
          <div id="ann-stat-top" style="font-size:1.3rem;font-weight:700;color:#1e40af;">0</div>
        </div>
      </div>

      <!-- 操作栏 -->
      <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px;align-items:center;">
        <input type="text" id="ann-search" placeholder="搜索公告标题..." style="padding:8px 12px;border:1.5px solid #e5e7eb;border-radius:10px;flex:1;min-width:180px;" onkeyup="loadAnnouncements()">
        <button onclick="showAnnForm()" class="btn btn-primary">+ 发布公告</button>
      </div>

      <!-- 公告列表 -->
      <div id="ann-list" style="max-height:600px;overflow-y:auto;"></div>
    </div>

    <!-- 公告表单弹窗 -->
    <div id="ann-form-overlay" class="modal-overlay" style="display:none;" onclick="if(event.target===this)closeAnnForm();">
      <div class="modal" style="width:92%;max-width:580px;max-height:85vh;overflow-y:auto;padding:0;">
        <div style="background:linear-gradient(135deg,#f59e0b,#d97706);padding:18px 22px;display:flex;align-items:center;justify-content:space-between;border-radius:16px 16px 0 0;">
          <h3 id="ann-form-title" style="margin:0;color:#fff;font-size:1.1rem;">📢 发布公告</h3>
          <button onclick="closeAnnForm()" style="background:none;border:none;color:#fff;font-size:22px;cursor:pointer;line-height:1;">✕</button>
        </div>
        <form id="ann-form" onsubmit="saveAnn();return false;" style="padding:20px 22px;">
          <input type="hidden" id="ann-form-id" value="">
          <div style="margin-bottom:14px;">
            <label style="font-size:0.85rem;color:#6b7280;margin-bottom:4px;display:block;">公告标题 *</label>
            <input type="text" id="ann-form-title-input" placeholder="请输入公告标题" required style="width:100%;padding:10px 14px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:0.95rem;box-sizing:border-box;">
          </div>
          <div style="margin-bottom:14px;">
            <label style="font-size:0.85rem;color:#6b7280;margin-bottom:4px;display:block;">公告内容 *</label>
            <textarea id="ann-form-content" rows="5" placeholder="请输入公告内容..." required style="width:100%;padding:10px 14px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:0.95rem;resize:vertical;box-sizing:border-box;"></textarea>
          </div>
          <div style="margin-bottom:18px;">
            <label style="font-size:0.85rem;color:#6b7280;margin-bottom:8px;display:block;">📷 图片上传（可多选）</label>
            <div id="ann-upload-area" style="border:2px dashed #fbbf24;background:#fffbeb;border-radius:12px;padding:24px;text-align:center;cursor:pointer;" onclick="document.getElementById('ann-form-images').click();">
              <div style="font-size:2rem;">📸</div>
              <div style="color:#92400e;margin-top:6px;">点击或拖拽上传图片</div>
              <div style="color:#d97706;font-size:0.78rem;margin-top:2px;">支持 JPG/PNG/GIF，单张不超过 10MB</div>
              <input type="file" id="ann-form-images" multiple accept="image/*" style="display:none;" onchange="previewAnnImages(this)">
            </div>
            <div id="ann-image-preview-list" style="display:flex;flex-wrap:wrap;gap:8px;margin-top:10px;"></div>
          </div>
          <div style="display:flex;gap:10px;">
            <button type="submit" class="btn btn-primary" style="flex:1;padding:11px;font-size:1rem;">✅ 发布</button>
            <button type="button" onclick="closeAnnForm()" class="btn" style="flex:1;padding:11px;font-size:1rem;background:#f3f4f6;color:#6b7280;">取消</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- 医院就诊记录 -->
  <div class="page" id="page-medical-records">
    <div class="card">
      <h3>🏥 医院就诊记录</h3>

      <!-- 统计卡片 -->
      <div id="medical-stats" style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:18px;">
        <div style="flex:1;min-width:120px;background:linear-gradient(135deg,#fee2e2,#fecaca);border-radius:12px;padding:14px 18px;">
          <div style="font-size:0.8rem;color:#991b1b;margin-bottom:4px;">🏥 就诊次数</div>
          <div id="medical-stat-total" style="font-size:1.3rem;font-weight:700;color:#991b1b;">0</div>
        </div>
        <div style="flex:1;min-width:120px;background:linear-gradient(135deg,#fef3c7,#fde68a);border-radius:12px;padding:14px 18px;">
          <div style="font-size:0.8rem;color:#92400e;margin-bottom:4px;">💰 累计费用</div>
          <div id="medical-stat-cost" style="font-size:1.3rem;font-weight:700;color:#92400e;">¥0</div>
        </div>
        <div style="flex:1;min-width:120px;background:linear-gradient(135deg,#dbeafe,#bfdbfe);border-radius:12px;padding:14px 18px;">
          <div style="font-size:0.8rem;color:#1e40af;margin-bottom:4px;">👤 就诊人数</div>
          <div id="medical-stat-patients" style="font-size:1.3rem;font-weight:700;color:#1e40af;">0</div>
        </div>
      </div>

      <!-- 操作栏 -->
      <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px;align-items:center;">
        <button class="btn btn-primary" onclick="showMedicalForm()">➕ 添加就诊记录</button>
        <input type="text" id="medical-search" placeholder="搜索就诊人/医院..." style="padding:8px 12px;border:1.5px solid #e5e7eb;border-radius:10px;flex:1;min-width:200px;">
        <input type="date" id="medical-start-date" style="padding:7px 10px;border:1.5px solid #e5e7eb;border-radius:10px;">
        <input type="date" id="medical-end-date" style="padding:7px 10px;border:1.5px solid #e5e7eb;border-radius:10px;">
        <button class="btn btn-primary" onclick="loadMedicalRecords(1)">🔍 搜索</button>
      </div>

      <table class="data-table">
        <thead>
          <tr>
            <th>就诊人</th>
            <th>医院</th>
            <th>就诊日期</th>
            <th>诊断</th>
            <th>费用</th>
            <th>文件</th>
            <th>记录人</th>
            <th>操作</th>
          </tr>
        </thead>
        <tbody id="medical-tbody">
          <tr><td colspan="8" style="text-align:center;color:#999;">加载中...</td></tr>
        </tbody>
      </table>
      <div id="medical-pagination" style="margin-top:14px;text-align:center;"></div>
    </div>
  </div>

  <!-- 就诊记录表单弹窗 -->
  <div class="modal-overlay" id="medical-modal-overlay" style="display:none;">
    <div class="modal">
      <button class="modal-close" onclick="closeMedicalModal()">✕</button>
      <h3 id="medical-modal-title">添加就诊记录</h3>
      <form id="medical-form" onsubmit="saveMedicalRecord(event)">
        <input type="hidden" id="medical-id">
        <div class="form-group">
          <label>就诊人姓名 *</label>
          <input type="text" id="medical-patient-name" required>
        </div>
        <div class="form-group">
          <label>医院名称 *</label>
          <input type="text" id="medical-hospital" required>
        </div>
        <div class="form-group">
          <label>就诊日期 *</label>
          <input type="date" id="medical-visit-date" required>
        </div>
        <div class="form-group">
          <label>诊断结果</label>
          <textarea id="medical-diagnosis" rows="3" style="width:100%;padding:8px;"></textarea>
        </div>
        <div class="form-group">
          <label>费用（元）</label>
          <input type="number" id="medical-cost" step="0.01" min="0">
        </div>
        <div class="form-group">
          <label>上传文件（支持多文件）</label>
          <input type="file" id="medical-files" multiple accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx">
          <div id="medical-file-preview" style="margin-top:8px;"></div>
        </div>
        <div style="display:flex;gap:10px;justify-content:flex-end;">
          <button type="button" class="btn" onclick="closeMedicalModal()">取消</button>
          <button type="submit" class="btn btn-primary">保存</button>
        </div>
      </form>
    </div>
  </div>

  <!-- 家庭计划（所有用户可见） -->
  <div class="page" id="page-plans">
    <div class="container" style="max-width:960px;margin:0 auto;">
      <h3>📅 家庭计划</h3>

      <!-- 统计卡片 -->
      <div id="plan-stats" style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:18px;">
        <div style="flex:1;min-width:120px;background:linear-gradient(135deg,#dbeafe,#bfdbfe);border-radius:12px;padding:14px 18px;">
          <div style="font-size:0.8rem;color:#1e40af;margin-bottom:4px;">📅 总计划数</div>
          <div id="plan-stat-total" style="font-size:1.3rem;font-weight:700;color:#1e40af;">0</div>
        </div>
        <div style="flex:1;min-width:120px;background:linear-gradient(135deg,#fef3c7,#fde68a);border-radius:12px;padding:14px 18px;">
          <div style="font-size:0.8rem;color:#92400e;margin-bottom:4px;">⏳ 待完成</div>
          <div id="plan-stat-pending" style="font-size:1.3rem;font-weight:700;color:#92400e;">0</div>
        </div>
        <div style="flex:1;min-width:120px;background:linear-gradient(135deg,#d1fae5,#a7f3d0);border-radius:12px;padding:14px 18px;">
          <div style="font-size:0.8rem;color:#065f46;margin-bottom:4px;">✅ 已完成</div>
          <div id="plan-stat-done" style="font-size:1.3rem;font-weight:700;color:#065f46;">0</div>
        </div>
        <div style="flex:1;min-width:120px;background:linear-gradient(135deg,#ede9fe,#ddd6fe);border-radius:12px;padding:14px 18px;">
          <div style="font-size:0.8rem;color:#5b21b6;margin-bottom:4px;">💰 计划金额</div>
          <div id="plan-stat-amount" style="font-size:1.3rem;font-weight:700;color:#5b21b6;">¥0</div>
        </div>
      </div>

      <!-- 操作栏 -->
      <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px;align-items:center;">
        <input type="text" id="plan-search" placeholder="搜索计划事项..." style="padding:8px 12px;border:1.5px solid #e5e7eb;border-radius:10px;flex:1;min-width:180px;" onkeyup="loadPlans()">
        <select id="plan-filter" onchange="loadPlans()" style="padding:8px 12px;border:1.5px solid #e5e7eb;border-radius:10px;">
          <option value="all">全部</option>
          <option value="pending">待完成</option>
          <option value="done">已完成</option>
        </select>
        <button onclick="showPlanForm()" class="btn btn-primary">+ 新增计划</button>
      </div>

      <!-- 计划列表 -->
      <div class="card" style="padding:0;overflow:hidden;">
        <table class="data-table" style="margin:0;">
          <thead>
            <tr>
              <th>计划事项</th>
              <th>实施时间</th>
              <th>计划金额</th>
              <th>状态</th>
              <th>同意人</th>
              <th>执行人</th>
              <th>操作</th>
            </tr>
          </thead>
          <tbody id="plan-list">
            <tr><td colspan="7" style="text-align:center;color:#999;padding:40px;">暂无计划，点击上方按钮添加 📅</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- 家庭支出（所有用户可见） -->
  <div class="page" id="page-family-expenses">
    <div class="container" style="max-width:960px;margin:0 auto;">
      <h3>💸 家庭支出</h3>

      <!-- 统计卡片 -->
      <div id="expense-stats" style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:18px;">
        <div style="flex:1;min-width:120px;background:linear-gradient(135deg,#fee2e2,#fecaca);border-radius:12px;padding:14px 18px;">
          <div style="font-size:0.8rem;color:#991b1b;margin-bottom:4px;">📅 今日支出</div>
          <div id="expense-stat-daily" style="font-size:1.3rem;font-weight:700;color:#991b1b;">¥0.00</div>
        </div>
        <div style="flex:1;min-width:120px;background:linear-gradient(135deg,#ffedd5,#fed7aa);border-radius:12px;padding:14px 18px;">
          <div style="font-size:0.8rem;color:#9a3412;margin-bottom:4px;">📆 昨日支出</div>
          <div id="expense-stat-yesterday" style="font-size:1.3rem;font-weight:700;color:#9a3412;">¥0.00</div>
        </div>
        <div style="flex:1;min-width:120px;background:linear-gradient(135deg,#fef3c7,#fde68a);border-radius:12px;padding:14px 18px;">
          <div style="font-size:0.8rem;color:#92400e;margin-bottom:4px;">📇 本月支出</div>
          <div id="expense-stat-month" style="font-size:1.3rem;font-weight:700;color:#92400e;">¥0.00</div>
        </div>
        <div style="flex:1;min-width:120px;background:linear-gradient(135deg,#e0e7ff,#c7d2fe);border-radius:12px;padding:14px 18px;">
          <div style="font-size:0.8rem;color:#3730a3;margin-bottom:4px;">📈 上月支出</div>
          <div id="expense-stat-last-month" style="font-size:1.3rem;font-weight:700;color:#3730a3;">¥0.00</div>
        </div>
        <div style="flex:1;min-width:120px;background:linear-gradient(135deg,#dbeafe,#bfdbfe);border-radius:12px;padding:14px 18px;">
          <div style="font-size:0.8rem;color:#1e40af;margin-bottom:4px;">⏳ 未结清</div>
          <div id="expense-stat-pending" style="font-size:1.3rem;font-weight:700;color:#1e40af;">¥0.00</div>
        </div>
        <div style="flex:1;min-width:120px;background:linear-gradient(135deg,#dcfce7,#bbf7d0);border-radius:12px;padding:14px 18px;">
          <div style="font-size:0.8rem;color:#166534;margin-bottom:4px;">📊 总支出</div>
          <div id="expense-stat-total" style="font-size:1.3rem;font-weight:700;color:#166534;">¥0.00</div>
        </div>
      </div>

      <!-- 操作栏 -->
      <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px;align-items:center;">
        <input type="text" id="expense-search" placeholder="搜索用途..." style="padding:8px 12px;border:1.5px solid #e5e7eb;border-radius:10px;flex:1;min-width:180px;" onkeyup="loadExpenses()">
        <button onclick="showExpenseForm()" class="btn btn-primary">+ 新增支出</button>
      </div>

      <!-- 支出列表 -->
      <div class="card" style="padding:0;overflow:hidden;">
        <table class="data-table" style="margin:0;">
          <thead>
            <tr>
              <th>用途</th>
              <th>总金额</th>
              <th>每人AA</th>
              <th>已到款</th>
              <th>未到款</th>
              <th>已交款</th>
              <th>执行人</th>
              <th>支出日期</th>
              <th>结算状态</th>
              <th>操作</th>
            </tr>
          </thead>
          <tbody id="expense-list">
            <tr><td colspan="10" style="text-align:center;color:#999;padding:40px;">暂无支出记录，点击上方按钮添加 💸</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- 家庭计划表单弹窗 -->
  <div class="modal-overlay" id="plan-form-overlay" style="display:none;" onclick="if(event.target===this)closePlanForm()">
    <div class="modal" style="width:92%;max-width:540px;max-height:90vh;overflow-y:auto;padding:0;">
      <div style="background:linear-gradient(135deg,#6366f1,#8b5cf6);padding:18px 22px;display:flex;align-items:center;justify-content:space-between;border-radius:16px 16px 0 0;">
        <h3 id="plan-form-title" style="margin:0;color:#fff;font-size:1.1rem;">📅 新增计划</h3>
        <button onclick="closePlanForm()" style="background:none;border:none;color:#fff;font-size:22px;cursor:pointer;line-height:1;">✕</button>
      </div>
      <form id="plan-form" onsubmit="savePlan(event)" style="padding:20px 22px;">
        <input type="hidden" id="plan-form-id">
        <div style="margin-bottom:14px;">
          <label style="font-size:0.85rem;color:#6b7280;margin-bottom:4px;display:block;">计划事项 *</label>
          <input type="text" id="plan-form-title-input" placeholder="请输入计划事项" required style="width:100%;padding:10px 14px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:0.95rem;box-sizing:border-box;">
        </div>
        <div style="margin-bottom:14px;">
          <label style="font-size:0.85rem;color:#6b7280;margin-bottom:4px;display:block;">详细说明</label>
          <textarea id="plan-form-desc" rows="3" placeholder="计划详细说明（可选）" style="width:100%;padding:10px 14px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:0.95rem;box-sizing:border-box;resize:vertical;"></textarea>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
          <div>
            <label style="font-size:0.85rem;color:#6b7280;margin-bottom:4px;display:block;">实施时间</label>
            <input type="date" id="plan-form-date" style="width:100%;padding:10px 14px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:0.95rem;box-sizing:border-box;">
          </div>
          <div>
            <label style="font-size:0.85rem;color:#6b7280;margin-bottom:4px;display:block;">计划金额（元）</label>
            <input type="number" id="plan-form-amount" step="0.01" min="0" placeholder="0.00" style="width:100%;padding:10px 14px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:0.95rem;box-sizing:border-box;">
          </div>
        </div>
        <div style="margin-bottom:14px;">
          <label style="font-size:0.85rem;color:#6b7280;margin-bottom:4px;display:block;">执行人</label>
          <input type="text" id="plan-form-executor" placeholder="请输入执行人姓名（可选）" style="width:100%;padding:10px 14px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:0.95rem;box-sizing:border-box;">
        </div>
        <div style="margin-bottom:18px;">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:0.95rem;">
            <input type="checkbox" id="plan-form-done"> 已完成
          </label>
        </div>
        <div style="display:flex;gap:10px;justify-content:flex-end;">
          <button type="button" class="btn" onclick="closePlanForm()">取消</button>
          <button type="submit" class="btn btn-primary">保存</button>
        </div>
      </form>
    </div>
  </div>



  <!-- 支出表单弹窗 -->
  <div class="modal-overlay" id="expense-form-overlay" style="display:none;" onclick="if(event.target===this)closeExpenseForm()">
    <div class="modal" style="width:92%;max-width:540px;max-height:90vh;overflow-y:auto;padding:0;">
      <div style="background:linear-gradient(135deg,#f59e0b,#d97706);padding:18px 22px;display:flex;align-items:center;justify-content:space-between;border-radius:16px 16px 0 0;">
        <h3 id="expense-form-title" style="margin:0;color:#fff;font-size:1.1rem;">💸 记一笔支出</h3>
        <button onclick="closeExpenseForm()" style="background:rgba(255,255,255,0.2);border:none;color:#fff;width:32px;height:32px;border-radius:50%;font-size:1.1rem;cursor:pointer;">✕</button>
      </div>
      <form id="expense-form" onsubmit="saveExpense(event)" style="padding:20px 22px;">
        <input type="hidden" id="expense-form-id">
        <div style="margin-bottom:14px;">
          <label style="font-size:0.85rem;color:#6b7280;margin-bottom:4px;display:block;">用途 *</label>
          <input type="text" id="expense-form-purpose" placeholder="例如：妈妈生活费、购买手机" required
                 style="width:100%;padding:10px 14px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:0.95rem;box-sizing:border-box;">
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
          <div>
            <label style="font-size:0.85rem;color:#6b7280;margin-bottom:4px;display:block;">总金额（元）*</label>
            <input type="number" id="expense-form-amount" step="0.01" min="0" placeholder="0.00" required
                   style="width:100%;padding:10px 14px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:0.95rem;box-sizing:border-box;">
          </div>
          <div>
            <label style="font-size:0.85rem;color:#6b7280;margin-bottom:4px;display:block;">支出日期</label>
            <input type="date" id="expense-form-date"
                   style="width:100%;padding:10px 14px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:0.95rem;box-sizing:border-box;">
          </div>
        </div>
        <div style="margin-bottom:14px;">
          <label style="font-size:0.85rem;color:#6b7280;margin-bottom:4px;display:block;">AA 用户 *（勾选参与AA的人员）</label>
          <div id="aa-users-checkboxes" style="display:flex;flex-wrap:wrap;gap:8px;padding:10px;background:#f9fafb;border-radius:10px;border:1.5px solid #e5e7eb;min-height:42px;">
            <span style="color:#9ca3af;font-size:0.85rem;">加载中...</span>
          </div>
        </div>
        <div style="margin-bottom:14px;">
          <label style="font-size:0.85rem;color:#6b7280;margin-bottom:4px;display:block;">执行人</label>
          <select id="expense-form-executor-id"
                  style="width:100%;padding:10px 14px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:0.95rem;box-sizing:border-box;">
            <option value="0">请选择执行人</option>
          </select>
        </div>
        <div style="margin-bottom:14px;">
          <label style="font-size:0.85rem;color:#6b7280;margin-bottom:4px;display:block;">备注</label>
          <textarea id="expense-form-remark" rows="2" placeholder="备注（可选）"
                    style="width:100%;padding:10px 14px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:0.95rem;box-sizing:border-box;resize:vertical;"></textarea>
        </div>
        <div style="margin-bottom:14px;">
          <label style="font-size:0.85rem;color:#6b7280;margin-bottom:4px;display:block;">消费凭证（可多选）</label>
          <input type="file" id="expense-form-receipts" multiple accept="image/*,.pdf"
                 style="width:100%;font-size:0.85rem;">
        </div>
        <div style="display:flex;gap:10px;justify-content:flex-end;">
          <button type="button" class="btn" onclick="closeExpenseForm()">取消</button>
          <button type="submit" class="btn btn-primary">保存</button>
        </div>
      </form>
    </div>
  </div>

  <!-- 就诊记录详情浮层 -->
  <div class="modal-overlay" id="medical-detail-overlay" style="display:none;" onclick="closeMedicalDetail(event)">
    <div class="modal" style="max-width:680px;width:96%;" onclick="event.stopPropagation()">
      <button class="modal-close" onclick="closeMedicalDetail()">✕</button>
      <h3 id="detail-patient-name" style="margin-bottom:12px;"></h3>
      <div id="detail-body" style="max-height:65vh;overflow-y:auto;"></div>
      <div id="detail-actions" style="display:flex;gap:10px;justify-content:flex-end;margin-top:16px;"></div>
    </div>
  </div>

<script>
// ===== 全局状态 =====
const CURRENT_ROLE  = '<?php echo $currentRole; ?>';
const CURRENT_NAME  = '<?php echo addslashes($currentName); ?>';
const CURRENT_ID    = <?php echo $currentId; ?>;
const IS_ADMIN      = <?php echo $isAdmin; ?>;

// ===== 分享参数（PHP 传入）=====
const SHARE_TO   = '<?php echo addslashes($shareTo); ?>';
const SHARE_ID   = '<?php echo addslashes($shareId); ?>';
const SHARE_HASH = '<?php echo addslashes($shareHash); ?>';

// ===== HTML 转义函数（防止 XSS）=====
function escHtml(str) {
    if (str === null || str === undefined) return '';
    const div = document.createElement('div');
    div.textContent = String(str);
    return div.innerHTML;
}

function formatDateTime(dtStr) {
    if (!dtStr) return '-';
    try {
        var d = new Date(dtStr.replace(/-/g, '/').replace('T', ' '));
        var pad = function(n){return n<10?'0'+n:n;};
        return d.getFullYear() + '-' + pad(d.getMonth()+1) + '-' + pad(d.getDate()) + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
    } catch(e) { return dtStr; }
}

let   MY_KEY        = '';

// ===== 侧边栏控制 =====
function toggleSidebar() {
    var sb = document.getElementById('sidebar');
    var ol = document.getElementById('sidebar-overlay');
    var mc = document.querySelector('.main-content');
    if (window.innerWidth >= 768) {
        // 桌面端：toggle active 展开/收起
        sb.classList.toggle('active');
        if (mc) mc.classList.toggle('shifted');
    } else {
        // 移动端：用遮罩模式
        sb.classList.toggle('active');
        ol.classList.toggle('active');
    }
}
function closeSidebar() {
    var sb = document.getElementById('sidebar');
    var ol = document.getElementById('sidebar-overlay');
    var mc = document.querySelector('.main-content');
    sb.classList.remove('active');
    ol.classList.remove('active');
    if (mc) mc.classList.remove('shifted');
}
// 点击导航链接后自动关闭侧边栏（移动端）
document.querySelectorAll('.sidebar-link').forEach(link => {
    link.addEventListener('click', () => {
        if (window.innerWidth < 768) closeSidebar();
    });
});

// ===== 导航切换 =====
document.querySelectorAll('.sidebar-link').forEach(link => {
    link.addEventListener('click', function(e) {
        e.preventDefault();
        document.querySelectorAll('.sidebar-link').forEach(l => l.classList.remove('active'));
        this.classList.add('active');
        document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
        const page = document.getElementById('page-' + this.dataset.page);
        if (page) page.classList.add('active');
        const pageName = this.dataset.page;
        if (pageName === 'dashboard')  loadDashboard();
        if (pageName === 'records')    loadDonations();
        if (pageName === 'rank')       loadRank();
        if (pageName === 'wallet')     loadWallet();
        if (pageName === 'stats')      loadStats();
        if (pageName === 'users')      loadUsers();
        if (pageName === 'audit')      loadAuditList();
        if (pageName === 'logs')       loadLogs();
        if (pageName === 'data-mgmt')  loadDataMgmt();
        if (pageName === 'documents')  loadDocuments();
        if (pageName === 'memos')      loadMemos();
        if (pageName === 'contacts')   loadContacts();
        if (pageName === 'announcements') loadAnnouncements();
        if (pageName === 'plans')       loadPlans();
        if (pageName === 'medical-records') { loadMedicalRecords(1); }
        if (pageName === 'expense-records') { loadExpenseStats(); loadExpenseRecords(); }
        if (pageName === 'family-expenses') { loadExpenses(); }
    });
});

// ===== 退出 =====
function doLogout() {
    fetch('api/logout.php').then(()=>location.reload());
}

// ===== 仪表盘 =====
function loadDashboard() {
    // 加载钱包余额（同时设置 MY_KEY）
    fetch('api/wallet.php?action=balances')
        .then(r=>r.json()).then(d=>{
            console.log('[balances] 返回数据:', d);
            if (!d.success) { alert('加载余额失败：' + (d.msg||'未知错误')); return; }
            let total = 0;
            d.data.forEach(w=>{
                total += parseFloat(w.balance);
                if (w.person_name === CURRENT_NAME) {
                    MY_KEY = w.person_key;
                    const el = document.getElementById('stat-my-wallet');
                    if (el) el.textContent = '¥' + parseFloat(w.balance).toFixed(2);
                }
            });
            if (IS_ADMIN) {
                const el = document.getElementById('stat-wallet-total');
                if (el) el.textContent = '¥' + total.toFixed(2);
            }

            // 填充各钱包余额表格
            const tbody = document.getElementById('balances-table-body');
            if (tbody && d.data.length > 0) {
                let html = '';
                d.data.forEach(w => {
                    html += '<tr>'
                        + '<td style="font-weight:600;">' + (w.person_name||w.person_key) + '</td>'
                        + '<td style="color:#dc2626;font-weight:700;">¥' + parseFloat(w.balance).toFixed(2) + '</td>';
                    if (IS_ADMIN) {
                        html += '<td style="white-space:nowrap;">'
                            + '<button onclick="doClearWallet(\'' + w.person_key + '\',\'' + (w.person_name||w.person_key).replace(/'/g, "\\'") + '\',' + parseFloat(w.balance).toFixed(2) + ')" style="padding:4px 10px;font-size:0.78rem;background:#fef2f2;color:#dc2626;border:1.5px solid #fecaca;border-radius:6px;cursor:pointer;margin-right:5px;" title="清空此钱包">清空</button>'
                            + '<button onclick="openWalletEdit(\'' + w.person_key + '\',\'' + (w.person_name||w.person_key).replace(/'/g, "\\'") + '\',' + parseFloat(w.balance).toFixed(2) + ')" style="padding:4px 10px;font-size:0.78rem;background:#f0f9ff;color:#0369a1;border:1.5px solid #bae6fd;border-radius:6px;cursor:pointer;" title="修改余额">修改</button>'
                            + '</td>';
                    }
                    html += '</tr>';
                });
                tbody.innerHTML = html;
            } else if (tbody) {
                tbody.innerHTML = '<tr><td colspan="' + (IS_ADMIN ? '3' : '2') + '" style="text-align:center;color:#999;">暂无数据</td></tr>';
            }
        })
        .catch(e=>{ console.error('[balances] 请求失败:', e); alert('加载余额接口失败，请检查控制台'); });
    // 捐款统计
    fetch('api/donations.php?stats=1')
        .then(r=>r.json()).then(d=>{
            if (!d.success) return;
            document.getElementById('stat-total').textContent = '¥' + parseFloat(d.data.total_amount||0).toFixed(2);
            document.getElementById('stat-count').textContent = d.data.total_count || 0;
            document.getElementById('stat-personal-amount').textContent = '¥' + parseFloat(d.data.personal_amount||0).toFixed(2);
            document.getElementById('stat-personal-count').textContent = d.data.personal_count || 0;
        })
        .catch(e=>console.error('[stats] 请求失败:', e));

    // 加载昨日/今日/上月/本月统计
    fetch('api/dashboard_stats.php')
        .then(r=>r.json()).then(d=>{
            if (!d.success) return;
            const data = d.data;
            // 捐款统计
            const dsToday = document.getElementById('ds-today');
            const dsYesterday = document.getElementById('ds-yesterday');
            const dsThisMonth = document.getElementById('ds-this-month');
            const dsLastMonth = document.getElementById('ds-last-month');
            if (dsToday) dsToday.textContent = data.today_donation.count + ' 笔 / ¥' + parseFloat(data.today_donation.total||0).toFixed(2);
            if (dsYesterday) dsYesterday.textContent = data.yesterday_donation.count + ' 笔 / ¥' + parseFloat(data.yesterday_donation.total||0).toFixed(2);
            if (dsThisMonth) dsThisMonth.textContent = data.this_month_donation.count + ' 笔 / ¥' + parseFloat(data.this_month_donation.total||0).toFixed(2);
            if (dsLastMonth) dsLastMonth.textContent = data.last_month_donation.count + ' 笔 / ¥' + parseFloat(data.last_month_donation.total||0).toFixed(2);
            // 支出统计
            const esToday = document.getElementById('es-today');
            const esYesterday = document.getElementById('es-yesterday');
            const esThisMonth = document.getElementById('es-this-month');
            const esLastMonth = document.getElementById('es-last-month');
            if (esToday) esToday.textContent = data.today_expense.count + ' 笔 / ¥' + parseFloat(data.today_expense.total||0).toFixed(2);
            if (esYesterday) esYesterday.textContent = data.yesterday_expense.count + ' 笔 / ¥' + parseFloat(data.yesterday_expense.total||0).toFixed(2);
            if (esThisMonth) esThisMonth.textContent = data.this_month_expense.count + ' 笔 / ¥' + parseFloat(data.this_month_expense.total||0).toFixed(2);
            if (esLastMonth) esLastMonth.textContent = data.last_month_expense.count + ' 笔 / ¥' + parseFloat(data.last_month_expense.total||0).toFixed(2);
        })
        .catch(e=>console.error('[dashboard_stats] 请求失败:', e));
}

// 初始加载
loadDashboard();

// 处理分享参数：自动跳转到分享的页面
(function handleShare(){
    // 优先用 PHP 传入的常量，其次用 sessionStorage（未登录时存入，登录后读取）
    var to = (SHARE_TO && SHARE_TO !== '') ? SHARE_TO : sessionStorage.getItem('share_to');
    var id = (SHARE_ID && SHARE_ID !== '') ? SHARE_ID : sessionStorage.getItem('share_id');
    // 用完即清
    sessionStorage.removeItem('share_to');
    sessionStorage.removeItem('share_id');
    if (!to) return;
    // 等待页面稳定后再跳转
    setTimeout(function(){
        var link = document.querySelector('.sidebar-link[data-page="'+to+'"]');
        if (link) {
            link.click();
            // 如果有记录 ID，尝试高亮并滚动到对应行
            if (id) {
                setTimeout(function(){
                    var row = document.querySelector('[data-id="'+id+'"]');
                    if (row) {
                        row.scrollIntoView({behavior:'smooth', block:'center'});
                        row.style.backgroundColor = '#fef3c7';
                        setTimeout(function(){ row.style.backgroundColor = ''; }, 3000);
                    }
                }, 1200);
            }
        }
    }, 600);
})();

// ===== 登记捐款 =====
document.getElementById('form-donate').addEventListener('submit', function(e) {
    e.preventDefault();
    const msg = document.getElementById('donate-msg');
    msg.innerHTML = '';
    const name = document.getElementById('donor-name').value.trim();
    const amt  = document.getElementById('donor-amount').value;
    if (!name || !amt || amt <= 0) { alert('请填写完整信息'); return; }
    fetch('api/donations.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            donor_name:    name,
            amount:        amt,
            donate_date:   document.getElementById('donor-date').value,
            source:        document.getElementById('donor-source').value,
            payment_method: document.getElementById('donor-pay').value,
            note:          document.getElementById('donor-note').value.trim(),
        })
    }).then(r=>r.json()).then(d=>{
        if (d.success) {
            msg.innerHTML = '<div class="alert alert-success">✅ 登记成功！钱包已自动入账</div>';
            document.getElementById('donor-name').value = '';
            document.getElementById('donor-amount').value = '';
            document.getElementById('donor-note').value = '';
            loadDashboard();
        } else {
            msg.innerHTML = '<div class="alert alert-danger">❌ '+d.msg+'</div>';
        }
    });
});

// ===== 捐款记录 =====
function drClear() {
    document.getElementById('dr-keyword').value = '';
    document.getElementById('dr-source').value  = '';
    document.getElementById('dr-date-from').value = '';
    document.getElementById('dr-date-to').value   = '';
    loadDonations(1);
}

function loadDonations() {
    const keyword  = (document.getElementById('dr-keyword')   || {}).value || '';
    const source   = (document.getElementById('dr-source')    || {}).value || '';
    const dateFrom = (document.getElementById('dr-date-from') || {}).value || '';
    const dateTo   = (document.getElementById('dr-date-to')   || {}).value || '';

    let params = [];
    if (keyword)  params.push('keyword='  + encodeURIComponent(keyword));
    if (source)   params.push('source='   + encodeURIComponent(source));
    if (dateFrom) params.push('date_from=' + encodeURIComponent(dateFrom));
    if (dateTo)   params.push('date_to='   + encodeURIComponent(dateTo));

    const qs  = params.length ? '?' + params.join('&') : '';
    const url = 'api/donations.php' + qs;

    fetch(url)
        .then(r=>r.json()).then(d=>{
            if (!d.success) return;

            // 汇总条：始终显示
            const summary = document.getElementById('dr-summary');
            if (summary) {
                const hasFilter = keyword || source || dateFrom || dateTo;
                const label = hasFilter ? '🔍 搜索结果：' : '📊 全部记录：';
                summary.style.display = 'flex';
                summary.style.justifyContent = 'space-between';
                summary.style.alignItems = 'center';
                summary.innerHTML = '<span>' + label + '共 <b>' + d.total_count + '</b> 笔</span>'
                    + '<span>合计 <b style="font-size:1.05rem;color:#dc2626;">¥' + parseFloat(d.total_amount||0).toFixed(2) + '</b></span>';
            }

            // 按日期分组
            let html = '';
            let lastDate = '';
            d.data.forEach(item=>{
                const day = (item.donate_date || '').substring(0, 10);
                if (day !== lastDate) {
                    lastDate = day;
                    html += '<tr><td colspan="8" style="background:#f1f5f9;color:#64748b;font-size:0.82rem;font-weight:600;padding:6px 10px;">' + day + '</td></tr>';
                }
                let tr = '<tr data-id="' + item.id + '">'
                    + '<td><small style="color:#999;font-size:0.78rem">' + (item.created_at||'').substring(0,19) + '</small></td>'
                    + '<td style="font-weight:600">' + item.donor_name + '</td>'
                    + '<td style="color:#dc2626;font-weight:700;font-size:1.05rem;">¥' + parseFloat(item.amount).toFixed(2) + '</td>'
                    + '<td><span style="background:#eff6ff;color:#2563eb;padding:2px 8px;border-radius:8px;font-size:0.8rem;">' + (item.source||'') + '</span></td>'
                    + '<td>' + (item.payment_method||'') + '</td>'
                    + '<td style="color:#6b7280;font-size:0.88rem;">' + (item.note||'') + '</td>'
                    + '<td><span style="color:#4f46e5;font-weight:600;">' + (item.created_by_name||'未知') + '</span></td>';
                if (IS_ADMIN) {
                    tr += '<td>'
                        + '<button class="btn btn-primary btn-sm" onclick="editDonation(' + item.id + ')" style="margin-right:4px;">修改</button>'
                        + '<button class="btn btn-danger btn-sm" onclick="requestDeleteDonation(' + item.id + ')">删除</button>'
                        + '</td>';
                } else {
                    tr += '<td>'
                        + '<button class="btn btn-primary btn-sm" onclick="requestEditDonation(' + item.id + ')" style="margin-right:4px;">申请修改</button>'
                        + '<button class="btn btn-danger btn-sm" onclick="requestDeleteDonation(' + item.id + ')">申请删除</button>'
                        + '</td>';
                }
                tr += '</tr>';
                html += tr;
            });
            if (!html) html = '<tr><td colspan="8" style="text-align:center;color:#9ca3af;padding:24px;">暂无记录</td></tr>';
            document.getElementById('donation-list').innerHTML = html;
        });
}

// 申请修改捐款（普通用户）
function requestEditDonation(id) {
    // 先获取原数据
    fetch('api/donations.php?id=' + id)
        .then(r=>r.json())
        .then(d=>{
            if (!d.success) {
                alert('获取捐款记录失败');
                return;
            }
            
            const oldData = d.data;
            const newAmount = prompt('请输入新的金额（原金额：¥' + parseFloat(oldData.amount).toFixed(2) + '）：', oldData.amount);
            if (!newAmount || newAmount <= 0) return;
            
            const newDonorName = prompt('请输入新的捐款人姓名（原姓名：' + oldData.donor_name + '）：', oldData.donor_name);
            if (!newDonorName) return;
            
            fetch('api/audit.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action_type: 'edit',
                    target_type: 'donation',
                    target_id: id,
                    new_data: {
                        amount: parseFloat(newAmount),
                        donor_name: newDonorName
                    }
                })
            }).then(r=>r.json()).then(d=>{
                alert(d.msg);
                if (d.success) loadDonations();
            });
        });
}

// 管理员直接修改捐款
function editDonation(id) {
    const newAmount = prompt('请输入新的金额：');
    if (!newAmount || newAmount <= 0) return;
    const newDonorName = prompt('请输入新的捐款人姓名：');
    if (!newDonorName) return;

    if (!confirm('管理员直接修改，无需审核？')) return;

    fetch('api/donations.php', {
        method: 'PUT',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            id: id,
            amount: parseFloat(newAmount),
            donor_name: newDonorName
        })
    }).then(r=>r.json()).then(d=>{
        alert(d.msg);
        if (d.success) loadDonations();
    });
}

// 申请删除捐款
function requestDeleteDonation(id) {
    if (!confirm('确定申请删除这条捐款记录？')) return;
    
    fetch('api/audit.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            action_type: 'delete',
            target_type: 'donation',
            target_id: id
        })
    }).then(r=>r.json()).then(d=>{
        alert(d.msg);
        if (d.success) loadDonations();
    });
}

// ===== 捐款排行 =====
function loadRank() {
    fetch('api/donations.php?rank=1')
        .then(r=>r.json()).then(d=>{
            if (!d.success) {
                document.getElementById('rank-list').innerHTML = '<tr><td colspan="4" style="text-align:center;color:#ef4444;padding:20px;">加载失败</td></tr>';
                return;
            }
            if (!d.data || d.data.length === 0) {
                document.getElementById('rank-list').innerHTML = '<tr><td colspan="4" style="text-align:center;color:#6b7280;padding:20px;">暂无捐款记录</td></tr>';
                return;
            }
            let html = '', rank = 0;
            d.data.forEach(item=>{
                rank++;
                const cls = rank<=3 ? 'rank-'+rank : 'rank-other';
                html += '<tr>'
                    + '<td><span class="rank-badge ' + cls + '">' + rank + '</span></td>'
                    + '<td style="font-weight:600">' + (item.donor_name || '未知') + '</td>'
                    + '<td style="color:#dc2626;font-weight:800;font-size:1.1rem;">¥' + parseFloat(item.total_amount || 0).toFixed(2) + '</td>'
                    + '<td><span style="background:#f3f4f6;padding:2px 8px;border-radius:10px;font-size:0.8rem;">' + (item.donate_count || 0) + ' 次</span></td>'
                    + '</tr>';
            });
            document.getElementById('rank-list').innerHTML = html;
        })
        .catch(err => {
            console.error('加载排行榜失败:', err);
            document.getElementById('rank-list').innerHTML = '<tr><td colspan="4" style="text-align:center;color:#ef4444;padding:20px;">网络错误，请刷新重试</td></tr>';
        });
}

// ===== 钱包功能 =====
function loadWallet() {
    fetch('api/wallet.php?action=balances')
        .then(r=>r.json()).then(d=>{
            if (!d.success) return;
            let total = 0;
            d.data.forEach(w=>{
                if (w.person_name === CURRENT_NAME) MY_KEY = w.person_key;
                total += parseFloat(w.balance);
                // 填充个人余额
                if (w.person_name === CURRENT_NAME) {
                    const el = document.getElementById('wallet-my-balance');
                    if (el) el.textContent = '¥' + parseFloat(w.balance).toFixed(2);
                }
            });
            document.getElementById('wallet-total-balance').textContent = total.toFixed(2);
            let opt = '';
            d.data.forEach(w=>{
                if (w.person_name !== CURRENT_NAME) {
                    opt += '<option value="' + w.person_key + '">' + w.person_name + '</option>';
                }
            });
            const select = document.getElementById('w-transfer-to');
            select.innerHTML = opt;
            // 默认首选二哥
            if ([...select.options].some(o => o.value === 'second_brother')) {
                select.value = 'second_brother';
            }
            // MY_KEY 已就绪，加载交易记录
            loadWalletTx();
        });
}
function loadWalletTx() {
    if (!MY_KEY) return;
    fetch('api/wallet.php?action=transactions&person_key='+encodeURIComponent(MY_KEY))
        .then(r=>r.json()).then(d=>{
            if (!d.success) return;
            let html = '';
            d.data.forEach(tx=>{
                const typeMap = {'receive':'入账','expense':'支出','transfer':'转出'};
                const typeColor = {'receive':'#16a34a','expense':'#dc2626','transfer':'#2563eb'};
                const photo = tx.image_path ? '<a href="' + tx.image_path + '" target="_blank" style="color:#4f46e5;">📷 查看</a>' : '';
                
                // 检查是否有审核记录
                const isAuditing = tx.audit_status ? ' <span style="color:#f59e0b;font-size:0.75rem;">(审核中)</span>' : '';
                const isDeleted = tx.audit_status === 'approved' && tx.audit_action === 'delete' ? ' style="text-decoration:line-through;color:#9ca3af;"' : '';
                
                html += '<tr' + isDeleted + '>'
                    + '<td><small>' + (tx.created_at||'') + '</small>' + isAuditing + '</td>'
                    + '<td style="color:' + (typeColor[tx.trans_type]||'#333') + ';font-weight:600">' + (typeMap[tx.trans_type]||tx.trans_type) + '</td>'
                    + '<td style="font-weight:700;font-size:1.05rem;">¥' + parseFloat(tx.amount).toFixed(2) + '</td>'
                    + '<td style="font-weight:700;color:#4f46e5;font-size:1.05rem;">¥' + parseFloat(tx.balance_after||0).toFixed(2) + '</td>'
                    + '<td>' + (tx.description||'') + (tx.related_name?'←'+tx.related_name:'') + '</td>'
                    + '<td>' + photo + '</td>';
                
                // 添加操作按钮（撤回/修改金额）
                if (!tx.audit_status || tx.audit_status === 'rejected') {
                    html += '<td>';
                    if (IS_ADMIN) {
                        html += '<button class="btn btn-warning btn-sm" onclick="adminRevertTx(' + tx.id + ')" style="margin-right:4px;">撤回</button>'
                             +  '<button class="btn btn-primary btn-sm" onclick="adminEditTxAmount(' + tx.id + ',' + tx.amount + ')">改金额</button>';
                    } else {
                        html += '<button class="btn btn-warning btn-sm" onclick="requestRevertTx(' + tx.id + ')" style="margin-right:4px;">申请撤回</button>'
                             +  '<button class="btn btn-primary btn-sm" onclick="requestEditTxAmount(' + tx.id + ',' + tx.amount + ')">申请改金额</button>';
                    }
                    html += '</td>';
                }
                
                html += '</tr>';
            });
            document.getElementById('wallet-tx-list').innerHTML = html;
        });
}

// 申请撤回交易（普通用户）
function requestRevertTx(txId) {
    if (!confirm('确定申请撤回这笔交易？')) return;
    
    fetch('api/audit.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            action_type: 'revert',
            target_type: 'transaction',
            target_id: txId
        })
    }).then(r=>r.json()).then(d=>{
        alert(d.msg);
        if (d.success) loadWalletTx();
    });
}

// 申请修改交易金额（普通用户）
function requestEditTxAmount(txId, oldAmount) {
    const newAmount = prompt('请输入新的金额（原金额：¥' + parseFloat(oldAmount).toFixed(2) + '）：');
    if (!newAmount || newAmount <= 0) return;
    
    fetch('api/audit.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            action_type: 'edit_amount',
            target_type: 'transaction',
            target_id: txId,
            new_data: { amount: parseFloat(newAmount) }
        })
    }).then(r=>r.json()).then(d=>{
        alert(d.msg);
        if (d.success) loadWalletTx();
    });
}

// 管理员直接撤回交易
function adminRevertTx(txId) {
    if (!confirm('管理员直接撤回，无需审核？')) return;
    
    fetch('api/wallet.php?action=revert&id=' + txId, {
        method: 'POST'
    }).then(r=>r.json()).then(d=>{
        alert(d.msg);
        if (d.success) { loadWalletTx(); loadWallet(); loadDashboard(); }
    });
}

// 管理员直接修改交易金额
function adminEditTxAmount(txId, oldAmount) {
    const newAmount = prompt('请输入新的金额（原金额：¥' + parseFloat(oldAmount).toFixed(2) + '）：');
    if (!newAmount || newAmount <= 0) return;
    
    if (!confirm('管理员直接修改，无需审核？')) return;
    
    fetch('api/wallet.php?action=edit_amount&id=' + txId, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ amount: parseFloat(newAmount) })
    }).then(r=>r.json()).then(d=>{
        alert(d.msg);
        if (d.success) { loadWalletTx(); loadWallet(); loadDashboard(); }
    });
}

// 消费
function walletExpense() {
    const amt = document.getElementById('w-expense-amt').value;
    if (!amt || amt <= 0) return alert('请输入有效金额');
    const fileInput = document.getElementById('w-expense-photo');
    if (fileInput.files.length === 0) {
        if (!confirm('未上传消费凭证，是否继续提交？')) return;
    }
    const fd = new FormData();
    fd.append('action', 'expense');
    fd.append('person_key', MY_KEY);
    fd.append('amount', amt);
    fd.append('description', document.getElementById('w-expense-desc').value.trim());
    // 读取分类
    let cat = document.getElementById('w-expense-cat').value;
    if (cat === '__custom__') cat = document.getElementById('w-expense-cat-custom').value.trim();
    if (cat) fd.append('category', cat);
    if (fileInput.files.length > 0) {
        fd.append('photo', fileInput.files[0]);
    }
    fetch('api/wallet.php', {method:'POST', body: fd})
        .then(r=>r.json()).then(d=>{
            alert(d.msg);
            if (d.success) { loadWallet(); loadDashboard(); }
        });
}

// 转账
function walletTransfer() {
    const toKey = document.getElementById('w-transfer-to').value;
    const amt    = document.getElementById('w-transfer-amt').value;
    if (!amt || amt <= 0) return alert('请输入有效金额');
    const fd = new FormData();
    fd.append('action', 'transfer');
    fd.append('from_key', MY_KEY);
    fd.append('to_key', toKey);
    fd.append('amount', amt);
    fd.append('description', document.getElementById('w-transfer-desc').value.trim());
    // 添加图片上传
    const fileInput = document.getElementById('w-transfer-photo');
    if (fileInput.files.length > 0) {
        fd.append('photo', fileInput.files[0]);
    }
    fetch('api/wallet.php', {method:'POST', body: fd})
        .then(r=>r.json()).then(d=>{
            alert(d.msg);
            if (d.success) {
                loadWallet();
                loadDashboard();
                // 清空图片
                document.getElementById('w-transfer-photo').value = '';
            }
        });
}

// ===== 数据管理（管理员）=====
function loadDataMgmt() {
    fetch('api/admin.php?action=list_backups')
        .then(r=>r.json()).then(d=>{
            const el = document.getElementById('backup-list');
            if (!d.success || !d.data || d.data.length === 0) {
                el.innerHTML = '<span style="color:#9ca3af;">暂无备份文件，请先创建备份</span>';
                document.getElementById('restore-area').style.display = 'none';
                return;
            }
            let html = '<div style="display:grid;gap:8px;">';
            const sel = document.getElementById('restore-file-select');
            sel.innerHTML = '<option value="">-- 选择备份文件 --</option>';
            d.data.forEach(b => {
                html += '<div style="display:flex;justify-content:space-between;align-items:center;padding:8px 12px;background:#fff;border-radius:8px;border:1px solid #e5e7eb;">'
                     + '<span>' + b.name + ' <span style="color:#9ca3af;font-size:11px;">(' + b.size + ', ' + b.time + ')</span></span>'
                     + '<a href="' + b.url + '" download class="btn btn-sm" style="background:#d1fae5;color:#065f46;text-decoration:none;padding:4px 10px;border-radius:6px;font-size:12px;">下载</a>'
                     + '</div>';
                const opt = document.createElement('option');
                opt.value = b.name;
                opt.textContent = b.name + ' (' + b.time + ')';
                sel.appendChild(opt);
            });
            html += '</div>';
            el.innerHTML = html;
            document.getElementById('restore-area').style.display = 'flex';
        })
        .catch(e=>{ console.error('[loadDataMgmt]', e); });
}

function adminBackup() {
    const btn = event.target;
    btn.disabled = true;
    btn.textContent = '备份中...';
    fetch('api/admin.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'backup'})
    }).then(r=>r.json()).then(d=>{
        btn.disabled = false;
        btn.textContent = '💾 创建备份';
        document.getElementById('backup-status').textContent = d.msg || '完成';
        if (d.success) {
            setTimeout(()=>{ loadDataMgmt(); document.getElementById('backup-status').textContent = ''; }, 2000);
            // 自动刷新备份列表
            loadBackupList();
        } else {
            alert('备份失败：' + (d.msg || '未知错误'));
        }
    }).catch(e => {
        console.error('[backup]', e);
        btn.disabled = false;
        btn.textContent = '💾 创建备份';
        alert('备份请求失败');
    });
}

function adminResetWallets() {
    const pwd = document.getElementById('dm-password').value;
    if (!pwd) return alert('请输入管理员密码！');
    if (!confirm('确定将所有钱包余额清零？此操作不可撤销！')) return;
    fetch('api/admin.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'reset_wallets', password: pwd})
    }).then(r=>r.json()).then(d=>{
        alert(d.msg);
        if (d.success) { loadWallet(); loadDashboard(); }
    });
}

function adminClearData() {
    const pwd = document.getElementById('dm-password').value;
    if (!pwd) return alert('请输入管理员密码！');
    if (!confirm('⚠️ 严重警告：确定清空所有数据？\n捐款记录、交易记录、钱包余额、审核记录将全部删除！\n用户账号会保留。\n\n此操作不可撤销！')) return;
    if (!confirm('最后确认一次：真的要清空所有数据吗？')) return;
    fetch('api/admin.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'clear_data', password: pwd})
    }).then(r=>r.json()).then(d=>{
        alert(d.msg);
        if (d.success) location.reload();
    });
}

function adminRestore() {
    const filename = document.getElementById('restore-file-select').value;
    const pwd = document.getElementById('restore-password').value;
    if (!filename) return alert('请选择备份文件！');
    if (!pwd) return alert('请输入管理员密码！');
    if (!confirm('确定用备份文件 ' + filename + ' 还原数据库？\n当前数据将被覆盖！')) return;
    fetch('api/admin.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'restore', filename: filename, password: pwd})
    }).then(r=>r.json()).then(d=>{
        alert(d.msg || (d.success ? '还原成功' : '还原失败'));
        if (d.success) setTimeout(()=>location.reload(), 1000);
    }).catch(e => {
        console.error('[restore]', e);
        alert('还原请求失败');
    });
}

// ===== 审核管理（管理员）=====
function loadAuditList() {
    const status = document.getElementById('audit-filter') ? document.getElementById('audit-filter').value : 'pending';
    let url = 'api/audit.php?status=' + status;
    fetch(url)
        .then(r=>r.json()).then(d=>{
            if (!d.success) return;
            let html = '';
            if (!d.data || d.data.length === 0) {
                html = '<tr><td colspan="8" style="text-align:center;color:#6b7280;padding:20px;">暂无审核记录</td></tr>';
                document.getElementById('audit-list').innerHTML = html;
                return;
            }
            d.data.forEach(audit=>{
                const statusColor = {
                    'pending': '#f59e0b',
                    'approved': '#16a34a',
                    'rejected': '#dc2626'
                };
                const statusText = {
                    'pending': '待审核',
                    'approved': '已通过',
                    'rejected': '已拒绝'
                };
                const actionTypeMap = {
                    'edit': '修改捐款',
                    'delete': '删除捐款',
                    'revert': '撤回交易',
                    'edit_amount': '修改金额'
                };
                
                // 格式化旧数据/新数据
                let oldStr = '-', newStr = '-';
                if (audit.old_data) {
                    const od = audit.old_data;
                    oldStr = (od.donor_name ? '捐款人：' + od.donor_name + '<br>' : '')
                           + (od.amount !== undefined ? '金额：¥' + parseFloat(od.amount).toFixed(2) : '')
                           || JSON.stringify(od);
                }
                if (audit.new_data) {
                    const nd = audit.new_data;
                    newStr = (nd.donor_name ? '捐款人：' + nd.donor_name + '<br>' : '')
                           + (nd.amount !== undefined ? '金额：¥' + parseFloat(nd.amount).toFixed(2) : '')
                           || JSON.stringify(nd);
                }
                
                html += '<tr>'
                    + '<td><small>' + (audit.created_at||'') + '</small></td>'
                    + '<td>' + (audit.requester_name||audit.username||'未知') + '</td>'
                    + '<td><span style="background:#eff6ff;padding:2px 8px;border-radius:8px;font-size:0.8rem;color:#2563eb">' + (actionTypeMap[audit.action_type]||audit.action_type||'') + '</span></td>'
                    + '<td>' + (audit.target_type === 'donation' ? '捐款记录' : '交易记录') + ' #' + (audit.target_id||'') + '</td>'
                    + '<td><small>' + oldStr + '</small></td>'
                    + '<td><small>' + newStr + '</small></td>'
                    + '<td><span style="color:' + (statusColor[audit.status]||'#333') + ';font-weight:600;">' + (statusText[audit.status]||audit.status) + '</span></td>'
                    + '<td>';
                
                if (audit.status === 'pending') {
                    html += '<button class="btn btn-success btn-sm" onclick="approveAudit(' + audit.id + ')" style="margin-right:4px;">通过</button>'
                         + '<button class="btn btn-danger btn-sm" onclick="rejectAudit(' + audit.id + ')">拒绝</button>';
                }
                
                html += '</td></tr>';
            });
            document.getElementById('audit-list').innerHTML = html;
        });
}

function approveAudit(auditId) {
    if (!confirm('确定通过这条审核？')) return;
    
    fetch('api/audit.php?id=' + auditId + '&action=approve', {
        method: 'POST'
    }).then(r=>r.json()).then(d=>{
        alert(d.msg);
        if (d.success) {
            loadAuditList();
            loadDonations();
            loadWalletTx();
            loadWallet();
            loadDashboard();
        }
    });
}

function rejectAudit(auditId) {
    if (!confirm('确定拒绝这条审核？')) return;
    
    fetch('api/audit.php?id=' + auditId + '&action=reject', {
        method: 'POST'
    }).then(r=>r.json()).then(d=>{
        alert(d.msg);
        if (d.success) loadAuditList();
    });
}

// ===== 导出 CSV =====
function exportCSV() {
    window.open('api/export.php?type=donations','_blank');
}

// ===== 统计汇总 =====
document.getElementById('form-stats').addEventListener('submit', function(e) {
    e.preventDefault();
    const start = document.getElementById('stat-start').value;
    const end   = document.getElementById('stat-end').value;
    if (!start || !end) return;
    fetch('api/stats.php?start=' + encodeURIComponent(start) + '&end=' + encodeURIComponent(end))
        .then(r=>r.json()).then(d=>{
            if (!d.success) return;
            const data = d.data;
            let html = '';
            html += '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;margin-bottom:14px;">';
            html += '<div class="stat-card"><h4>总笔数</h4><div class="num">' + (data.total_count||0) + '</div></div>';
            html += '<div class="stat-card"><h4>总金额</h4><div class="num">¥' + parseFloat(data.total_amount||0).toFixed(2) + '</div></div>';
            html += '</div>';
            if (data.by_source && data.by_source.length) {
                html += '<h4 style="margin-bottom:8px;color:#374151;">📌 按来源统计</h4><table><thead><tr><th>来源</th><th>金额</th><th>笔数</th></tr></thead><tbody>';
                data.by_source.forEach(s=>{
                    html += '<tr><td>' + s.source + '</td><td style="color:#dc2626;font-weight:600">¥' + parseFloat(s.total).toFixed(2) + '</td><td>' + s.cnt + '</td></tr>';
                });
                html += '</tbody></table>';
            }
            if (data.by_pay && data.by_pay.length) {
                html += '<h4 style="margin:14px 0 8px;color:#374151;">💳 按支付方式统计</h4><table><thead><tr><th>方式</th><th>金额</th><th>笔数</th></tr></thead><tbody>';
                data.by_pay.forEach(s=>{
                    html += '<tr><td>' + s.payment_method + '</td><td style="color:#dc2626;font-weight:600">¥' + parseFloat(s.total).toFixed(2) + '</td><td>' + s.cnt + '</td></tr>';
                });
                html += '</tbody></table>';
            }
            document.getElementById('stats-result').innerHTML = html;
        });
});

// ===== 批量导入 =====
function doImport() {
    const fileInput = document.getElementById('import-file');
    if (!fileInput.files.length) return;
    const fd = new FormData();
    fd.append('file', fileInput.files[0]);
    fetch('api/import.php', {method:'POST', body: fd})
        .then(r=>r.json()).then(d=>{
            const msg = document.getElementById('import-msg');
            if (d.success) {
                msg.innerHTML = '<div class="alert alert-success">✅ ' + d.msg + '</div>';
                loadDonations(); loadDashboard();
            } else {
                msg.innerHTML = '<div class="alert alert-danger">❌ ' + (d.msg||'导入失败') + '</div>';
            }
        });
}

// ===== 用户管理（管理员）=====
<?php if ($currentRole === 'admin'): ?>

function loadUsers() {
    fetch('api/users.php').then(r=>r.json()).then(d=>{
        if (!d.success) return;
        let html = '';
        d.data.forEach(u=>{
            html += '<tr>'
                + '<td>' + u.username + '</td>'
                + '<td>' + u.brother_name + '</td>'
                + '<td><span style="background:' + (u.role==='admin'?'#fef2f2':'#f0fdf4') + ';padding:2px 8px;border-radius:8px;font-size:0.8rem;color:' + (u.role==='admin'?'#dc2626':'#16a34a') + '">' + (u.role==='admin'?'管理员':'兄弟') + '</span></td>'
                + '<td>'
                + '<button class="btn btn-primary btn-sm" onclick="editUser(' + u.id + ')">编辑</button>'
                + '<button class="btn btn-danger btn-sm" onclick="deleteUser(' + u.id + ',\'' + u.brother_name.replace("'", "\\'") + '\')" style="margin-left:4px">删除</button>'
                + '</td>'
                + '</tr>';
        });
        document.getElementById('users-list').innerHTML = html;
    });
}

function showAddUser() {
    const name   = prompt('用户名：');
    if (!name) return;
    const bname  = prompt('名称（如：大哥）：');
    if (!bname) return;
    const pass   = prompt('密码：');
    if (!pass) return;
    const role   = confirm('是否为管理员？') ? 'admin' : 'brother';
    fetch('api/users.php', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({username:name, password:pass, role:role, brother_name:bname})
    }).then(r=>r.json()).then(d=>{ alert(d.msg); if(d.success) loadUsers(); });
}

function editUser(id) {
    fetch('api/users.php').then(r=>r.json()).then(d=>{
        const u = d.data.find(x=>x.id==id);
        if (!u) return;
        const name  = prompt('用户名：', u.username);
        if (name===null) return;
        const bname = prompt('名称：', u.brother_name);
        if (bname===null) return;
        const pass  = prompt('新密码（留空不修改）：');
        const role  = confirm('是否为管理员？') ? 'admin' : 'brother';
        const body  = {id:id, username:name, role:role, brother_name:bname};
        if (pass) body.password = pass;
        fetch('api/users.php', {
            method:'POST',
            headers:{'Content-Type':'application/json'},
            body: JSON.stringify(body)
        }).then(r=>r.json()).then(d=>{ alert(d.msg); if(d.success) loadUsers(); });
    });
}

function deleteUser(id, name) {
    if (!confirm('确定删除「'+name+'」？此操作不可恢复！')) return;
    fetch('api/users.php?id='+id, {method:'DELETE'}).then(r=>r.json()).then(d=>{ alert(d.msg); if(d.success) loadUsers(); });
}

// ===== 操作日志（管理员）=====
function loadLogs() {
    fetch('api/logs.php').then(r=>r.json()).then(d=>{
        if (!d.success) return;
        let html = '';
        d.data.forEach(log=>{
            const ipDisplay = (log.ip_location && log.ip_location !== '') ? log.ip_location : (log.ip || '');
            html += '<tr>'
                + '<td><small>' + (log.created_at||'') + '</small></td>'
                + '<td>' + (log.username||'') + '（' + (log.brother_name||'') + '）</td>'
                + '<td><span style="background:#eff6ff;padding:2px 8px;border-radius:8px;font-size:0.8rem;color:#2563eb">' + (log.action||'') + '</span></td>'
                + '<td style="font-size:0.85rem;color:#4b5563;">' + ipDisplay + '</td>'
                + '<td>' + (log.detail||'') + '</td>'
                + '</tr>';
        });
        document.getElementById('logs-list').innerHTML = html;
    });
}

<?php endif; ?>

// ===== 款项用途（支出记录） =====
// 注意：ER_CURRENT_PAGE 已在全局位置声明为 window.ER_CURRENT_PAGE

// 加载统计
function loadExpenseStats() {
    fetch('api/wallet.php?action=expense_stats')
        .then(r=>r.json()).then(d=>{
            if (!d.success) return;
            document.getElementById('er-stat-today').textContent   = '¥' + parseFloat(d.today_total).toFixed(2);
            document.getElementById('er-stat-current').textContent = '¥' + parseFloat(d.current_total).toFixed(2);
            document.getElementById('er-stat-last').textContent    = '¥' + parseFloat(d.last_total).toFixed(2);
        }).catch(e=>console.error('[expense_stats]', e));
}

// 设置本月/上月时间
function erSetMonth(type) {
    const now = new Date();
    let y, m;
    if (type === 'current') {
        y = now.getFullYear();
        m = now.getMonth() + 1;
    } else {
        // 上月
        if (now.getMonth() === 0) { y = now.getFullYear() - 1; m = 12; }
        else { y = now.getFullYear(); m = now.getMonth(); }
    }
    const start = y + '-' + String(m).padStart(2, '0') + '-01';
    // 计算月末
    const lastDay = new Date(y, m, 0).getDate();
    const end = y + '-' + String(m).padStart(2, '0') + '-' + String(lastDay).padStart(2, '0');
    document.getElementById('er-start-date').value = start;
    document.getElementById('er-end-date').value   = end;
}

// 清空时间
function erClearDate() {
    document.getElementById('er-start-date').value = '';
    document.getElementById('er-end-date').value   = '';
}

// 全局变量：当前页码（必须在所有函数之前声明）
window.ER_CURRENT_PAGE = 1;

function loadExpenseRecords(page) {
    page = page || 1;
    window.ER_CURRENT_PAGE = page;
    const search     = document.getElementById('er-search').value.trim();
    const category   = document.getElementById('er-category-filter').value;
    const startDate  = document.getElementById('er-start-date').value;
    const endDate    = document.getElementById('er-end-date').value;
    let url = 'api/wallet.php?action=expense_records&page=' + page;
    if (search)     url += '&search=' + encodeURIComponent(search);
    if (category)   url += '&category=' + encodeURIComponent(category);
    if (startDate)  url += '&start_date=' + encodeURIComponent(startDate);
    if (endDate)    url += '&end_date=' + encodeURIComponent(endDate);

    fetch(url)
        .then(r=>r.json()).then(d=>{
            if (!d.success) { alert(d.msg||'加载失败'); return; }

            // 更新筛选合计
            if (d.filtered_total !== undefined) {
                document.getElementById('er-stat-filtered').textContent = '¥' + parseFloat(d.filtered_total).toFixed(2);
            }

            // 填充分类下拉（首次加载时）
            const catSelect = document.getElementById('er-category-filter');
            if (d.categories && catSelect.options.length <= 1) {
                d.categories.forEach(c => {
                    const opt = document.createElement('option');
                    opt.value = c;
                    opt.textContent = c;
                    catSelect.appendChild(opt);
                });
            }

            // 填充表格（金额在前，时间在后）
            const tbody = document.getElementById('er-tbody');
            if (!d.data || d.data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#999;">暂无支出记录</td></tr>';
            } else {
                let html = '';
                d.data.forEach(r => {
                    // 消费凭证缩略图
                    let凭证 = '无';
                    if (r.image_path) {
                        凭证 = '<a href="' + r.image_path + '" target="_blank">'
                             + '<img src="' + r.image_path + '" style="width:48px;height:48px;object-fit:cover;border-radius:6px;border:1px solid #e5e7eb;cursor:pointer;" onerror="this.parentNode.textContent=\'加载失败\';">'
                             + '</a>';
                    }
                    html += '<tr>'
                        + '<td style="font-weight:600;">' + (r.person_name||'') + '</td>'
                        + '<td><span style="background:#e0e7ff;color:#3730a3;padding:2px 8px;border-radius:8px;font-size:0.8rem;">' + (r.category||'未分类') + '</span></td>'
                        + '<td style="color:#dc2626;font-weight:700;font-size:1.05rem;">¥' + parseFloat(r.amount).toFixed(2) + '</td>'
                        + '<td>' + (r.description||'') + '</td>'
                        + '<td>' + 凭证 + '</td>'
                        + '<td><small style="color:#888;">' + (r.created_at||'') + '</small></td>'
                        + '</tr>';
                });
                tbody.innerHTML = html;
            }

            // 分页
            const pg = document.getElementById('er-pagination');
            if (d.total_pages > 1) {
                let pghtml = '';
                let start = Math.max(1, page - 2);
                let end   = Math.min(d.total_pages, page + 2);
                if (start > 1) pghtml += '<a href="javascript:void(0)" onclick="loadExpenseRecords(1)" style="margin:0 3px;color:#4f46e5;">1</a>...';
                for (let i = start; i <= end; i++) {
                    if (i === page) {
                        pghtml += '<span style="margin:0 4px;font-weight:700;color:#4f46e5;">' + i + '</span>';
                    } else {
                        pghtml += '<a href="javascript:void(0)" onclick="loadExpenseRecords(' + i + ')" style="margin:0 4px;color:#555;">' + i + '</a>';
                    }
                }
                if (end < d.total_pages) pghtml += '...<a href="javascript:void(0)" onclick="loadExpenseRecords(' + d.total_pages + ')" style="margin:0 3px;color:#4f46e5;">' + d.total_pages + '</a>';
                pg.innerHTML = pghtml;
            } else {
                pg.innerHTML = '';
            }
        })
        .catch(e => { console.error('[expense_records]', e); alert('加载失败'); });
}
</script>

<!-- 证件资料弹窗 -->
<div class="modal-overlay" id="doc-form-overlay" style="display:none;">
  <div class="modal" style="max-width:520px;border-radius:16px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,0.15);">
    <div style="background:linear-gradient(135deg,#667eea,#764ba2);padding:20px 24px;display:flex;align-items:center;justify-content:space-between;">
      <h3 id="doc-form-title" style="margin:0;color:#fff;font-size:1.15rem;">📁 新增证件</h3>
      <button onclick="closeDocumentForm()" style="background:none;border:none;color:#fff;font-size:22px;cursor:pointer;line-height:1;padding:0;">✕</button>
    </div>
    <div style="padding:24px;">
    <input type="hidden" id="doc-form-id">
    <div style="margin-bottom:14px">
      <label style="display:block;font-weight:600;font-size:0.88rem;color:#374151;margin-bottom:6px;">📋 证件名称 <span style="color:#ef4444;">*</span></label>
      <input type="text" id="doc-form-title-input" placeholder="如：身份证、户口本、房产证等" style="width:100%;padding:10px 12px;border:2px solid #e5e7eb;border-radius:10px;box-sizing:border-box;font-size:0.95rem;transition:border-color 0.2s;" onfocus="this.style.borderColor='#667eea'" onblur="this.style.borderColor='#e5e7eb'">
    </div>
    <div style="margin-bottom:14px">
      <label style="display:block;font-weight:600;font-size:0.88rem;color:#374151;margin-bottom:6px;">📎 文件上传 <span style="font-weight:normal;font-size:0.78rem;color:#9ca3af;">(支持多选，图片/文档，单个≤10MB)</span></label>
      <!-- 多文件拖拽上传区 -->
      <div id="doc-upload-area" style="border:2px dashed #a78bfa;border-radius:12px;padding:20px;text-align:center;background:#f5f3ff;cursor:pointer;transition:all 0.2s;"
           onclick="document.getElementById('doc-form-file').click()"
           ondragover="event.preventDefault();this.style.borderColor='#7c3aed';this.style.background='#ede9fe'"
           ondragleave="this.style.borderColor='#a78bfa';this.style.background='#f5f3ff'"
           ondrop="event.preventDefault();handleDocDrop(event)">
        <div style="font-size:1.8rem;margin-bottom:4px;">📁</div>
        <div style="color:#7c3aed;font-size:0.9rem;font-weight:500;">点击或拖拽文件到此处</div>
        <div style="color:#9ca3af;font-size:0.78rem;margin-top:4px;">支持 JPG / PNG / PDF / DOC 等格式</div>
      </div>
      <input type="file" id="doc-form-file" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip,.rar" multiple style="display:none;" onchange="previewDocFiles(this)">
      <!-- 文件预览列表 -->
      <div id="doc-file-preview-list" style="margin-top:10px;display:flex;flex-wrap:wrap;gap:8px;"></div>
    </div>
    <div style="margin-bottom:18px">
      <label style="display:block;font-weight:600;font-size:0.88rem;color:#374151;margin-bottom:6px;">👤 保管人</label>
      <select id="doc-form-keeper" style="width:100%;padding:10px 12px;border:2px solid #e5e7eb;border-radius:10px;box-sizing:border-box;font-size:0.95rem;background:#fff;cursor:pointer;"></select>
    </div>
    <button onclick="saveDocument()" style="background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;border:none;padding:12px 28px;border-radius:10px;cursor:pointer;width:100%;font-size:1rem;font-weight:600;letter-spacing:1px;">💾 保存证件</button>
    </div>
  </div>
</div>

<!-- 备忘录弹窗 -->
<div class="modal-overlay" id="memo-form-overlay" style="display:none;">
  <div class="modal" style="max-width:520px;border-radius:16px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,0.15);">
    <div style="background:linear-gradient(135deg,#f093fb,#f5576c);padding:20px 24px;display:flex;align-items:center;justify-content:space-between;">
      <h3 id="memo-form-title" style="margin:0;color:#fff;font-size:1.15rem;">📝 新增备忘</h3>
      <button onclick="closeMemoForm()" style="background:none;border:none;color:#fff;font-size:22px;cursor:pointer;line-height:1;padding:0;">✕</button>
    </div>
    <div style="padding:24px;">
    <input type="hidden" id="memo-form-id">
    <div style="margin-bottom:14px">
      <label style="display:block;font-weight:600;font-size:0.88rem;color:#374151;margin-bottom:6px;">📄 记录事项 <span style="color:#ef4444;">*</span></label>
      <textarea id="memo-form-content" rows="4" placeholder="请输入记录事项..." style="width:100%;padding:10px 12px;border:2px solid #e5e7eb;border-radius:10px;box-sizing:border-box;resize:vertical;font-size:0.95rem;font-family:inherit;transition:border-color 0.2s;" onfocus="this.style.borderColor='#f093fb'" onblur="this.style.borderColor='#e5e7eb'"></textarea>
    </div>
    <div style="margin-bottom:18px">
      <label style="display:block;font-weight:600;font-size:0.88rem;color:#374151;margin-bottom:6px;">🕐 时间 <span style="color:#ef4444;">*</span></label>
      <input type="datetime-local" id="memo-form-time" style="width:100%;padding:10px 12px;border:2px solid #e5e7eb;border-radius:10px;box-sizing:border-box;font-size:0.95rem;transition:border-color 0.2s;" onfocus="this.style.borderColor='#f093fb'" onblur="this.style.borderColor='#e5e7eb'">
    </div>
    <button onclick="saveMemo()" style="background:linear-gradient(135deg,#f093fb,#f5576c);color:#fff;border:none;padding:12px 28px;border-radius:10px;cursor:pointer;width:100%;font-size:1rem;font-weight:600;letter-spacing:1px;">💾 保存备忘</button>
    </div>
  </div>
</div>

<!-- 通讯录弹窗 -->
<div class="modal-overlay" id="contact-form-overlay" style="display:none;">
  <div class="modal" style="max-width:520px;border-radius:16px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,0.15);">
    <div style="background:linear-gradient(135deg,#11998e,#38ef7d);padding:20px 24px;display:flex;align-items:center;justify-content:space-between;">
      <h3 id="contact-form-title" style="margin:0;color:#fff;font-size:1.15rem;">📞 新增联系人</h3>
      <button onclick="closeContactForm()" style="background:none;border:none;color:#fff;font-size:22px;cursor:pointer;line-height:1;padding:0;">✕</button>
    </div>
    <div style="padding:24px;">
    <input type="hidden" id="contact-form-id">
    <div style="margin-bottom:14px;text-align:center;">
      <label style="display:block;font-weight:600;font-size:0.88rem;color:#374151;margin-bottom:8px;">🖼️ 头像</label>
      <img id="contact-avatar-preview" src="" style="width:72px;height:72px;border-radius:50%;object-fit:cover;display:none;margin:0 auto 10px;border:3px solid #e5e7eb;">
      <input type="file" id="contact-form-avatar" accept="image/*" onchange="if(this.files[0]){var r=new FileReader();r.onload=function(e){var p=document.getElementById('contact-avatar-preview');p.src=e.target.result;p.style.display='block';};r.readAsDataURL(this.files[0])}" style="width:100%;padding:8px;border:2px dashed #d1d5db;border-radius:10px;box-sizing:border-box;background:#fafafa;cursor:pointer;font-size:0.9rem;">
    </div>
    <div style="margin-bottom:14px">
      <label style="display:block;font-weight:600;font-size:0.88rem;color:#374151;margin-bottom:6px;">👤 联系人姓名 <span style="color:#ef4444;">*</span></label>
      <input type="text" id="contact-form-name" placeholder="请输入联系人姓名" style="width:100%;padding:10px 12px;border:2px solid #e5e7eb;border-radius:10px;box-sizing:border-box;font-size:0.95rem;transition:border-color 0.2s;" onfocus="this.style.borderColor='#11998e'" onblur="this.style.borderColor='#e5e7eb'">
    </div>
    <div style="margin-bottom:14px">
      <label style="display:block;font-weight:600;font-size:0.88rem;color:#374151;margin-bottom:6px;">💼 职务</label>
      <input type="text" id="contact-form-position" placeholder="如：爸爸、妈妈、老师等" style="width:100%;padding:10px 12px;border:2px solid #e5e7eb;border-radius:10px;box-sizing:border-box;font-size:0.95rem;" onfocus="this.style.borderColor='#11998e'" onblur="this.style.borderColor='#e5e7eb'">
    </div>
    <div style="margin-bottom:18px">
      <label style="display:block;font-weight:600;font-size:0.88rem;color:#374151;margin-bottom:6px;">📱 电话</label>
      <input type="tel" id="contact-form-phone" placeholder="请输入电话号码" style="width:100%;padding:10px 12px;border:2px solid #e5e7eb;border-radius:10px;box-sizing:border-box;font-size:0.95rem;" onfocus="this.style.borderColor='#11998e'" onblur="this.style.borderColor='#e5e7eb'">
    </div>
    <button onclick="saveContact()" style="background:linear-gradient(135deg,#11998e,#38ef7d);color:#fff;border:none;padding:12px 28px;border-radius:10px;cursor:pointer;width:100%;font-size:1rem;font-weight:600;letter-spacing:1px;">💾 保存联系人</button>
    </div>
  </div>
</div>

<script>
// ===== 消息弹窗功能 =====
function checkUnreadMessages() {
    fetch('api/notifications.php?action=get_unread')
        .then(r=>r.json()).then(d=>{
            if (d.success && d.data && d.data.length > 0) {
                showMessageModal(d.data);
            }
        }).catch(e=>console.error('检查未读消息失败', e));
}
function showMessageModal(messages) {
    // 移除已有的弹窗（防止重复）
    var old = document.getElementById('message-modal-overlay');
    if (old) old.remove();

    // 创建遮罩层
    var overlay = document.createElement('div');
    overlay.id = 'message-modal-overlay';
    overlay.style.cssText = 'display:flex;position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.7);z-index:99999;justify-content:center;align-items:center;box-sizing:border-box;padding:16px;';

    // 创建内层面板
    var inner = document.createElement('div');
    inner.id = 'message-modal-inner';
    inner.style.cssText = 'background:#fff;border-radius:16px;width:92%;max-width:480px;max-height:85vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.35);';

    // 头部
    var header = document.createElement('div');
    header.style.cssText = 'background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;padding:16px 20px;display:flex;align-items:center;justify-content:space-between;border-radius:16px 16px 0 0;';
    header.innerHTML = '<h3 style="margin:0;font-size:1.05rem;">📢 新消息</h3>' +
                      '<span id="message-count" style="background:rgba(255,255,255,0.2);padding:2px 10px;border-radius:10px;font-size:0.85rem;">' + messages.length + ' 条新消息</span>';

    // 消息列表容器
    var list = document.createElement('div');
    list.id = 'message-list';
    list.style.cssText = 'padding:12px 20px;max-height:55vh;overflow-y:auto;';

    // 底部按钮区
    var footer = document.createElement('div');
    footer.style.cssText = 'padding:12px 20px 18px;display:flex;gap:10px;justify-content:flex-end;border-top:1px solid #f0f0f0;';
    footer.innerHTML = '<button onclick="ignoreMessageModal()" style="padding:8px 20px;border-radius:8px;border:1px solid #ddd;background:#f5f5f5;cursor:pointer;font-size:0.88rem;color:#555;">忽略</button>' +
                      '<button onclick="closeMessageModal()" style="padding:8px 20px;border-radius:8px;border:none;background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;cursor:pointer;font-size:0.88rem;font-weight:500;">已阅读</button>';

    inner.appendChild(header);
    inner.appendChild(list);
    inner.appendChild(footer);
    overlay.appendChild(inner);
    document.body.appendChild(overlay);

    // 填充消息列表
    var html = '';
    messages.forEach(m => {
        html += '<div class="message-item">' +
                '<div class="message-icon">' + m.icon + '</div>' +
                '<div class="message-content">' +
                '<div class="message-title">' + m.title + '</div>' +
                '<div class="message-text">' + m.content + '</div>' +
                '<div class="message-time">' + m.time + '</div>' +
                '</div>' +
                '</div>';
    });
    list.innerHTML = html;
}
function ignoreMessageModal() {
    var el = document.getElementById('message-modal-overlay');
    if (el) el.remove();
}
function closeMessageModal() {
    var el = document.getElementById('message-modal-overlay');
    if (el) el.remove();
    // 用 form-urlencoded，PHP 才能正确解析 $_POST['action']
    fetch('api/notifications.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=mark_read'
    }).then(r => r.json()).then(d => {
        if (d && d.success) console.log('已标记已读');
        else console.error('标记已读失败', d ? d.msg : '');
    }).catch(e => console.error('标记已读失败', e));
}

/* ===== 通用弹窗显示/隐藏（暴力 inline 样式，不依赖 CSS 类） ===== */
var _savedBodyScroll = 0;
function showOverlay(id) {
    var el = document.getElementById(id);
    if (!el) return;
    // 保存滚动位置并锁住 body
    _savedBodyScroll = window.scrollY || document.documentElement.scrollTop;
    document.body.style.position = 'fixed';
    document.body.style.top = -_savedBodyScroll + 'px';
    document.body.style.width = '100%';
    // 暴力设置所有关键样式为 inline（最高优先级，绕开所有 CSS 层叠问题）
    el.style.cssText = 'display:flex!important;position:fixed!important;top:0!important;left:0!important;width:100vw!important;height:100vh!important;background:rgba(0,0,0,0.7)!important;z-index:99999!important;justify-content:center!important;align-items:center!important;box-sizing:border-box!important;padding:16px!important;';
}
function hideOverlay(id) {
    var el = document.getElementById(id);
    if (el) el.style.display = 'none';
    // 恢复 body
    document.body.style.position = '';
    document.body.style.top = '';
    document.body.style.width = '';
    window.scrollTo(0, _savedBodyScroll);
}
// 登录后自动检查新公告/通知
window.addEventListener('load', function() {
    setTimeout(checkUnreadMessages, 1200);

    // ===== 处理分享参数：自动跳转到对应模块 =====
    if (SHARE_TO && SHARE_ID) {
        console.log('[share] 检测到分享参数：', SHARE_TO, SHARE_ID);
        // 延迟执行，确保各模块 JS 已加载
        setTimeout(function() {
            var pageMap = {
                'expenses'      : 'family-expenses',
                'family-expenses': 'family-expenses',
                'plans'         : 'family-plans',
                'family-plans'  : 'family-plans',
                'medical'        : 'medical-records',
                'medical-records': 'medical-records',
                'documents'      : 'documents',
                'contacts'       : 'contacts',
                'announcements'  : 'announcements',
                'wallet'         : 'wallet',
                'fundraising'    : 'fundraising',
            };
            var targetPage = pageMap[SHARE_TO] || SHARE_TO;
            // 切换到目标页面
            try {
                if (typeof showPage === 'function') {
                    showPage(targetPage);
                }
                // 如果是家庭支出，尝试高亮对应记录
                if (targetPage === 'family-expenses' && typeof loadExpenses === 'function') {
                    loadExpenses();
                    setTimeout(function(){
                        var el = document.getElementById('expense-row-' + SHARE_ID);
                        if (el) {
                            el.style.background = '#fef3c7';
                            el.scrollIntoView({behavior:'smooth', block:'center'});
                            setTimeout(function(){ el.style.background = ''; }, 3000);
                        }
                    }, 800);
                }
            } catch(e) { console.error('[share] 跳转失败：', e); }
        }, 500);
    }
});

// ===== 证件资料功能 =====
function loadDocuments() {
    fetch('api/documents.php?action=list')
        .then(r=>r.json())
        .then(d=>{
            if (!d.success) return;
            const tbody = document.getElementById('doc-tbody');
            const search = (document.getElementById('doc-search') || {value: ''}).value.trim().toLowerCase();
            let data = d.data || [];
            if (search) data = data.filter(x => (x.title || '').toLowerCase().indexOf(search) > -1);

            // 统计卡片
            let totalFiles = 0, keepers = new Set();
            data.forEach(doc => {
                if (doc.file_path) try { totalFiles += JSON.parse(doc.file_path).length; } catch(e){}
                if (doc.keeper_name) keepers.add(doc.keeper_name);
            });
            const elTotal = document.getElementById('doc-stat-total');
            const elFiles = document.getElementById('doc-stat-files');
            const elKeepers = document.getElementById('doc-stat-keepers');
            if (elTotal) elTotal.textContent = data.length;
            if (elFiles) elFiles.textContent = totalFiles;
            if (elKeepers) elKeepers.textContent = keepers.size;

            if (data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#999;">暂无证件资料</td></tr>';
                return;
            }
            tbody.innerHTML = data.map((doc, i) => {
                // 解析文件数量
                let fileCount = 0;
                if (doc.file_path) { try { const f=JSON.parse(doc.file_path); fileCount=f.length; } catch(e){} }
                return '<tr style="cursor:pointer;" onclick="showDocDetail(' + doc.id + ')">'
                    + '<td>' + (i+1) + '</td>'
                    + '<td>'
                        + '<strong style="color:#4f46e5;cursor:pointer;" title="' + escHtml(doc.title) + '">' + escHtml(doc.title) + '</strong>'
                        + (fileCount > 0 ? '<span style="background:#ede9fe;color:#7c3aed;font-size:0.72rem;padding:1px 6px;border-radius:10px;margin-left:6px;">' + fileCount + '个附件</span>' : '')
                    + '</td>'
                    + '<td>' + (fileCount > 0 ? '<span style="color:#7c3aed;font-size:0.85rem;">' + fileCount + '</span>' : '<span style="color:#999;">-</span>') + '</td>'
                    + '<td>' + escHtml(doc.keeper_name || '-') + '</td>'
                    + '<td>' + escHtml(doc.created_at) + '</td>'
                    + '<td onclick="event.stopPropagation();">'
                        + '<button onclick="editDocument(' + doc.id + ')" class="btn btn-primary" style="padding:4px 10px;font-size:0.85rem;">编辑</button>'
                        + '<button onclick="shareItem(\'documents\',' + doc.id + ',\'' + escHtml(doc.title) + '\')" style="background:#3b82f6;color:#fff;border:none;padding:4px 10px;border-radius:6px;cursor:pointer;font-size:0.85rem;margin-left:4px;">分享</button>'
                        + (IS_ADMIN ? '<button onclick="deleteDocument(' + doc.id + ')" style="background:#ef4444;color:#fff;border:none;padding:4px 10px;border-radius:6px;cursor:pointer;font-size:0.85rem;margin-left:4px;">删除</button>' : '')
                    + '</td>'
                + '</tr>';
            }).join('');
        })
        .catch(e => console.error('[documents]', e));
}

function showDocumentForm(id) {
    document.getElementById('doc-form-id').value = id || '';
    document.getElementById('doc-form-title-input').value = '';
    document.getElementById('doc-form-file').value = '';
    document.getElementById('doc-form-keeper').innerHTML = '<option value="">请选择保管人</option>';
    
    // 更新弹窗标题
    const titleEl = document.getElementById('doc-form-title');
    if (titleEl) titleEl.textContent = id ? '📝 编辑证件资料' : '📁 新增证件资料';
    
    // 加载用户列表作为保管人选项
    fetch('api/users.php?action=list')
        .then(r=>r.json())
        .then(d=>{
            if (d.success) {
                const select = document.getElementById('doc-form-keeper');
                d.data.forEach(u => {
                    const opt = document.createElement('option');
                    opt.value = u.id; opt.textContent = u.username + (u.realname ? '('+u.realname+')' : '');
                    select.appendChild(opt);
                });
            }
        });
    
    if (id) {
        fetch('api/documents.php?action=list')
            .then(r=>r.json())
            .then(d=>{
                const doc = d.data.find(x=>x.id==id);
                if (doc) {
                    document.getElementById('doc-form-title-input').value = doc.title;
                    document.getElementById('doc-form-keeper').value = doc.keeper_id || '';
                    // 加载已有文件的预览
                    renderDocFilePreview(doc.file_path);
                }
            });
    } else {
        // 新增模式：清空预览
        document.getElementById('doc-file-preview-list').innerHTML = '';
        document.getElementById('doc-upload-area').style.display = 'block';
    }

    showOverlay('doc-form-overlay');
}

function editDocument(id) { showDocumentForm(id); }
function closeDocumentForm() { hideOverlay('doc-form-overlay'); }

function saveDocument() {
    const title = document.getElementById('doc-form-title-input').value.trim();
    if (!title) { alert('⚠️ 请输入证件名称'); return; }
    
    const formData = new FormData();
    formData.append('title', title);
    formData.append('keeper_id', document.getElementById('doc-form-keeper').value);
    // 多文件上传
    const fileInput = document.getElementById('doc-form-file');
    for (let i = 0; i < fileInput.files.length; i++) {
        formData.append('files[]', fileInput.files[i]);
    }
    
    const id = document.getElementById('doc-form-id').value;
    if (id) formData.append('id', id);
    const url = id ? `api/documents.php?action=update` : 'api/documents.php?action=create';
    
    fetch(url, { method: 'POST', body: formData })
        .then(r=>r.json())
        .then(d=>{
            if (d.success) {
                alert(id ? '✅ 证件资料修改成功！' : '📁 证件资料新增成功！');
                closeDocumentForm();
                loadDocuments();
            } else {
                alert('❌ ' + (d.error || '保存失败'));
            }
        })
        .catch(e => { console.error('[documents]', e); alert('❌ 网络错误，请检查连接'); });
}

function deleteDocument(id) {
    if (!confirm('确定删除此证件？此操作不可恢复！')) return;
    const formData = new FormData();
    formData.append('id', id);
    fetch('api/documents.php?action=delete', {
        method: 'POST',
        body: formData
    }).then(r=>r.json()).then(d=>{
        if (d.success) {
            alert('📁 证件资料已删除');
            loadDocuments();
        } else {
            alert('❌ ' + (d.error || '删除失败'));
        }
    }).catch(e => { console.error('[documents]', e); alert('网络错误'); });
}

// ===== 备忘录功能 =====
function loadMemos() {
    fetch('api/memos.php?action=list')
        .then(r=>r.json())
        .then(d=>{
            if (!d.success) return;
            const container = document.getElementById('memo-list');
            const search = (document.getElementById('memo-search') || {value: ''}).value.trim().toLowerCase();
            let data = d.data || [];
            if (search) data = data.filter(x => (x.content || '').toLowerCase().indexOf(search) > -1);

            // 统计卡片
            const todayStr = new Date().toISOString().slice(0, 10);
            let todayCount = 0, updatedCount = 0;
            data.forEach(m => {
                if ((m.created_at || '').startsWith(todayStr)) todayCount++;
                if (m.updated_at && !m.updated_at.startsWith(todayStr) && m.updated_at > m.created_at) updatedCount++;
            });
            const elTotal = document.getElementById('memo-stat-total');
            const elToday = document.getElementById('memo-stat-today');
            const elUpdated = document.getElementById('memo-stat-updated');
            if (elTotal) elTotal.textContent = data.length;
            if (elToday) elToday.textContent = todayCount;
            if (elUpdated) elUpdated.textContent = updatedCount;

            if (data.length === 0) {
                container.innerHTML = '<div style="text-align:center;color:#999;padding:30px;">暂无备忘记录</div>';
                return;
            }
            container.innerHTML = data.map((m, i) => '<div style="border:1px solid #fce7f3;border-radius:12px;padding:14px;margin-bottom:10px;background:linear-gradient(135deg,#fff9fb,#fff);transition:transform 0.2s;" onmouseover="this.style.transform=\'translateY(-1px)\'" onmouseout="this.style.transform=\'\'">'
                + '<div style="font-weight:600;font-size:0.95rem;margin-bottom:6px;color:#831843;line-height:1.5;">' + escHtml(m.content) + '</div>'
                + '<div style="display:flex;justify-content:space-between;font-size:0.78rem;color:#be185d;flex-wrap:wrap;gap:4px;">'
                    + '<span>📅 ' + escHtml(m.record_time) + '</span>'
                    + '<span>👤 ' + escHtml(m.record_by_name || m.record_by_username || '-') + '</span>'
                    + '<span>'
                        + '<button onclick="editMemo(' + m.id + ')" class="btn btn-primary" style="padding:3px 10px;font-size:0.75rem;">✏️ 编辑</button>'
                        + '<button onclick="shareItem(\'memos\',' + m.id + ',\'' + escHtml(m.title) + '\')" class="btn" style="padding:3px 10px;font-size:0.75rem;background:#dbeafe;color:#1e40af;border-color:#bfdbfe;">分享</button>'
                        + '<button onclick="deleteMemo(' + m.id + ')" class="btn btn-danger" style="padding:3px 10px;font-size:0.75rem;">🗑️ 删除</button>'
                    + '</span>'
                + '</div>'
            + '</div>').join('');
        })
        .catch(e => console.error('[memos]', e));
}

function showMemoForm(id) {
    document.getElementById('memo-form-id').value = id || '';
    document.getElementById('memo-form-content').value = '';
    document.getElementById('memo-form-time').value = '';
    document.getElementById('memo-form-title').textContent = id ? '📝 编辑备忘' : '📝 新增备忘';
    
    if (id) {
        // 编辑模式：加载现有数据
        fetch('api/memos.php?action=list')
            .then(r=>r.json())
            .then(d=>{
                if (d.success) {
                    const memo = d.data.find(x=>x.id==id);
                    if (memo) {
                        document.getElementById('memo-form-content').value = memo.content;
                        document.getElementById('memo-form-time').value = memo.record_time;
                    }
                }
            });
    }

    showOverlay('memo-form-overlay');
}

function editMemo(id) { showMemoForm(id); }
function closeMemoForm() { hideOverlay('memo-form-overlay'); }

function saveMemo() {
    const content = document.getElementById('memo-form-content').value.trim();
    const recordTime = document.getElementById('memo-form-time').value;
    const id = document.getElementById('memo-form-id').value;
    
    if (!content) { alert('请输入记录事项'); return; }
    if (!recordTime) { alert('请选择时间'); return; }
    
    const url = id ? 'api/memos.php?action=update' : 'api/memos.php?action=create';
    const data = {content: content, record_time: recordTime};
    if (id) data.id = id;
    
    fetch(url, {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify(data)
    }).then(r=>r.json()).then(d=>{
        if (d.success) {
            alert(d.msg || '保存成功！');
            closeMemoForm();
            loadMemos();
        } else {
            alert(d.error || '保存失败');
        }
    }).catch(e => alert('网络错误'));
}

function deleteMemo(id) {
    if (!confirm('确定删除此备忘？')) return;
    fetch('api/memos.php?action=delete', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({id: id})
    }).then(r=>r.json()).then(d=>{
        if (d.success) { loadMemos(); } else { alert(d.error); }
    });
}

// ===== 通讯录功能 =====
function loadContacts() {
    fetch('api/contacts.php?action=list')
        .then(r=>r.json())
        .then(d=>{
            if (!d.success) return;
            const container = document.getElementById('contact-list');
            const search = (document.getElementById('contact-search') || {value: ''}).value.trim().toLowerCase();
            let data = d.data || [];
            if (search) data = data.filter(x => (x.name || '').toLowerCase().indexOf(search) > -1 || (x.position || '').toLowerCase().indexOf(search) > -1);

            // 统计卡片
            let phoneCount = 0, emailCount = 0;
            data.forEach(c => { if (c.phone) phoneCount++; if (c.email) emailCount++; });
            const elTotal = document.getElementById('contact-stat-total');
            const elPhone = document.getElementById('contact-stat-phone');
            const elEmail = document.getElementById('contact-stat-email');
            if (elTotal) elTotal.textContent = data.length;
            if (elPhone) elPhone.textContent = phoneCount;
            if (elEmail) elEmail.textContent = emailCount;

            if (data.length === 0) {
                container.innerHTML = '<div style="grid-column:1/-1;text-align:center;color:#999;padding:30px;">暂无联系人</div>';
                return;
            }
            container.innerHTML = data.map((c, i) => {
                var avatarSrc = c.avatar_path || 'data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><circle cx=%2250%22 cy=%2235%22 r=%2222%22 fill=%22%23a7f3d0%22/><ellipse cx=%2250%22 cy=%2285%22 rx=%2235%22 ry=%2225%22 fill=%22%23a7f3d0%22/></svg>';
                var cardStyle = 'border:1px solid #d1fae5;border-radius:14px;padding:18px;text-align:center;background:linear-gradient(135deg,#f0fdf4,#fff);transition:transform 0.2s;';
                var html = '<div style="' + cardStyle + '" onmouseover="this.style.transform=\'translateY(-3px)\'" onmouseout="this.style.transform=\'\'">'
                    + '<img src="' + escHtml(avatarSrc) + '" '
                    + 'style="width:70px;height:70px;border-radius:50%;object-fit:cover;margin-bottom:10px;background:#ecfdf5;" '
                    + 'onerror="this.src=\'data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><circle cx=%2250%22 cy=%2235%22 r=%2222%22 fill=%22%23a7f3d0%22/><ellipse cx=%2250%22 cy=%2285%22 rx=%2235%22 ry=%2225%22 fill=%22%23a7f3d0%22/></svg>\'">'
                    + '<div style="font-weight:700;font-size:1.05rem;margin-bottom:4px;color:#065f46;">' + escHtml(c.name) + '</div>';
                if (c.position) html += '<div style="font-size:0.83rem;color:#047857;margin-bottom:8px;">' + escHtml(c.position) + '</div>';
                if (c.phone) {
                    html += '<div style="display:flex;justify-content:center;gap:8px;margin-top:10px;">'
                        + '<a href="tel:' + escHtml(c.phone) + '" style="background:#10b981;color:#fff;border:none;padding:6px 16px;border-radius:20px;text-decoration:none;font-size:0.85rem;cursor:pointer;">📞 拨打</a>'
                        + '<button onclick="copyPhone(\'' + escHtml(c.phone) + '\')" style="background:#3b82f6;color:#fff;border:none;padding:6px 16px;border-radius:20px;cursor:pointer;font-size:0.85rem;">📋 复制</button>'
                        + '</div>';
                }
                html += '<div style="margin-top:10px;display:flex;justify-content:center;gap:6px;">'
                        + '<button onclick="editContact(' + c.id + ')" class="btn btn-primary" style="padding:4px 12px;font-size:0.8rem;">编辑</button>';
                if (IS_ADMIN === true || IS_ADMIN === 'true') {
                    html += '<button onclick="deleteContact(' + c.id + ')" class="btn btn-danger" style="padding:4px 12px;font-size:0.8rem;">删除</button>';
                }
                html += '</div></div>';
                return html;
            }).join('');
        })
        .catch(e => console.error('[contacts]', e));
}

function copyPhone(phone) {
    navigator.clipboard.writeText(phone).then(() => {
        alert('电话已复制：' + phone);
    }).catch(() => {
        // 兼容处理
        const tmp = document.createElement('textarea');
        tmp.value = phone; document.body.appendChild(tmp);
        tmp.select(); document.execCommand('copy');
        document.body.removeChild(tmp);
        alert('电话已复制：' + phone);
    });
}

function showContactForm(id) {
    document.getElementById('contact-form-id').value = id || '';
    document.getElementById('contact-form-name').value = '';
    document.getElementById('contact-form-position').value = '';
    document.getElementById('contact-form-phone').value = '';
    document.getElementById('contact-form-avatar').value = '';
    document.getElementById('contact-avatar-preview').style.display = 'none';
    
    if (id) {
        fetch('api/contacts.php?action=list')
            .then(r=>r.json())
            .then(d=>{
                const c = d.data.find(x=>x.id==id);  // 使用 == 而不是 ===
                if (c) {
                    document.getElementById('contact-form-name').value = c.name;
                    document.getElementById('contact-form-position').value = c.position || '';
                    document.getElementById('contact-form-phone').value = c.phone || '';
                    if (c.avatar_path) {
                        document.getElementById('contact-avatar-preview').src = c.avatar_path;
                        document.getElementById('contact-avatar-preview').style.display = 'block';
                    }
                }
            });
    }

    showOverlay('contact-form-overlay');
}

function editContact(id) { showContactForm(id); }
function closeContactForm() { hideOverlay('contact-form-overlay'); }

function saveContact() {
    const name = document.getElementById('contact-form-name').value.trim();
    if (!name) { alert('请输入联系人姓名'); return; }
    
    const formData = new FormData();
    formData.append('name', name);
    formData.append('position', document.getElementById('contact-form-position').value.trim());
    formData.append('phone', document.getElementById('contact-form-phone').value.trim());
    const avatarFile = document.getElementById('contact-form-avatar').files[0];
    if (avatarFile) { formData.append('avatar', avatarFile); }
    
    const id = document.getElementById('contact-form-id').value;
    if (id) formData.append('id', id);
    const url = id ? 'api/contacts.php?action=update' : 'api/contacts.php?action=create';
    
    fetch(url, { method: 'POST', body: formData })
        .then(r=>r.json())
        .then(d=>{
            if (d.success) {
                alert(d.msg || '保存成功！');
                closeContactForm();
                loadContacts();
            } else {
                alert(d.error || '保存失败');
            }
        })
        .catch(e => alert('网络错误'));
}

function deleteContact(id) {
    if (!confirm('确定删除此联系人？此操作不可恢复！')) return;
    const fd = new FormData();
    fd.append('id', id);
    fetch('api/contacts.php?action=delete', {
        method: 'POST',
        body: fd
    }).then(r=>r.json()).then(d=>{
        if (d.success) {
            alert('✅ 联系人已删除！');
            loadContacts();
        } else { alert('❌ ' + (d.error || '删除失败')); }
    }).catch(e => alert('❌ 网络错误'));
}

// ===== 家庭公告功能 =====
function loadAnnouncements() {
    fetch('api/announcements.php?action=list')
        .then(r=>r.json())
        .then(d=>{
            if (!d.success) return;
            const container = document.getElementById('ann-list');
            const search = (document.getElementById('ann-search') || {value: ''}).value.trim().toLowerCase();
            let data = d.data || [];
            if (search) data = data.filter(x => (x.title||'').toLowerCase().indexOf(search) > -1);

            // 统计卡片
            var todayStr = new Date().toISOString().slice(0, 10);
            var todayCount = 0;
            var topCount = 0;
            data.forEach(function(a) {
                if ((a.created_at||'').startsWith(todayStr)) todayCount++;
                if (parseInt(a.is_visible) === 1) topCount++;
            });
            var elTotal = document.getElementById('ann-stat-total');
            var elToday = document.getElementById('ann-stat-today');
            var elTop   = document.getElementById('ann-stat-top');
            if (elTotal) elTotal.textContent = data.length;
            if (elToday) elToday.textContent = todayCount;
            if (elTop)   elTop.textContent   = topCount;

            if (data.length === 0) {
                container.innerHTML = '<div style="text-align:center;color:#999;padding:40px;font-size:0.95rem;">暂无家庭公告，点击上方按钮发布第一条吧 📢</div>';
                return;
            }

            container.innerHTML = data.map(function(a) {
                var isVisible = parseInt(a.is_visible) === 1;
                var timeLabel = a.updated_at ? '更新于 ' : '发布于 ';
                var displayTime = a.updated_at || a.created_at;

                return '<div style="border:1px solid #fef3c7;border-radius:14px;padding:16px;margin-bottom:12px;background:linear-gradient(135deg,#fffdf5,#fff);">'
                    + (isVisible ? '<div style="background:#f59e0b;color:#fff;display:inline-block;font-size:0.7rem;padding:2px 10px;border-radius:20px;margin-bottom:8px;">🔝 已置顶</div>' : '')
                    + '<div style="font-weight:700;font-size:1.05rem;margin-bottom:6px;color:#92400e;line-height:1.5;">' + escHtml(a.title) + '</div>'
                    + '<div style="font-size:0.9rem;color:#374151;line-height:1.7;margin-bottom:8px;white-space:pre-wrap;">' + escHtml(a.content) + '</div>'
                    + '<div style="display:flex;justify-content:space-between;align-items:center;font-size:0.78rem;color:#92400e;flex-wrap:wrap;gap:6px;margin-top:10px;padding-top:10px;border-top:1px dashed #fde68a;">'
                        + '<span>👤 ' + escHtml(a.author_name || '-') + '</span>'
                        + '<span>🕐 ' + timeLabel + formatDateTime(displayTime) + '</span>'
                        + '<span>'
                            + '<button onclick="editAnn(' + a.id + ')" class="btn btn-primary" style="padding:3px 11px;font-size:0.75rem;background:#f59e0b;border-color:#f59e0b;">✏️ 编辑</button> '
                            + '<button onclick="shareItem(\'announcements\',' + a.id + ',\'' + escHtml(a.title) + '\')" class="btn" style="padding:3px 11px;font-size:0.75rem;background:#dbeafe;color:#1e40af;border-color:#bfdbfe;">分享</button> '
                            + '<button onclick="deleteAnn(' + a.id + ')" class="btn btn-danger" style="padding:3px 11px;font-size:0.75rem;">🗑️ 删除</button> '
                            + '<button onclick="toggleTopAnn(' + a.id + ')" class="btn" style="padding:3px 11px;font-size:0.75rem;background:#fef3c7;color:#92400e;border-color:#fde68a;">' + (isVisible ? '取消置顶' : '置顶') + '</button>'
                        + '</span>'
                    + '</div>'
                + '</div>';
            }).join('');
        })
        .catch(function(e) { console.error('[announcements]', e); });
}

function showAnnForm(id) {
    document.getElementById('ann-form-id').value = id || '';
    document.getElementById('ann-form-title-input').value = '';
    document.getElementById('ann-form-content').value = '';
    document.getElementById('ann-form-title').textContent = id ? '📝 编辑公告' : '📢 发布公告';

    if (id) {
        fetch('api/announcements.php?action=list')
            .then(function(r){return r.json();})
            .then(function(d){
                if (!d.success) return;
                var ann = d.data.find(function(x){return x.id == id;});
                if (ann) {
                    document.getElementById('ann-form-title-input').value = ann.title;
                    document.getElementById('ann-form-content').value = ann.content;
                }
            });
    }

    showOverlay('ann-form-overlay');
}

function editAnn(id) { showAnnForm(id); }
function closeAnnForm() { hideOverlay('ann-form-overlay'); }

function saveAnn() {
    var title   = document.getElementById('ann-form-title-input').value.trim();
    var content = document.getElementById('ann-form-content').value.trim();
    var id      = document.getElementById('ann-form-id').value;
    if (!title) { alert('请输入公告标题'); return; }
    if (!content) { alert('请输入公告内容'); return; }

    var formData = new FormData();
    formData.append('title', title);
    formData.append('content', content);
    if (id) formData.append('id', id);
    // 图片上传
    var fileInput = document.getElementById('ann-form-images');
    for (var i = 0; i < fileInput.files.length; i++) {
        formData.append('images[]', fileInput.files[i]);
    }

    var url = id ? 'api/announcements.php?action=update' : 'api/announcements.php?action=create';

    fetch(url, { method: 'POST', body: formData })
        .then(function(r){return r.json();})
        .then(function(d){
            if (d.success) {
                alert(id ? '✅ 公告修改成功！' : '📢 公告发布成功！');
                closeAnnForm();
                loadAnnouncements();
            } else {
                alert('❌ ' + (d.error || '操作失败'));
            }
        })
        .catch(function(e) { console.error('[ann]', e); alert('❌ 网络错误，请检查连接'); });
}

function deleteAnn(id) {
    if (!confirm('确定删除此公告？此操作不可恢复！')) return;
    var fd = new FormData();
    fd.append('id', id);
    fetch('api/announcements.php?action=delete', { method: 'POST', body: fd })
        .then(function(r){return r.json();})
        .then(function(d){
            if (d.success) {
                alert('📢 公告已删除');
                loadAnnouncements();
            } else {
                alert('❌ ' + (d.error || '删除失败'));
            }
        })
        .catch(function(e) { alert('❌ 网络错误'); });
}

function toggleTopAnn(id) {
    var fd = new FormData();
    fd.append('id', id);
    fetch('api/announcements.php?action=toggle_top', { method: 'POST', body: fd })
        .then(function(r){return r.json();})
        .then(function(d){ if (d.success) loadAnnouncements(); })
        .catch(function(e) {});
}

// 图片预览
function previewAnnImages(input) {
    var preview = document.getElementById('ann-image-preview-list');
    preview.innerHTML = '';
    for (var i = 0; i < input.files.length; i++) {
        (function(f){
            var div = document.createElement('div');
            div.style.cssText = 'position:relative;width:90px;height:90px;border-radius:10px;overflow:hidden;';
            if (f.type.startsWith('image/')) {
                var reader = new FileReader();
                reader.onload = function(e) { div.innerHTML = '<img src="' + e.target.result + '" style="width:100%;height:100%;object-fit:cover;"><span onclick="this.parentElement.remove()" style="position:absolute;top:2px;right:2px;background:rgba(239,68,68,0.85);color:#fff;border-radius:50%;width:18px;height:18px;text-align:center;font-size:12px;cursor:pointer;line-height:18px;">×</span>'; };
                reader.readAsDataURL(f);
            }
            preview.appendChild(div);
        })(input.files[i]);
    }
}

function renderAnnImagePreview(imagesJson) {
    var preview = document.getElementById('ann-image-preview-list');
    preview.innerHTML = '';
    if (!imagesJson) return;
    var imgs;
    try { imgs = JSON.parse(imagesJson); } catch(e) { return; }
    if (!imgs) return;
    for (var j = 0; j < imgs.length; j++) {
        (function(img){
            var div = document.createElement('div');
            div.style.cssText = 'position:relative;width:90px;height:90px;border-radius:10px;overflow:hidden;';
            div.innerHTML = '<img src="' + img.path + '" style="width:100%;height:100%;object-fit:cover;"><span style="position:absolute;bottom:2px;left:2px;right:0;background:rgba(0,0,0,0.55);color:#fff;font-size:0.65rem;padding:1px 5px;border-radius:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + escHtml(img.name) + '</span>';
            preview.appendChild(div);
        })(imgs[j]);
    }
    document.getElementById('ann-upload-area').style.display = 'block';
}

// ===== 家庭计划功能 =====
function loadPlans() {
    var search = document.getElementById('plan-search').value.trim();
    var filter = document.getElementById('plan-filter').value;
    fetch('api/plans.php?action=list')
        .then(function(r){return r.json();})
        .then(function(d){
            if (!d.success) { console.error('[plans]', d.error); return; }
            var data = d.data || [];
            var total = 0, pending = 0, done = 0, totalAmt = 0;
            var html = '';
            for (var i = 0; i < data.length; i++) {
                var p = data[i];
                var title = (p.title || '').toLowerCase();
                if (search && title.indexOf(search.toLowerCase()) === -1) continue;
                var isDone = parseInt(p.is_done) === 1;
                if (filter === 'pending' && isDone) continue;
                if (filter === 'done' && !isDone) continue;
                total++;
                totalAmt += parseFloat(p.planned_amount || 0);
                if (isDone) done++; else pending++;

                // agree_names 由后端直接返回真实姓名（大哥/二哥等）
                var approversNames = p.agree_names || [];

                html += '<tr>'
                    + '<td style="font-weight:600;">' + escHtml(p.title) + '</td>'
                    + '<td>' + (p.planned_date || '-') + '</td>'
                    + '<td>¥' + parseFloat(p.planned_amount || 0).toFixed(2) + '</td>'
                    + '<td>' + (isDone ? '<span style="color:#16a34a;font-weight:600;">✅ 已完成</span>' : '<span style="color:#ea580c;">⏳ 待完成</span>') + '</td>'
                    + '<td style="font-size:0.85rem;color:#6b7280;">' + (approversNames.length > 0 ? approversNames.join(', ') : '<span style="color:#d1d5db;">暂无</span>') + '</td>'
                    + '<td>' + escHtml(p.executor || '-') + '</td>'
                    + '<td style="white-space:nowrap;">'
                        + '<button onclick="togglePlanDone(' + p.id + ')" class="btn" style="padding:3px 10px;font-size:0.75rem;margin-right:4px;">' + (isDone ? '重新打开' : '标记完成') + '</button>'
                        + '<button onclick="togglePlanApprove(' + p.id + ')" class="btn" style="padding:3px 10px;font-size:0.75rem;margin-right:4px;background:#ede9fe;color:#5b21b6;border-color:#c4b5fd;">' + ((p.agree_users||[]).indexOf(<?php echo CURRENT_USER_ID(); ?>) !== -1 ? '取消同意' : '同意') + '</button>'
                        + '<button onclick="editPlan(' + p.id + ')" class="btn btn-primary" style="padding:3px 10px;font-size:0.75rem;margin-right:4px;">编辑</button>'
                        + '<button onclick="shareItem(\'plans\',' + p.id + ',\'' + escHtml(p.title) + '\')" class="btn" style="padding:3px 10px;font-size:0.75rem;margin-right:4px;background:#dbeafe;color:#1e40af;border-color:#bfdbfe;">分享</button>'
                        + '<button onclick="deletePlan(' + p.id + ')" class="btn btn-danger" style="padding:3px 10px;font-size:0.75rem;">删除</button>'
                    + '</td>'
                    + '</tr>';
            }
            document.getElementById('plan-stat-total').textContent = total;
            document.getElementById('plan-stat-pending').textContent = pending;
            document.getElementById('plan-stat-done').textContent = done;
            document.getElementById('plan-stat-amount').textContent = '¥' + totalAmt.toFixed(2);
            document.getElementById('plan-list').innerHTML = html || '<tr><td colspan="7" style="text-align:center;color:#999;padding:40px;">暂无计划，点击上方按钮添加 📅</td></tr>';
        })
        .catch(function(e) { console.error('[plans]', e); });
}

function showPlanForm(id) {
    document.getElementById('plan-form-id').value = id || '';
    document.getElementById('plan-form-title-input').value = '';
    document.getElementById('plan-form-desc').value = '';
    document.getElementById('plan-form-date').value = '';
    document.getElementById('plan-form-amount').value = '';
    document.getElementById('plan-form-executor').value = '';
    document.getElementById('plan-form-done').checked = false;
    document.getElementById('plan-form-title').textContent = id ? '✏️ 修改计划' : '📅 新增计划';
    if (id) {
        fetch('api/plans.php?action=list')
            .then(function(r){return r.json();})
            .then(function(d){
                if (!d.success) return;
                var list = d.data || [];
                for (var i = 0; i < list.length; i++) {
                    if (parseInt(list[i].id) === parseInt(id)) {
                        var p = list[i];
                        document.getElementById('plan-form-title-input').value = p.title || '';
                        document.getElementById('plan-form-desc').value = p.description || '';
                        document.getElementById('plan-form-date').value = p.planned_date || '';
                        document.getElementById('plan-form-amount').value = p.planned_amount || '';
                        document.getElementById('plan-form-executor').value = p.executor || '';
                        document.getElementById('plan-form-done').checked = parseInt(p.is_done) === 1;
                        break;
                    }
                }
            });
    }
    showOverlay('plan-form-overlay');
}

function closePlanForm() {
    hideOverlay('plan-form-overlay');
}

function savePlan(e) {
    e.preventDefault();
    var id           = document.getElementById('plan-form-id').value;
    var title        = document.getElementById('plan-form-title-input').value.trim();
    var description  = document.getElementById('plan-form-desc').value.trim();
    var planned_date = document.getElementById('plan-form-date').value;
    var planned_amount = document.getElementById('plan-form-amount').value;
    var executor     = document.getElementById('plan-form-executor').value.trim();
    var is_done      = document.getElementById('plan-form-done').checked ? 1 : 0;
    if (!title) { alert('请输入计划事项'); return; }
    var fd = new FormData();
    fd.append('title', title);
    fd.append('description', description);
    fd.append('plan_date', planned_date);
    fd.append('plan_amount', planned_amount);
    fd.append('executor', executor);
    fd.append('is_done', is_done);
    var url = id ? 'api/plans.php?action=update' : 'api/plans.php?action=create';
    if (id) fd.append('id', id);
    fetch(url, { method: 'POST', body: fd })
        .then(function(r){return r.json();})
        .then(function(d){
            if (d.success) {
                closePlanForm();
                loadPlans();
            } else {
                alert('❌ ' + (d.error || '操作失败'));
            }
        })
        .catch(function(e) { alert('❌ 网络错误'); });
}

function deletePlan(id) {
    if (!confirm('确定删除此计划？此操作不可恢复！')) return;
    var fd = new FormData();
    fd.append('id', id);
    fetch('api/plans.php?action=delete', { method: 'POST', body: fd })
        .then(function(r){return r.json();})
        .then(function(d){
            if (d.success) loadPlans();
            else alert('❌ ' + (d.error || '删除失败'));
        });
}

function editPlan(id) {
    showPlanForm(id);
}

function togglePlanDone(id) {
    var fd = new FormData();
    fd.append('id', id);
    fetch('api/plans.php?action=toggle_done', { method: 'POST', body: fd })
        .then(function(r){return r.json();})
        .then(function(d){ if (d.success) loadPlans(); });
}

function togglePlanApprove(id) {
    var fd = new FormData();
    fd.append('id', id);
    fetch('api/plans.php?action=toggle_agree', { method: 'POST', body: fd })
        .then(function(r){return r.json();})
        .then(function(d){ if (d.success) loadPlans(); });
}

// ===== 证件资料：文件预览 & 拖拽 & 详情弹窗 =====
function previewDocFiles(input) {
    const preview = document.getElementById('doc-file-preview-list');
    preview.innerHTML = '';
    const files = input.files;
    for (let i = 0; i < files.length; i++) {
        const f = files[i];
        const isImage = f.type.startsWith('image/');
        const div = document.createElement('div');
        div.style.cssText = 'position:relative;width:90px;height:90px;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;display:flex;align-items:center;justify-content:center;background:#f9fafb;';
        if (isImage) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = document.createElement('img');
                img.src = e.target.result;
                img.style.cssText = 'width:100%;height:100%;object-fit:cover;';
                div.appendChild(img);
            };
            reader.readAsDataURL(f);
        } else {
            const icon = document.createElement('div');
            icon.innerHTML = '📄';
            icon.style.fontSize = '2rem';
            div.appendChild(icon);
        }
        // 文件名
        const nameTag = document.createElement('div');
        nameTag.textContent = f.name.length > 8 ? f.name.slice(0, 8) + '…' : f.name;
        nameTag.style.cssText = 'position:absolute;bottom:0;left:0;right:0;background:rgba(0,0,0,0.55);color:#fff;font-size:0.65rem;text-align:center;padding:2px;';
        div.appendChild(nameTag);
        preview.appendChild(div);
    }
    document.getElementById('doc-upload-area').style.display = 'none';
}

function handleDocDrop(event) {
    event.preventDefault();
    const dt = event.dataTransfer;
    const input = document.getElementById('doc-form-file');
    if (dt.files.length) {
        input.files = dt.files;
        previewDocFiles(input);
    }
    const area = document.getElementById('doc-upload-area');
    area.style.borderColor = '#a78bfa';
    area.style.background = '#f5f3ff';
}

function renderDocFilePreview(filePathJson) {
    const preview = document.getElementById('doc-file-preview-list');
    preview.innerHTML = '';
    if (!filePathJson) { preview.style.display = 'none'; return; }
    let files = [];
    try { files = JSON.parse(filePathJson); } catch(e) { return; }
    if (!files.length) { preview.style.display = 'none'; return; }
    preview.style.display = 'flex';
    files.forEach(f => {
        const isImage = f.type === 'image' || (f.path && /\.(jpg|jpeg|png|gif|webp|bmp)$/i.test(f.path));
        const div = document.createElement('div');
        div.style.cssText = 'position:relative;width:90px;height:90px;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;display:flex;align-items:center;justify-content:center;background:#f9fafb;cursor:pointer;';
        div.title = f.name || '文件';
        div.onclick = () => window.open(f.path, '_blank');
        if (isImage) {
            const img = document.createElement('img');
            img.src = f.path;
            img.style.cssText = 'width:100%;height:100%;object-fit:cover;';
            img.onerror = function() { this.style.display='none'; div.innerHTML='📄'; };
            div.appendChild(img);
        } else {
            div.innerHTML = '<div style="font-size:2rem;">📄</div>';
        }
        const nameTag = document.createElement('div');
        const name = f.name || f.path.split('/').pop();
        nameTag.textContent = name.length > 8 ? name.slice(0, 8) + '…' : name;
        nameTag.style.cssText = 'position:absolute;bottom:0;left:0;right:0;background:rgba(0,0,0,0.55);color:#fff;font-size:0.65rem;text-align:center;padding:2px;';
        div.appendChild(nameTag);
        preview.appendChild(div);
    });
}

// 点击证件名称查看详情
function showDocDetail(id) {
    fetch('api/documents.php?action=list')
        .then(r=>r.json())
        .then(d=>{
            const doc = d.data ? d.data.find(x=>x.id==id) : null;
            if (!doc) { alert('证件不存在'); return; }
            let html = '<div style="text-align:left;max-height:60vh;overflow-y:auto;padding:10px;">';
            html += '<h3 style="margin:0 0 12px 0;color:#1f2937;">' + escHtml(doc.title) + '</h3>';
            html += '<p style="color:#6b7280;font-size:0.85rem;margin-bottom:16px;">保管人：' + (escHtml(doc.keeper_name || '未指定')) + '｜上传时间：' + escHtml(doc.created_at) + '</p>';
            if (doc.file_path) {
                let files = [];
                try { files = JSON.parse(doc.file_path); } catch(e) { files = []; }
                if (files.length) {
                    html += '<div style="display:flex;flex-wrap:wrap;gap:10px;">';
                    files.forEach(f => {
                        const isImage = f.type === 'image' || (f.path && /\.(jpg|jpeg|png|gif|webp|bmp)$/i.test(f.path));
                        if (isImage) {
                            html += '<a href="' + escHtml(f.path) + '" target="_blank" style="display:block;width:120px;height:120px;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;">'
                                + '<img src="' + escHtml(f.path) + '" style="width:100%;height:100%;object-fit:cover;" onerror="this.parentElement.innerHTML=\'📄 预览失败\';">'
                                + '</a>';
                        } else {
                            html += '<a href="' + escHtml(f.path) + '" target="_blank" download style="display:flex;align-items:center;gap:6px;padding:8px 12px;border:1px solid #e5e7eb;border-radius:10px;text-decoration:none;color:#374151;font-size:0.85rem;">'
                                + '📄 ' + escHtml(f.name || '下载文件')
                                + '</a>';
                        }
                    });
                    html += '</div>';
                }
            } else {
                html += '<p style="color:#9ca3af;">暂无附件</p>';
            }
            html += '</div>';
            document.getElementById('doc-detail-body').innerHTML = html;
            showOverlay('doc-detail-overlay');
        });
}

function closeDocDetail() {
    hideOverlay('doc-detail-overlay');
}

// ===== 钱包操作（管理员） =====
var _editingWalletKey = '';

function openWalletEdit(person_key, person_name, current_balance) {
    _editingWalletKey = person_key;
    document.getElementById('wallet-edit-user-info').innerHTML =
        '<strong>' + person_name + '</strong> 当前余额：<span style="color:#dc2626;font-weight:700;">¥' + current_balance + '</span>';
    document.getElementById('wallet-new-balance').value = current_balance;
    document.getElementById('wallet-edit-reason').value = '';
    showOverlay('wallet-edit-overlay');
}

function doClearWallet(person_key, person_name, current_balance) {
    if (!confirm('⚠️ 确定要清空「' + person_name + '」的钱包吗？\n\n当前余额：¥' + current_balance + '\n\n清空后：\n· 余额归零\n· 所有交易记录将被删除\n\n此操作不可恢复！')) return;
    var fd = new FormData();
    fd.append('action', 'clear_wallet');
    fd.append('person_key', person_key);
    fetch('api/wallet.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.success) {
                alert(d.msg);
                loadDashboard(); // 刷新数据
            } else {
                alert('❌ ' + (d.msg || '操作失败'));
            }
        })
        .catch(function(e) { console.error(e); alert('请求失败'); });
}

function doUpdateWallet() {
    var newBalanceStr = document.getElementById('wallet-new-balance').value;
    var newBalance = parseFloat(newBalanceStr);
    if (isNaN(newBalance) || newBalance < 0) {
        alert('请输入有效的金额（≥0）');
        return;
    }
    var fd = new FormData();
    fd.append('action', 'update_balance');
    fd.append('person_key', _editingWalletKey);
    fd.append('new_balance', newBalance);
    fd.append('reason', document.getElementById('wallet-edit-reason').value);
    fetch('api/wallet.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.success) {
                alert('✅ ' + d.msg);
                document.getElementById('wallet-edit-overlay').style.display = 'none';
                loadDashboard(); // 刷新数据
            } else {
                alert('❌ ' + (d.msg || '修改失败'));
            }
        })
        .catch(function(e) { console.error(e); alert('请求失败'); });
}
</script>

<!-- 修改钱包弹窗 -->
<div class="modal-overlay" id="wallet-edit-overlay" style="display:none;" onclick="if(event.target===this)this.style.display='none'">
  <div class="modal" style="width:420px;max-width:94%;">
    <div class="modal-header" style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid #eee;">
      <h3 style="font-size:1.05rem;margin:0;">🔧 修改钱包余额</h3>
      <button onclick="document.getElementById('wallet-edit-overlay').style.display='none'" style="background:none;border:none;font-size:1.5rem;cursor:pointer;color:#999;line-height:1;">&times;</button>
    </div>
    <div style="padding:20px;">
      <p style="margin-bottom:14px;color:#555;font-size:0.92rem;" id="wallet-edit-user-info">-</p>
      <div class="form-group">
        <label style="font-weight:600;font-size:0.9rem;display:block;margin-bottom:6px;">新余额（元）</label>
        <input type="number" id="wallet-new-balance" step="0.01" min="0" placeholder="输入新的钱包余额" style="width:100%;padding:10px 12px;border:2px solid #ddd;border-radius:8px;font-size:1rem;outline:none;transition:border-color 0.2s;" onfocus="this.style.borderColor='#7c3aed'" onblur="this.style.borderColor='#ddd'">
      </div>
      <div class="form-group">
        <label style="font-weight:600;font-size:0.9rem;display:block;margin-bottom:6px;">调整原因（可选）</label>
        <textarea id="wallet-edit-reason" rows="2" placeholder="如：账目核对修正、补录漏记..." style="width:100%;padding:10px 12px;border:2px solid #ddd;border-radius:8px;font-size:0.9rem;resize:vertical;outline:none;transition:border-color 0.2s;" onfocus="this.style.borderColor='#7c3aed'" onblur="this.style.borderColor='#ddd'"></textarea>
      </div>
      <div style="display:flex;gap:10px;margin-top:18px;">
        <button onclick="doUpdateWallet()" class="btn btn-primary" style="flex:1;padding:11px;border-radius:8px;font-weight:600;font-size:0.95rem;">✅ 确认修改</button>
        <button onclick="document.getElementById('wallet-edit-overlay').style.display='none'" class="btn" style="flex:1;padding:11px;border-radius:8px;background:#f3f4f6;color:#374151;font-weight:600;font-size:0.95rem;">取消</button>
      </div>
    </div>
  </div>
</div>

<script src="medical.js"></script>
<script>
// ===== 家庭支出功能 =====
function loadExpenses() {
    var search = document.getElementById('expense-search').value.trim();
    fetch('api/expenses.php?action=list')
        .then(function(r){return r.json();})
        .then(function(d){
            if (!d.success) { console.error('[expenses]', d.error); return; }
            var data = d.data || [];
            var stats = d.stats || {};
            // 更新统计
            document.getElementById('expense-stat-daily').textContent   = '¥' + parseFloat(stats.daily||0).toFixed(2);
            document.getElementById('expense-stat-month').textContent   = '¥' + parseFloat(stats.month||0).toFixed(2);
            document.getElementById('expense-stat-pending').textContent = '¥' + parseFloat(stats.pending||0).toFixed(2);
            // 新增：昨日、上月、总支出
            if (document.getElementById('expense-stat-yesterday')) {
                document.getElementById('expense-stat-yesterday').textContent = '¥' + parseFloat(stats.yesterday||0).toFixed(2);
            }
            if (document.getElementById('expense-stat-last-month')) {
                document.getElementById('expense-stat-last-month').textContent = '¥' + parseFloat(stats.lastMonth||0).toFixed(2);
            }
            if (document.getElementById('expense-stat-total')) {
                document.getElementById('expense-stat-total').textContent = '¥' + parseFloat(stats.total||0).toFixed(2);
            }

            var html = '';
            for (var i = 0; i < data.length; i++) {
                var e = data[i];
                if (search && (e.purpose||'').toLowerCase().indexOf(search.toLowerCase()) === -1) continue;
                var paidNames  = (e.paid_names||[]).join('、');
                var paidCount  = (e.paid_users||[]).length;
                var aaAmount   = parseFloat(e.aa_amount||0);
                var paidAmount = paidCount * aaAmount; // 已到款 = 已付款人数 × 每人AA
                var unpaidAmount = Math.max(0, parseFloat(e.amount||0) - paidAmount); // 未到款
                var aaUsers     = (e.aa_users||[]); // AA用户ID列表
                var isFullyPaid = (paidAmount >= parseFloat(e.amount||0));
                var statusText  = isFullyPaid
                    ? '<span style="color:#16a34a;font-weight:600;">✅ 已结清</span>'
                    : '<span style="color:#ea580c;font-weight:600;">⏳ 未结清</span>';
                // 只有被勾选的AA用户才能点"已付款"
                var canPay = aaUsers.indexOf(CURRENT_ID) !== -1;

                html += '<tr>'
                    + '<td style="font-weight:600;">' + escHtml(e.purpose) + '</td>'
                    + '<td>¥' + parseFloat(e.amount||0).toFixed(2) + '</td>'
                    + '<td>¥' + aaAmount.toFixed(2) + '</td>'
                    + '<td>¥' + paidAmount.toFixed(2) + '</td>'
                    + '<td>¥' + unpaidAmount.toFixed(2) + '</td>'
                    + '<td style="font-size:0.82rem;color:#374151;">' + (paidNames || '<span style="color:#d1d5db;">暂无</span>') + '</td>'
                    + '<td>' + escHtml(e.executor||'-') + '</td>'
                    + '<td>' + (e.expense_date||'-') + '</td>'
                    + '<td>' + statusText + '</td>'
                    + '<td style="white-space:nowrap;">'
                        + (canPay
                            ? '<button onclick="toggleExpensePaid(' + e.id + ')" class="btn" style="padding:3px 10px;font-size:0.75rem;margin-right:4px;background:#fef3c7;color:#92400e;border-color:#fde68a;">'
                              + ((e.paid_users||[]).indexOf(CURRENT_ID) !== -1 ? '取消付款' : '已付款')
                              + '</button>'
                            : '<span style="font-size:0.75rem;color:#9ca3af;">无权限</span>')
                        + '<button onclick="editExpense(' + e.id + ')" class="btn btn-primary" style="padding:3px 10px;font-size:0.75rem;margin-right:4px;">编辑</button>'
                        + '<button onclick="shareItem(\'expenses\',' + e.id + ',\'' + escHtml(e.purpose) + '\')" class="btn" style="padding:3px 10px;font-size:0.75rem;margin-right:4px;background:#dbeafe;color:#1e40af;border-color:#bfdbfe;">分享</button>'
                        + '<button onclick="deleteExpense(' + e.id + ')" class="btn btn-danger" style="padding:3px 10px;font-size:0.75rem;">删除</button>'
                    + '</td>'
                    + '</tr>';
            }
            document.getElementById('expense-list').innerHTML = html || '<tr><td colspan="10" style="text-align:center;color:#999;padding:40px;">暂无支出记录，点击"记一笔"添加 💸</td></tr>';
        })
        .catch(function(e) { console.error('[expenses]', e); });
}

var _expenseUsersLoaded = false;
var _expenseUserList = [];
function loadExpenseUsers() {
    if (_expenseUsersLoaded) return Promise.resolve();
    return fetch('api/expenses.php?action=get_users')
        .then(function(r){return r.json();})
        .then(function(d){
            if (!d.success) return;
            _expenseUserList = d.data || [];
            // 填充 AA 用户勾选框
            var box = document.getElementById('aa-users-checkboxes');
            box.innerHTML = '';
            _expenseUserList.forEach(function(u){
                var label = document.createElement('label');
                label.style.cssText = 'display:flex;align-items:center;gap:6px;padding:6px 12px;background:#fff;border-radius:8px;border:1px solid #e5e7eb;cursor:pointer;font-size:0.9rem;';
                label.innerHTML = '<input type="checkbox" name="aa_user" value="' + u.id + '" style="accent-color:#f59e0b;"> ' + escHtml(u.display_name);
                box.appendChild(label);
            });
            // 填充执行人下拉
            var sel = document.getElementById('expense-form-executor-id');
            sel.innerHTML = '<option value="0">请选择执行人</option>';
            _expenseUserList.forEach(function(u){
                var opt = document.createElement('option');
                opt.value = u.id;
                opt.textContent = u.display_name;
                sel.appendChild(opt);
            });
            _expenseUsersLoaded = true;
        });
}

function showExpenseForm(id) {
    document.getElementById('expense-form-id').value = id || '';
    document.getElementById('expense-form-purpose').value = '';
    document.getElementById('expense-form-amount').value = '';
    document.getElementById('expense-form-date').value = '';
    document.getElementById('expense-form-executor-id').value = '0';
    document.getElementById('expense-form-remark').value = '';
    document.getElementById('expense-form-receipts').value = '';
    document.getElementById('expense-form-title').textContent = id ? '💸 编辑支出' : '💸 记一笔支出';
    // 清空 AA 勾选
    var cbs = document.querySelectorAll('input[name="aa_user"]');
    for (var i = 0; i < cbs.length; i++) cbs[i].checked = false;
    // 默认勾选当前用户
    for (var i = 0; i < cbs.length; i++) {
        if (parseInt(cbs[i].value) === CURRENT_ID) { cbs[i].checked = true; break; }
    }
    loadExpenseUsers().then(function(){
        showOverlay('expense-form-overlay');
    });
}

function closeExpenseForm() {
    hideOverlay('expense-form-overlay');
}

function saveExpense(evt) {
    evt.preventDefault();
    var id       = document.getElementById('expense-form-id').value;
    var purpose  = document.getElementById('expense-form-purpose').value.trim();
    var amount   = document.getElementById('expense-form-amount').value;
    var date     = document.getElementById('expense-form-date').value;
    var execId   = document.getElementById('expense-form-executor-id').value;
    var remark   = document.getElementById('expense-form-remark').value.trim();

    if (!purpose || !amount) { alert('用途和金额不能为空'); return; }

    // 收集 AA 用户
    var aaUsers = [];
    var cbs = document.querySelectorAll('input[name="aa_user"]:checked');
    for (var i = 0; i < cbs.length; i++) aaUsers.push(parseInt(cbs[i].value));
    if (aaUsers.length === 0) { alert('请至少勾选一个AA用户'); return; }

    var fd = new FormData();
    fd.append('action',   id ? 'update' : 'create');
    if (id) fd.append('id', id);
    fd.append('purpose',  purpose);
    fd.append('amount',   amount);
    fd.append('aa_users', JSON.stringify(aaUsers));
    fd.append('executor_id', execId);
    fd.append('expense_date', date);
    fd.append('remark',   remark);

    // 附件
    var filesInput = document.getElementById('expense-form-receipts');
    for (var i = 0; i < filesInput.files.length; i++) {
        fd.append('receipts[]', filesInput.files[i]);
    }

    fetch('api/expenses.php', { method:'POST', body: fd })
        .then(function(r){return r.json();})
        .then(function(d){
            if (d.success) {
                closeExpenseForm();
                loadExpenses();
            } else {
                alert('操作失败：' + (d.error||'未知错误'));
            }
        })
        .catch(function(e){ console.error('[expenses]', e); alert('网络错误'); });
}

function editExpense(id) {
    fetch('api/expenses.php?action=list')
        .then(function(r){return r.json();})
        .then(function(d){
            if (!d.success) return;
            var data = d.data || [];
            var e = null;
            for (var i = 0; i < data.length; i++) { if (data[i].id == id) { e = data[i]; break; } }
            if (!e) { alert('记录不存在'); return; }
            document.getElementById('expense-form-id').value = e.id;
            document.getElementById('expense-form-purpose').value  = e.purpose || '';
            document.getElementById('expense-form-amount').value    = e.amount || '';
            document.getElementById('expense-form-date').value     = e.expense_date || '';
            document.getElementById('expense-form-remark').value   = e.remark || '';
            document.getElementById('expense-form-receipts').value = '';
            document.getElementById('expense-form-title').textContent = '💸 编辑支出';
            // 加载用户列表后再预选
            loadExpenseUsers().then(function(){
                // 预选 AA 用户
                var aaUsers = [];
                if (e.aa_users) {
                    aaUsers = Array.isArray(e.aa_users) ? e.aa_users : JSON.parse(e.aa_users || '[]');
                }
                var cbs = document.querySelectorAll('input[name="aa_user"]');
                for (var i = 0; i < cbs.length; i++) {
                    cbs[i].checked = (aaUsers.indexOf(parseInt(cbs[i].value)) !== -1);
                }
                // 预选执行人（按名字匹配）
                if (e.executor) {
                    var sel = document.getElementById('expense-form-executor-id');
                    for (var i = 0; i < sel.options.length; i++) {
                        if (sel.options[i].text === e.executor) {
                            sel.selectedIndex = i;
                            break;
                        }
                    }
                }
                showOverlay('expense-form-overlay');
            });
        });
}

function deleteExpense(id) {
    if (!confirm('确定删除此支出记录？')) return;
    var fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id', id);
    fetch('api/expenses.php', { method:'POST', body: fd })
        .then(function(r){return r.json();})
        .then(function(d){
            if (d.success) { loadExpenses(); }
            else { alert('删除失败：' + (d.error||'未知错误')); }
        })
        .catch(function(e){ console.error('[expenses]', e); });
}

function toggleExpensePaid(id) {
    var fd = new FormData();
    fd.append('action', 'toggle_paid');
    fd.append('id', id);
    fetch('api/expenses.php', { method:'POST', body: fd })
        .then(function(r){return r.json();})
        .then(function(d){
            if (d.success) { loadExpenses(); }
            else { alert('操作失败：' + (d.error||'未知错误')); }
        })
        .catch(function(e){ console.error('[expenses]', e); });
}

// ===== 分享功能 =====
function shareItem(module, id, title) {
    var baseUrl = location.origin + location.pathname;
    var shareUrl = baseUrl + '?to=' + encodeURIComponent(module) + '&id=' + encodeURIComponent(id);
    // 复制内容：标题 + 链接
    var shareText = '【家庭管理系统分享】\n'
        + '📌 ' + (title || '点击查看详情') + '\n'
        + '🔗 ' + shareUrl;

    // 尝试使用 Web Share API（手机端，支持微信分享）
    if (navigator.share) {
        navigator.share({
            title: title || '家庭管理系统',
            text: shareText,
            url: shareUrl
        }).catch(function(){});
        return;
    }

    // 复制到剪贴板（内容+链接一起复制）
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(shareText).then(function(){
            alert('✅ 分享内容已复制！\n\n你可以粘贴到其他地方分享');
        }).catch(function(){
            prompt('请手动复制分享内容：', shareText);
        });
    } else {
        prompt('请手动复制分享内容：', shareText);
    }
}
</script>
</body>
</html>
