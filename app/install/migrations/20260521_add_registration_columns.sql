SET NAMES utf8mb4;

-- 为已安装数据库补齐注册配置字段，并调整 user.qq 为可保存昵称的字符串字段。
-- 使用方法：将 __PREFIX__ 替换为你的数据表前缀后执行（例如 sk_）。

SET @setting_table = '__PREFIX__setting';
SET @user_table = '__PREFIX__user';

SET @exists_col = (
    SELECT COUNT(1)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = @setting_table
      AND COLUMN_NAME = 'reg_points'
);
SET @sql = IF(
    @exists_col > 0,
    'SELECT 1',
    CONCAT('ALTER TABLE `', @setting_table, '` ADD COLUMN `reg_points` INT(11) NOT NULL DEFAULT 0 COMMENT ''注册送点数'' AFTER `reg_type`')
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exists_col = (
    SELECT COUNT(1)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = @setting_table
      AND COLUMN_NAME = 'reg_captcha'
);
SET @sql = IF(
    @exists_col > 0,
    'SELECT 1',
    CONCAT('ALTER TABLE `', @setting_table, '` ADD COLUMN `reg_captcha` TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''注册验证码开关'' AFTER `reg_points`')
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exists_col = (
    SELECT COUNT(1)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = @setting_table
      AND COLUMN_NAME = 'reg_email_code'
);
SET @sql = IF(
    @exists_col > 0,
    'SELECT 1',
    CONCAT('ALTER TABLE `', @setting_table, '` ADD COLUMN `reg_email_code` TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''注册邮箱验证码开关'' AFTER `reg_captcha`')
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exists_col = (
    SELECT COUNT(1)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = @setting_table
      AND COLUMN_NAME = 'login_captcha'
);
SET @sql = IF(
    @exists_col > 0,
    'SELECT 1',
    CONCAT('ALTER TABLE `', @setting_table, '` ADD COLUMN `login_captcha` TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''登录验证码开关'' AFTER `reg_email_code`')
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exists_col = (
    SELECT COUNT(1)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = @setting_table
      AND COLUMN_NAME = 'invite_reward'
);
SET @sql = IF(
    @exists_col > 0,
    'SELECT 1',
    CONCAT('ALTER TABLE `', @setting_table, '` ADD COLUMN `invite_reward` DECIMAL(10,3) NOT NULL DEFAULT 0.000 COMMENT ''邀请奖励'' AFTER `login_captcha`')
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exists_col = (
    SELECT COUNT(1)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = @setting_table
      AND COLUMN_NAME = 'default_way'
);
SET @sql = IF(
    @exists_col > 0,
    'SELECT 1',
    CONCAT('ALTER TABLE `', @setting_table, '` ADD COLUMN `default_way` VARCHAR(20) NOT NULL DEFAULT ''包点'' COMMENT ''默认用户类型'' AFTER `invite_reward`')
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = CONCAT(
    'ALTER TABLE `', @user_table, '` MODIFY COLUMN `qq` VARCHAR(255) NULL DEFAULT NULL COMMENT ''QQ或昵称'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
