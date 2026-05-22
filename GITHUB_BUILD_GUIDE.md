# GitHub Actions 自动编译 iOS IPA — 操作指南

## 概述

使用 GitHub 免费的 macOS 虚拟机自动编译 IPA，全程约 **8-12 分钟**，无需 Mac 电脑。

---

## 第一步：创建 GitHub 仓库

1. 打开 https://github.com/new
2. **Repository name**：填 `family-app`（随便取）
3. **Description**：可选，填"家庭管理系统 iOS APP"
4. 勾选 **Add a README file**
5. 点下方绿色按钮 **Create repository**

---

## 第二步：上传项目文件

### 方式 A：网页上传（最简单）

1. 进入刚创建的仓库页面
2. 点右上角 **"Add file"** → **"Upload files"**
3. 把以下文件/文件夹**全部拖进去**：

```
family-android 文件夹里的：
├── capacitor.config.json
├── app-shell/              （整个文件夹）
├── index.php
├── api/                    （整个文件夹）
├── config.php
├── config.custom.php       （如果有）
├── static/                 （整个文件夹）
├── uploads/                （整个文件夹）
├── medical.js
├── version.json
├── app-icon-1024.png
├── init_db.sql
├── .github/workflows/build-ios.yml   （GitHub Actions 配置文件）
```

4. 页面往下拉，点 **"Commit changes"**

> ⚠️ 注意：android/ 文件夹不需要传，太大了。

### 方式 B：命令行上传（会用 git 的话更快）

```bash
# 进入项目目录
cd family-android

# 初始化 git
git init
git add -A
git commit -m "init"

# 关联远程仓库（把下面 URL 换成你的）
git remote add origin https://github.com/你的用户名/family-app.git

# 推送
git branch -M main
git push -u origin main
```

---

## 第三步：触发编译

1. 回到仓库主页，点上方 **"Actions"** 标签
2. 左侧找到 **"Build iOS IPA"**，点一下
3. 右侧点 **"Run workflow"** 下拉按钮 → 再点绿色 **"Run workflow"**
4. 页面刷新，看到一条正在运行的记录（黄色圆圈）

---

## 第四步：等待并下载 IPA

1. 点进正在运行的那条记录
2. 等待约 **8-12 分钟**（可以看到每个步骤的执行日志）
3. 全部变绿色 ✅ 后，页面上方会出现 **"Artifacts"** 区域
4. 找到 **"家庭管理系统-iOS"**，点一下下载 zip
5. 解压 zip，得到 `家庭管理系统-unsigned.ipa`

---

## 第五步：全能签签名安装

1. 把 IPA 传到手机（微信文件传输、AirDrop、百度网盘等）
2. 打开 **全能签 / 轻松签 / 牛蛙助手**
3. 导入 IPA 文件
4. 用自己的 Apple ID **签名**
5. 安装到手机

> 💡 首次安装需要在手机：`设置` → `通用` → `VPN与设备管理` → 信任你的 Apple ID

---

## 后续更新（服务器代码更新后）

因为 APP 加载的是远程服务器，**不需要重新编译 IPA**。

只需要修改服务器上的 `version.json` 版本号，APP 会自动检测并提示刷新。

如果一定要重新编译（比如改 APP 名称、图标）：
1. 修改代码
2. 重新推送到 GitHub
3. 去 Actions 页面点 **"Run workflow"**
4. 等 10 分钟下载新的 IPA

---

## 常见问题

**Q: 编译失败怎么办？**
- 点进失败的记录，看红色 ❌ 那一步的日志
- 把错误信息复制发给我

**Q: 下载的 IPA 安装不了？**
- 全能签签名时需要登录你的 Apple ID
- 免费账号签名的 IPA 有效期 7 天，到期重新签名即可

**Q: 想改 APP 名称/图标？**
- 改 `capacitor.config.json` 里的 `appName`
- 替换 `app-icon-1024.png`（需要重新编译）
