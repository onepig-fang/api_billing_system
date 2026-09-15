SET NAMES utf8mb4;

-- 为已安装数据库补齐 setting 表的打包配置字段。
-- 使用方法：将 __PREFIX__ 替换为你的数据表前缀后执行（例如 sk_）。

SET @table_name = '__PREFIX__setting';

SET @exists_col = (
    SELECT COUNT(1)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = @table_name
      AND COLUMN_NAME = 'pack_enabled'
);
SET @sql = IF(
    @exists_col > 0,
    'SELECT 1',
    CONCAT('ALTER TABLE `', @table_name, '` ADD COLUMN `pack_enabled` TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''打包开关'' AFTER `appid`')
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exists_col = (
    SELECT COUNT(1)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = @table_name
      AND COLUMN_NAME = 'pack_cost'
);
SET @sql = IF(
    @exists_col > 0,
    'SELECT 1',
    CONCAT('ALTER TABLE `', @table_name, '` ADD COLUMN `pack_cost` INT(11) NOT NULL DEFAULT 1 COMMENT ''单次打包消耗次数'' AFTER `pack_enabled`')
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exists_col = (
    SELECT COUNT(1)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = @table_name
      AND COLUMN_NAME = 'pack_api_url'
);
SET @sql = IF(
    @exists_col > 0,
    'SELECT 1',
    CONCAT('ALTER TABLE `', @table_name, '` ADD COLUMN `pack_api_url` VARCHAR(255) NULL DEFAULT NULL COMMENT ''打包API地址'' AFTER `pack_cost`')
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exists_col = (
    SELECT COUNT(1)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = @table_name
      AND COLUMN_NAME = 'pack_api_key'
);
SET @sql = IF(
    @exists_col > 0,
    'SELECT 1',
    CONCAT('ALTER TABLE `', @table_name, '` ADD COLUMN `pack_api_key` VARCHAR(255) NULL DEFAULT NULL COMMENT ''打包API Key'' AFTER `pack_api_url`')
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exists_col = (
    SELECT COUNT(1)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = @table_name
      AND COLUMN_NAME = 'pack_api_appid'
);
SET @sql = IF(
    @exists_col > 0,
    'SELECT 1',
    CONCAT('ALTER TABLE `', @table_name, '` ADD COLUMN `pack_api_appid` VARCHAR(255) NULL DEFAULT NULL COMMENT ''打包API AppID'' AFTER `pack_api_key`')
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exists_col = (
    SELECT COUNT(1)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = @table_name
      AND COLUMN_NAME = 'pack_api_secret'
);
SET @sql = IF(
    @exists_col > 0,
    'SELECT 1',
    CONCAT('ALTER TABLE `', @table_name, '` ADD COLUMN `pack_api_secret` VARCHAR(255) NULL DEFAULT NULL COMMENT ''打包API Secret'' AFTER `pack_api_appid`')
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = CONCAT(
    'UPDATE `', @table_name, '` ',
    'SET `pack_enabled` = IFNULL(`pack_enabled`, 0), ',
    '`pack_cost` = IFNULL(NULLIF(`pack_cost`, 0), 1) ',
    'WHERE `id` = 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
