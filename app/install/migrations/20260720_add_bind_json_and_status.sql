SET NAMES utf8mb4;

-- 为已安装数据库补齐 user.bind_json_ids 与 json.status 字段。
-- 使用方法：将 __PREFIX__ 替换为你的数据表前缀后执行（例如 sk_）。
-- 本迁移幂等：重复执行不会报错；新装库（install.sql 已含这两列）执行也不会报错。

-- ========================================================================
-- 1. user 表：补齐 bind_json_ids（绑定的解析接口ID，逗号分隔）
-- ========================================================================
SET @user_table = '__PREFIX__user';

SET @exists_col = (
    SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @user_table
      AND COLUMN_NAME = 'bind_json_ids'
);
SET @exists_after = (
    SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @user_table
      AND COLUMN_NAME = 'pack_quota'
);
-- pack_quota 存在时才带 AFTER 定位，避免历史库缺该列导致语句失败
SET @sql = IF(@exists_col > 0, 'SELECT 1',
    CONCAT('ALTER TABLE `', @user_table, '` ADD COLUMN `bind_json_ids` TEXT NULL COMMENT ''绑定的解析接口ID，逗号分隔''',
           IF(@exists_after > 0, ' AFTER `pack_quota`', '')));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ========================================================================
-- 2. json 表：补齐 status（状态 1启用 0禁用）
-- ========================================================================
SET @json_table = '__PREFIX__json';

SET @exists_col = (
    SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @json_table
      AND COLUMN_NAME = 'status'
);
SET @exists_after = (
    SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @json_table
      AND COLUMN_NAME = 'Json_type'
);
SET @sql = IF(@exists_col > 0, 'SELECT 1',
    CONCAT('ALTER TABLE `', @json_table, '` ADD COLUMN `status` TINYINT(1) DEFAULT ''1'' COMMENT ''状态 1启用 0禁用''',
           IF(@exists_after > 0, ' AFTER `Json_type`', '')));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
