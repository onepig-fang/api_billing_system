<?php

declare(strict_types=1);

namespace app\install\controller;

use app\BaseController;
use think\facade\Db;
use think\facade\Log;
use think\facade\View;
use think\Request;

/**
 * 系统安装控制器
 */
class Index extends BaseController
{
    /**
     * 安装锁文件路径
     */
    protected $lockFile = '';
    
    /**
     * SQL文件路径
     */
    protected $sqlFile = '';
    
    /**
     * 环境配置文件路径
     */
    protected $envFile = '';
    
    /**
     * 初始化
     */
    protected function initialize()
    {
        parent::initialize();
        
        $this->lockFile = root_path() . 'install.lock';
        $this->sqlFile = app_path() . 'install.sql';  // SQL文件在当前应用目录下
        $this->envFile = root_path() . '.env';
    }
    
    /**
     * 安装首页
     */
    public function index()
    {
        // 检查是否已安装
        if (file_exists($this->lockFile)) {
            return $this->showMessage('系统已安装，如需重新安装请删除install.lock文件');
        }
        
        return view('index/index');
    }
    
    /**
     * 步骤1：检查环境
     */
    public function step1(Request $request)
    {
        // 检查是否已安装
        if (file_exists($this->lockFile)) {
            return redirect('/');
        }
        
        // POST请求返回JSON（下一步按钮）
        if ($request->isPost()) {
            return json(['code' => 200, 'msg' => 'success', 'url' => '/install.php/index/step2']);
        }
        
        // 检查环境（与模板变量名匹配）
        $checkenv = [
            'php' => PHP_VERSION,
            'mysqli' => extension_loaded('mysqli') ? 1 : 0,
            'redis' => extension_loaded('redis') ? 1 : 0,
            'curl' => extension_loaded('curl') ? 1 : 0,
            'fileinfo' => extension_loaded('fileinfo') ? 1 : 0,
            'exif' => extension_loaded('exif') ? 1 : 0,
        ];
        
        // SG扩展检查（已移除加密，设为1表示通过）
        $sg_get_version = 1;
        
        // 检查目录权限
        $checkdirfile = [
            ['dir', 'layui-icon-ok-circle', 'layui-icon-ok-circle', './'],
            ['dir', 'layui-icon-ok-circle', 'layui-icon-ok-circle', './public'],
            ['dir', 'layui-icon-ok-circle', 'layui-icon-ok-circle', './runtime'],
            ['dir', 'layui-icon-ok-circle', 'layui-icon-ok-circle', './extend'],
        ];
        
        // 实际检查目录权限
        $dirs = [
            './' => root_path(),
            './public' => public_path(),
            './runtime' => runtime_path(),
            './extend' => root_path() . 'extend/',
        ];
        
        $i = 0;
        foreach ($dirs as $name => $path) {
            if (!is_writable($path)) {
                $checkdirfile[$i][1] = 'layui-icon-close-fill';
            }
            if (!is_readable($path)) {
                $checkdirfile[$i][2] = 'layui-icon-close-fill';
            }
            $i++;
        }
        
        View::assign([
            'checkenv' => $checkenv,
            'sg_get_version' => $sg_get_version,
            'checkdirfile' => $checkdirfile,
        ]);
        
        return view('index/step1');
    }
    
