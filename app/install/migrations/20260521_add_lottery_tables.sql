SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `__PREFIX__lottery_config` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `is_enabled` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否启用',
  `daily_limit` int(11) NOT NULL DEFAULT '3' COMMENT '每日抽奖次数',
  `prizes` longtext COMMENT '奖品配置',
  `create_time` datetime DEFAULT NULL COMMENT '创建时间',
  `update_time` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='抽奖配置';

INSERT INTO `__PREFIX__lottery_config` (`id`, `is_enabled`, `daily_limit`, `prizes`, `create_time`, `update_time`)
SELECT 1, 0, 3, '[{"name":"100点数","type":"points","value":100,"probability":30,"sort":1},{"name":"50点数","type":"points","value":50,"probability":35,"sort":2},{"name":"1元余额","type":"money","value":1,"probability":20,"sort":3},{"name":"10次打包额度","type":"pack_quota","value":10,"probability":10,"sort":4},{"name":"谢谢参与","type":"empty","value":0,"probability":5,"sort":5}]', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM `__PREFIX__lottery_config` WHERE `id` = 1);

CREATE TABLE IF NOT EXISTS `__PREFIX__lottery_record` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `user_id` int(11) NOT NULL DEFAULT '0' COMMENT '用户ID',
  `uid` varchar(64) DEFAULT '' COMMENT '用户UID',
  `username` varchar(255) DEFAULT '' COMMENT '用户名',
  `prize_name` varchar(255) DEFAULT '' COMMENT '奖品名称',
  `prize_type` varchar(32) DEFAULT '' COMMENT '奖品类型',
  `prize_value` decimal(12,3) NOT NULL DEFAULT '0.000' COMMENT '奖品值',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态',
  `ip` varchar(64) DEFAULT '' COMMENT 'IP',
  `create_time` datetime DEFAULT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_user_id`(`user_id`) USING BTREE,
  INDEX `idx_prize_type`(`prize_type`) USING BTREE,
  INDEX `idx_create_time`(`create_time`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='抽奖记录';
