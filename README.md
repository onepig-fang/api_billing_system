# 聚合解析计费系统

基于 **ThinkPHP 8** 开发的视频聚合解析 API 计费平台，提供前台用户中心、后台管理、API 解析接口和 Web 安装向导，适用于视频解析服务的账号管理、套餐计费、充值支付、采集接口授权等场景。

## 项目特点

- 基于 ThinkPHP 8 多应用模式开发
- 提供前台、后台、API、安装向导四套独立入口
- 支持视频解析接口管理与按套餐计费
- 支持包点 / 包月两种计费模式
- 支持用户注册、登录、邀请、黑名单、IP 授权
- 支持卡密充值、在线充值、套餐购买
- 支持 CMS 采集接口授权管理
- 支持 M3U8 代理、播放器页面、用户中心调试
- 支持多套前台模板与后台文章公告管理

## 技术栈

- PHP 8.0+
- ThinkPHP 8
- ThinkORM 3/4
- ThinkPHP 多应用模式
- MySQL / MariaDB
- Redis（队列 / 可选缓存）
- PHPMailer
- think-captcha
- think-cors
- Layui / jQuery / Think 模板引擎

## 运行环境

### 基础要求

- PHP >= 8.0（建议 8.2）
- MySQL 5.7+ / MariaDB 10.3+
- Composer
- Web 服务器：Nginx / Apache

### 建议安装的 PHP 扩展

- `mysqli`
- `curl`
- `fileinfo`
- `exif`
- `openssl`
- `gd`（验证码相关）
- `zip`（打包下载相关）
- `redis`（如启用 Redis 队列或缓存）

## 项目结构

```text
聚合解析计费开源/
├── app/                    应用目录
│   ├── admin/              后台应用
│   ├── api/                API 应用
│   ├── common/             公共模型与公共库
│   ├── home/               前台应用
│   └── install/            安装向导应用
├── config/                 配置目录
├── public/                 Web 根目录
│   ├── index.php           前台入口
│   ├── admin.php           后台入口
│   ├── api.php             API 入口
│   └── install.php         安装入口
├── route/                  路由目录
├── runtime/                运行时目录
├── vendor/                 Composer 依赖
├── view/                   前后台模板目录
├── install.lock            安装锁文件
└── README.md
```

## 主要模块

### 1. 前台模块 `app/home`

主要提供：

- 首页展示
- 用户注册 / 登录 / 找回密码
- 用户中心
- 余额充值 / 卡密兑换
- 套餐购买
- 邀请推广
- API Key 管理
- 调用记录查询
- 解析 / 采集 IP 授权配置

核心控制器：

- `app/home/controller/Index.php`
- `app/home/controller/User.php`
- `app/home/controller/Shop.php`
- `app/home/controller/Pay.php`
- `app/home/controller/Ajax.php`
- `app/home/controller/Article.php`

### 2. 后台模块 `app/admin`

主要提供：

- 管理员登录
- 数据统计仪表盘
- 用户管理
- 代理管理
- 套餐与解析接口管理
- 卡密管理
- 采集接口管理
- 文章公告管理
- 模板管理
- 系统设置
- 工单 / 求片 / 邀请 / 记录管理

核心控制器：

- `app/admin/controller/Login.php`
- `app/admin/controller/Index.php`
- `app/admin/controller/User.php`
- `app/admin/controller/Shop.php`
- `app/admin/controller/Set.php`
- `app/admin/controller/Card.php`
- `app/admin/controller/Agent.php`
- `app/admin/controller/Cmsapi.php`
- `app/admin/controller/Article.php`

### 3. API 模块 `app/api`

主要提供：

- 视频解析主接口
- JSON 解析返回
- 播放器页面输出
- 配置查询接口
- CMS 采集接口
- M3U8 代理相关接口
- 第三方快捷登录接口

核心控制器：

- `app/api/controller/Index.php`
- `app/api/controller/Set.php`
- `app/api/controller/Cms.php`
- `app/api/controller/M3u8.php`
- `app/api/controller/Quick.php`

### 4. 安装模块 `app/install`

主要用于：

- 环境检测
- 数据库初始化
- 生成环境配置
- 创建安装锁文件

## 入口说明

本项目使用多入口方式部署：

| 入口文件 | 说明 |
|---|---|
| `public/index.php` | 前台首页与用户中心 |
| `public/admin.php` | 后台管理 |
| `public/api.php` | API 接口 |
| `public/install.php` | 安装向导 |