    /**
     * 步骤2：配置数据库（显示表单）
     */
    public function step2(Request $request)
    {
        // 检查是否已安装
        if (file_exists($this->lockFile)) {
            return redirect('/');
        }
        
        if (!$request->isPost()) {
            return view('index/step2');
        }
        
        // 获取表单数据
        $data = $request->post();
        
        $hostname = trim((string)($data['hostname'] ?? '127.0.0.1'));
        $hostport = trim((string)($data['hostport'] ?? '3306'));
        $database = trim((string)($data['database'] ?? ''));
        $username = trim((string)($data['username'] ?? ''));
        $password = (string)($data['password'] ?? '');
        $prefix = trim((string)($data['prefix'] ?? 'sa_'));
        $adminUser = trim((string)($data['user'] ?? 'admin'));
        $adminPwd = (string)($data['pwd'] ?? '');
        $adminQQ = trim((string)($data['qq'] ?? ''));
        
        // 验证必填项
        if (empty($database) || empty($username) || empty($adminUser) || empty($adminPwd)) {
            return json(['code' => 0, 'msg' => '请填写完整的配置信息']);
        }

        if (!$this->isSafeHost($hostname) || !$this->isSafePort($hostport)) {
            return json(['code' => 0, 'msg' => '数据库主机或端口格式不正确']);
        }

        if (!$this->isSafeDbIdentifier($database) || !$this->isSafeDbIdentifier($prefix)) {
            return json(['code' => 0, 'msg' => '数据库名或表前缀格式不正确']);
        }

        if (!preg_match('/^[a-zA-Z0-9_\-]{3,32}$/', $adminUser)) {
            return json(['code' => 0, 'msg' => '管理员账号格式不正确']);
        }

        if ($adminQQ !== '' && !preg_match('/^[0-9]{5,20}$/', $adminQQ)) {
            return json(['code' => 0, 'msg' => '客服QQ格式不正确']);
        }
        
        // 测试数据库连接
        try {
            $dsn = "mysql:host={$hostname};port={$hostport};charset=utf8mb4";
            $pdo = new \PDO($dsn, $username, $password);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            
            // 创建数据库（如果不存在）
            $pdo->exec('CREATE DATABASE IF NOT EXISTS ' . $this->quoteIdentifier($database) . ' DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $pdo->exec('USE ' . $this->quoteIdentifier($database));
            
        } catch (\PDOException $e) {
            Log::error('install step2 database connection failed', [
                'error' => $e->getMessage(),
                'host' => $hostname,
                'port' => $hostport,
                'database' => $database,
            ]);
            return json(['code' => 0, 'msg' => '数据库连接失败，请检查数据库配置']);
        }
        
        // 读取并执行SQL文件
        $currentIndex = 0;
        $currentStatement = '';
        try {
            if ($this->sqlFile === '' || !is_file($this->sqlFile)) {
                throw new \RuntimeException('SQL文件不存在: ' . $this->sqlFile);
            }

            $sql = file_get_contents($this->sqlFile);
            if ($sql === false) {
                throw new \RuntimeException('SQL文件读取失败: ' . $this->sqlFile);
            }

            // 替换表前缀
            $sql = str_replace('__PREFIX__', $prefix, $sql);

            // 分割SQL语句
            $statements = $this->splitSql($sql);

            foreach ($statements as $i => $statement) {
                $statement = trim($statement);
                if (!empty($statement)) {
                    $currentIndex = $i + 1;
                    $currentStatement = $statement;
                    $pdo->exec($statement);
                }
            }

            // 更新管理员信息
            $currentStatement = 'UPDATE ' . $prefix . 'admin';
            $adminPassword = secure_password_hash($adminPwd);
            $adminTable = $this->quoteIdentifier($prefix . 'admin');
            $stmtAdmin = $pdo->prepare("UPDATE {$adminTable} SET `username` = :username, `password` = :password WHERE `id` = 1");
            $stmtAdmin->bindValue(':username', $adminUser, \PDO::PARAM_STR);
            $stmtAdmin->bindValue(':password', $adminPassword, \PDO::PARAM_STR);
            $stmtAdmin->execute();

            // 更新设置表中的QQ
            $currentStatement = 'UPDATE ' . $prefix . 'setting';
            $settingTable = $this->quoteIdentifier($prefix . 'setting');
            $stmtSetting = $pdo->prepare("UPDATE {$settingTable} SET `kefuqq` = :kefuqq WHERE `id` = 1");
            $stmtSetting->bindValue(':kefuqq', $adminQQ, \PDO::PARAM_STR);
            $stmtSetting->execute();

        } catch (\Throwable $e) {
            // 截取出错语句片段，便于快速定位
            $snippet = mb_substr(preg_replace('/\s+/', ' ', $currentStatement), 0, 200);

            // 具体错误写入日志正文（不依赖日志渠道是否记录上下文数组）
            $detail = sprintf(
                'install step2 database init failed | error=%s | 第%d条SQL | 语句片段: %s',
                $e->getMessage(),
                $currentIndex,
                $snippet
            );
            Log::error($detail, [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'statement_index' => $currentIndex,
                'statement_snippet' => $snippet,
                'database' => $database,
                'prefix' => $prefix,
                'sqlFile' => $this->sqlFile,
            ]);

            return json([
                'code' => 0,
                'msg' => '数据库初始化失败：' . $e->getMessage() . '（第' . $currentIndex . '条SQL）',
                'detail' => $snippet,
            ]);
        }
        
        // 生成.env配置文件
        $envContent = $this->generateEnvContent($hostname, $hostport, $database, $username, $password, $prefix);
        
        if (!file_put_contents($this->envFile, $envContent)) {
            return json(['code' => 0, 'msg' => '配置文件写入失败，请检查目录权限']);
        }
        
        // 创建安装锁文件
        file_put_contents($this->lockFile, date('Y-m-d H:i:s'));
        
        return json([
            'code' => 200,
            'msg' => '安装成功',
            'url' => '/install.php/index/step3'
        ]);
    }
    
    /**
     * 步骤3：安装完成
     */
    public function step3()
    {
        return view('index/step3');
    }
    
    /**
     * 开始安装（step3页面JS调用）
     */
    public function install()
    {
        // 安装已在step2完成，这里直接返回成功
        return json(['code' => 200, 'msg' => '开始安装...']);
    }
    
    /**
     * 获取安装进度（step3页面JS调用）
     */
    public function progress(Request $request)
    {
        $key = (int)$request->get('key', 1);
        
        // 模拟安装进度
        $steps = [
            1 => '初始化数据库连接...',
            2 => '创建用户表...',
            3 => '创建配置表...',
            4 => '创建订单表...',
            5 => '创建卡密表...',
            6 => '创建日志表...',
            7 => '导入默认数据...',
            8 => '创建管理员账户...',
            9 => '配置系统参数...',
            10 => '安装完成！',
        ];
        
        $total = count($steps);
        $progress = min(100, ($key / $total) * 100);
        
        if ($key > $total) {
            return json([
                'code' => 200,
                'msg' => '安装完成！',
                'key' => $key,
                'total' => $total,
                'progress' => '100%'
            ]);
        }
        
        return json([
            'code' => 200,
            'msg' => $steps[$key] ?? '',
            'key' => $key,
            'total' => $total,
            'progress' => round($progress) . '%'
        ]);
    }
    
    /**
     * 清理并生成后台入口文件（step3页面JS调用）
     */
    public function clear(Request $request)
    {
        // 安装完成后本方法必须关闭：否则任何人都能反复调用它在 webroot 下
        // 生成任意名称的后台入口文件（安装向导本身建议改名入口，此处会让改名失去意义）。
        if (file_exists($this->lockFile)) {
            return json(['code' => 403, 'msg' => '系统已安装，该接口已关闭']);
        }

        $loginfile = $request->get('loginfile', 'admin.php');

        // 安全检查文件名
        if (!preg_match('/^[a-zA-Z0-9]+\.php$/', $loginfile)) {
            $loginfile = 'admin.php';
        }
        
        // 复制admin.php为新的后台入口文件
        $sourceFile = public_path() . 'admin.php';
        $targetFile = public_path() . $loginfile;
        
        if (file_exists($sourceFile) && $loginfile !== 'admin.php') {
            copy($sourceFile, $targetFile);
        }
        
        return json(['code' => 200, 'msg' => 'success', 'file' => $loginfile]);
    }
    
    /**
     * 生成.env配置文件内容
     */
    protected function generateEnvContent($hostname, $hostport, $database, $username, $password, $prefix): string
    {
        return <<<ENV
app_debug = false
app_trace = false

[APP]
DEFAULT_TIMEZONE = Asia/Shanghai

[DATABASE]
TYPE = mysql
HOSTNAME = {$hostname}
DATABASE = {$database}
USERNAME = {$username}
PASSWORD = {$password}
HOSTPORT = {$hostport}
CHARSET = utf8mb4
PREFIX = {$prefix}
DEBUG = false

[CACHE]
DRIVER = file
HOSTNAME = 127.0.0.1
HOSTPORT = 6379
SELECT = 1
USERNAME =
PASSWORD =

[LANG]
default_lang = zh-CN

ENV;
    }

    protected function isSafeHost(string $host): bool
    {
        if ($host === '') {
            return false;
        }

        return (bool)preg_match('/^[a-zA-Z0-9._:-]+$/', $host);
    }

    protected function isSafePort(string $port): bool
    {
        if ($port === '' || !ctype_digit($port)) {
            return false;
        }

        $portNumber = (int)$port;
        return $portNumber > 0 && $portNumber <= 65535;
    }

    protected function isSafeDbIdentifier(string $identifier): bool
    {
        return (bool)preg_match('/^[a-zA-Z0-9_]+$/', $identifier);
    }

    protected function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }
    
