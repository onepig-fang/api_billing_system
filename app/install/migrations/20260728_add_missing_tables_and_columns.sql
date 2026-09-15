SET NAMES utf8mb4;

-- 为已安装数据库补齐缺失的表与字段。
-- 使用方法：将 __PREFIX__ 替换为你的数据表前缀后执行（例如 sk_）。
-- 本迁移幂等：重复执行不会报错。

-- ========================================================================
-- 1. user 表：补齐代理归属字段 sjuser（admin.id），并加索引
-- ========================================================================
SET @user_table = '__PREFIX__user';

SET @exists_col = (
    SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @user_table
      AND COLUMN_NAME = 'sjuser'
);
SET @sql = IF(@exists_col > 0, 'SELECT 1',
    CONCAT('ALTER TABLE `', @user_table, '` ADD COLUMN `sjuser` INT(11) NULL DEFAULT NULL COMMENT ''所属代理管理员ID（admin.id）'' AFTER `sj`'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists_idx = (
    SELECT COUNT(1) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @user_table
      AND INDEX_NAME = 'sjuser'
);
SET @sql = IF(@exists_idx > 0, 'SELECT 1',
    CONCAT('ALTER TABLE `', @user_table, '` ADD INDEX `sjuser`(`sjuser`)'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ========================================================================
-- 2. setting 表：补齐易支付与支付宝当面付字段
-- ========================================================================
SET @setting_table = '__PREFIX__setting';

SET @exists_col = (
    SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @setting_table
      AND COLUMN_NAME = 'epay_api'
);
SET @sql = IF(@exists_col > 0, 'SELECT 1',
    CONCAT('ALTER TABLE `', @setting_table, '` ADD COLUMN `epay_api` MEDIUMTEXT NULL COMMENT ''易支付接口地址'''));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists_col = (
    SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @setting_table
      AND COLUMN_NAME = 'epay_pid'
);
SET @sql = IF(@exists_col > 0, 'SELECT 1',
    CONCAT('ALTER TABLE `', @setting_table, '` ADD COLUMN `epay_pid` VARCHAR(255) NULL DEFAULT NULL COMMENT ''易支付商户ID'''));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists_col = (
    SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @setting_table
      AND COLUMN_NAME = 'epay_key'
);
SET @sql = IF(@exists_col > 0, 'SELECT 1',
    CONCAT('ALTER TABLE `', @setting_table, '` ADD COLUMN `epay_key` VARCHAR(255) NULL DEFAULT NULL COMMENT ''易支付商户密钥'''));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists_col = (
    SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @setting_table
      AND COLUMN_NAME = 'whatpay'
);
SET @sql = IF(@exists_col > 0, 'SELECT 1',
    CONCAT('ALTER TABLE `', @setting_table, '` ADD COLUMN `whatpay` INT(1) NOT NULL DEFAULT 1 COMMENT ''支付方式 1易支付 2支付宝当面付'''));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists_col = (
    SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @setting_table
      AND COLUMN_NAME = 'public_key'
);
SET @sql = IF(@exists_col > 0, 'SELECT 1',
    CONCAT('ALTER TABLE `', @setting_table, '` ADD COLUMN `public_key` MEDIUMTEXT NULL COMMENT ''支付宝公钥'''));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists_col = (
    SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @setting_table
      AND COLUMN_NAME = 'private_key'
);
SET @sql = IF(@exists_col > 0, 'SELECT 1',
    CONCAT('ALTER TABLE `', @setting_table, '` ADD COLUMN `private_key` MEDIUMTEXT NULL COMMENT ''支付宝私钥'''));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists_col = (
    SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @setting_table
      AND COLUMN_NAME = 'appid'
);
SET @sql = IF(@exists_col > 0, 'SELECT 1',
    CONCAT('ALTER TABLE `', @setting_table, '` ADD COLUMN `appid` MEDIUMTEXT NULL COMMENT ''支付宝appid'''));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 打包 API 字段（部分历史库使用 pack_api_id，此处补齐代码实际读取的 appid/secret）
SET @exists_col = (
    SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @setting_table
      AND COLUMN_NAME = 'pack_api_appid'
);
SET @sql = IF(@exists_col > 0, 'SELECT 1',
    CONCAT('ALTER TABLE `', @setting_table, '` ADD COLUMN `pack_api_appid` VARCHAR(255) NULL DEFAULT NULL COMMENT ''外部打包API AppID'''));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists_col = (
    SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @setting_table
      AND COLUMN_NAME = 'pack_api_secret'
);
SET @sql = IF(@exists_col > 0, 'SELECT 1',
    CONCAT('ALTER TABLE `', @setting_table, '` ADD COLUMN `pack_api_secret` VARCHAR(255) NULL DEFAULT NULL COMMENT ''外部打包API Secret'''));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ========================================================================
-- 3. 补齐缺失的日志/分组表
-- ========================================================================
CREATE TABLE IF NOT EXISTS `__PREFIX__shop_log` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `uid` int(11) NOT NULL DEFAULT '0' COMMENT '用户UID',
  `shop_id` int(11) NOT NULL DEFAULT '0' COMMENT '套餐ID',
  `shop_name` varchar(255) DEFAULT NULL COMMENT '套餐名称',
  `price` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '购买价格',
  `time` datetime DEFAULT NULL COMMENT '购买时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `uid`(`uid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='套餐购买记录';

CREATE TABLE IF NOT EXISTS `__PREFIX__cmsapi_log` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `uid` int(11) NOT NULL DEFAULT '0' COMMENT '用户UID',
  `cmsapi_id` varchar(50) NOT NULL DEFAULT '' COMMENT '采集接口ID',
  `cmsapi_name` varchar(255) DEFAULT NULL COMMENT '采集接口名称',
  `type` varchar(50) DEFAULT NULL COMMENT '购买类型',
  `price` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '购买价格',
  `duration` int(11) NOT NULL DEFAULT '0' COMMENT '有效期天数',
  `time` datetime DEFAULT NULL COMMENT '购买时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `uid`(`uid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='采集套餐购买记录';

CREATE TABLE IF NOT EXISTS `__PREFIX__json_group` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `name` varchar(255) NOT NULL DEFAULT '' COMMENT '分组名称',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='解析接口分组';

-- ========================================================================
-- 4. new（公告）表：补齐 status 列（Article 状态开关使用）
-- ========================================================================
SET @new_table = '__PREFIX__new';
SET @exists_col = (
    SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @new_table
      AND COLUMN_NAME = 'status'
);
SET @sql = IF(@exists_col > 0, 'SELECT 1',
    CONCAT('ALTER TABLE `', @new_table, '` ADD COLUMN `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT ''状态 1显示 0隐藏'''));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ========================================================================
-- 5. lottery_record 表：补齐 uid 列（LotteryService 写入使用）
-- ========================================================================
SET @lr_table = '__PREFIX__lottery_record';
SET @exists_col = (
    SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @lr_table
      AND COLUMN_NAME = 'uid'
);
SET @sql = IF(@exists_col > 0, 'SELECT 1',
    CONCAT('ALTER TABLE `', @lr_table, '` ADD COLUMN `uid` VARCHAR(64) NULL DEFAULT '''' COMMENT ''用户UID'' AFTER `user_id`'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ========================================================================
-- 6. cms_log 表：采集调用日志（Cms 控制器使用）
-- ========================================================================
CREATE TABLE IF NOT EXISTS `__PREFIX__cms_log` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `uid` int(11) NOT NULL DEFAULT '0' COMMENT '用户UID',
  `cmsapi_id` int(11) NOT NULL DEFAULT '0' COMMENT '采集接口ID',
  `ip` varchar(64) DEFAULT NULL COMMENT '请求IP',
  `time` datetime DEFAULT NULL COMMENT '采集时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `uid`(`uid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='采集调用日志';

-- ========================================================================
-- 7. work / work_reply 表：工单管理（Work 控制器使用）
-- ========================================================================
CREATE TABLE IF NOT EXISTS `__PREFIX__work` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `admin_id` int(11) NOT NULL DEFAULT '0' COMMENT '所属管理员ID',
  `name` varchar(255) NOT NULL DEFAULT '' COMMENT '工单标题',
  `state` tinyint(1) NOT NULL DEFAULT '0' COMMENT '状态 0待处理 1处理中 2已完成',
  `time` datetime DEFAULT NULL COMMENT '创建时间',
  `update_time` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `admin_id`(`admin_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='工单表';

CREATE TABLE IF NOT EXISTS `__PREFIX__work_reply` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `work_id` int(11) NOT NULL DEFAULT '0' COMMENT '工单ID',
  `admin_id` int(11) NOT NULL DEFAULT '0' COMMENT '回复者管理员ID',
  `details` text COMMENT '回复内容',
  `type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '类型 1管理员回复 2用户回复',
  `time` datetime DEFAULT NULL COMMENT '回复时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `work_id`(`work_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='工单回复表';