说明：

- `public/index.php` 与 `public/admin.php` 会检查根目录 `install.lock`
- 如果没有 `install.lock`，会自动跳转到 `/install.php`

## 安装部署

### 方式一：Web 安装向导（推荐）

1. 上传项目代码到服务器
2. 安装 Composer 依赖：

```bash
composer install
```

3. 配置站点根目录到 `public/`
4. 确保根目录不存在 `install.lock`（首次安装一般没有）
5. 浏览器访问：

```text
http://你的域名/install.php
```

6. 按页面提示完成数据库配置与初始化
7. 安装完成后系统会生成 `install.lock`

### 方式二：手动部署

1. 安装依赖：

```bash
composer install
```

2. 配置数据库连接（通常通过 `.env`）
3. 导入安装 SQL（如使用安装模块提供的 SQL）
4. 确保根目录存在 `install.lock`
5. 配置站点根目录到 `public/`

## 常用访问地址

| 地址 | 说明 |
|---|---|
| `/` | 前台首页 |
| `/admin.php` | 后台入口 |
| `/api.php` | API 入口 |
| `/install.php` | 安装入口 |

## 开发运行

### 使用 ThinkPHP 命令启动

```bash
php think run
```

### 或使用 PHP 内置服务器

```bash
php -S 127.0.0.1:8000 -t public public/router.php
```

## 队列与缓存

项目已集成 `topthink/think-queue`，当前 `config/queue.php` 默认使用 `redis` 驱动。

如果启用队列监听，可使用：

```bash
php think queue:listen
```

如不使用 Redis 队列，请根据实际环境调整 `config/queue.php` 与缓存配置。

## 数据与配置说明

### 数据库

- 默认数据库驱动为 `mysql`
- `config/database.php` 默认表前缀为 `sk_`
- 实际运行时通常可通过 `.env` 覆盖数据库配置

### 默认应用

`config/app.php` 中默认应用为：

```php
'default_app' => 'home'
```

### 路由说明

当前 `route/app.php` 只保留了少量 ThinkPHP 示例路由，项目主要依赖：

- 多应用入口文件
- 控制器约定路由

## 重要目录补充说明

### `app/common/model`

该目录包含主要业务模型，例如：

- `Admin.php`
- `Users.php`
- `Setting.php`
- `Shops.php`
- `Json.php`
- `Recharge.php`
- `Cards.php`
- `Userlogs.php`
- `Cmsapis.php`
- `Invite.php`
- `Blacklist.php`
- `News.php`

### `public/drbfq`

该目录包含播放器与相关资源，项目中存在用户下载 / 打包播放器的相关逻辑。

### `view/`

前后台页面模板主要位于根目录 `view/` 下，例如：

- `view/admin/`
- `view/home/`
- `view/error/`

此外，安装向导页面位于 `app/install/view/`。

## 默认账号说明

安装器 `app/install/controller/Index.php` 默认写入的管理员账号为：

- 管理员账号：`admin`
- 初始密码：安装时填写的密码

但需要注意：当前代码中安装器将后台密码写为 `md5(md5($pwd))`，而后台登录校验使用的是 `md5($password)`，两处逻辑并不完全一致。

因此在实际部署时请注意：

- 不要完全依赖历史文档中的默认账号密码说明
- 以你实际导入的数据库内容和安装填写信息为准
- 部署完成后请立即重置后台密码并自行验证登录流程

## 部署建议

- 生产环境关闭调试模式
- 部署后及时修改后台默认账号密码
- 保护好 `.env`、数据库备份、SQL 压缩包等敏感文件
- 建议限制 `/install.php` 在生产环境的访问
- 确保 `runtime/` 目录可写
- 若启用 HTTPS，可更好保护 API Key 与登录态
- 如启用 Redis 队列，请确认 Redis 服务可用

## 已知说明

- 根目录原 README 为 ThinkPHP 官方默认说明，已不适用于当前项目
- 项目中存在部分历史备份模板、旧文件与额外入口文件，部署前建议自行审查与清理
- `public/api.php`、`public/install.php` 中仍保留旧版 PHP 版本提示代码，但 Composer 依赖要求为 PHP 8.0+

## License

本项目遵循仓库内 `LICENSE.txt` 说明。