    /**
     * 分割SQL语句
     */
    protected function splitSql(string $sql): array
    {
        $sql = preg_replace('/--.*$/m', '', $sql);
        $sql = preg_replace('/\/\*[\s\S]*?\*\//', '', $sql);
        
        $statements = [];
        $currentStatement = '';
        $inString = false;
        $stringChar = '';
        
        for ($i = 0; $i < strlen($sql); $i++) {
            $char = $sql[$i];
            
            if ($inString) {
                $currentStatement .= $char;
                if ($char === $stringChar && ($i === 0 || $sql[$i - 1] !== '\\')) {
                    $inString = false;
                }
            } else {
                if ($char === '"' || $char === "'") {
                    $inString = true;
                    $stringChar = $char;
                    $currentStatement .= $char;
                } elseif ($char === ';') {
                    $currentStatement = trim($currentStatement);
                    if (!empty($currentStatement)) {
                        $statements[] = $currentStatement;
                    }
                    $currentStatement = '';
                } else {
                    $currentStatement .= $char;
                }
            }
        }
        
        // 添加最后一条语句
        $currentStatement = trim($currentStatement);
        if (!empty($currentStatement)) {
            $statements[] = $currentStatement;
        }
        
        return $statements;
    }
    
    /**
     * 用户协议页面
     */
    public function user_agreen()
    {
        return view('index/user_agreen');
    }
    
    /**
     * 显示消息页面
     */
    protected function showMessage(string $message): string
    {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>提示信息</title>
    <style>
        body { font-family: Arial, sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; background: #f5f5f5; }
        .box { background: white; padding: 30px 50px; border-radius: 10px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .msg { color: #333; font-size: 16px; }
        a { color: #3498db; text-decoration: none; margin-top: 20px; display: inline-block; }
    </style>
</head>
<body>
    <div class="box">
        <div class="msg">' . htmlspecialchars($message) . '</div>
        <a href="/">返回首页</a>
    </div>
</body>
</html>';
    }
}
