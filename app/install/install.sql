
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
--
-- 表的结构 `__PREFIX__admin`
--
DROP TABLE IF EXISTS `__PREFIX__admin`;
CREATE TABLE `__PREFIX__admin` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `username` varchar(255) NOT NULL COMMENT '用户名',
  `password` varchar(255) NOT NULL COMMENT '密码',
  `auth` varchar(255) DEFAULT NULL COMMENT '身份',
  `name` varchar(255) DEFAULT NULL COMMENT '管理员名称',
  `login_time` datetime DEFAULT NULL COMMENT '最后登录时间',
  `login_ip` varchar(255) DEFAULT NULL,
  `zsnum` int(200) DEFAULT '0' COMMENT '赠送的点数',
  `zfurl` mediumtext COMMENT '易支付地址',
  `zfid` varchar(255) DEFAULT NULL COMMENT '易支付id',
  `zfkey` varchar(255) DEFAULT NULL COMMENT '易支付key',
  `json` int(1) DEFAULT '0' COMMENT '解析权限',
  `shop` int(11) DEFAULT '0' COMMENT '套餐权限',
  `ban` int(11) DEFAULT '0' COMMENT '账号状态',
  `pay` int(11) DEFAULT '0' COMMENT '支付权限',
  `whatpay` int(1) DEFAULT '1' COMMENT '支付方式 1已支付2支付宝当面',
  `public_key` mediumtext COMMENT '支付宝公钥',
  `private_key` mediumtext COMMENT '支付宝私钥',
  `appid` mediumtext COMMENT '支付宝appid',
  `enable_yipay` tinyint(1) DEFAULT '1' COMMENT '易支付开关 1开启 0关闭',
  `enable_dmpay` tinyint(1) DEFAULT '1' COMMENT '当面付开关 1开启 0关闭',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `id`(`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='后台管理员表' ROW_FORMAT=DYNAMIC;
--
-- 转存表中的数据 `__PREFIX__admin`
--


INSERT INTO `__PREFIX__admin` VALUES (1, 'admin', '76a2e9e6cf0be65a19340c884ecf72b1', '超级管理员', '聚合', '2025-07-07 15:13:44', '', 0, NULL, NULL, NULL, 1, 1, 1, 1, 1, NULL, NULL, NULL, 1, 1);
--
-- 表的结构 `__PREFIX__bought`
--
DROP TABLE IF EXISTS `__PREFIX__bought`;
CREATE TABLE `__PREFIX__bought` (
  `id` int(200) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `uid` int(11) NOT NULL COMMENT '购买者',
  `name` mediumtext NOT NULL COMMENT '名称',
  `fs` mediumtext NOT NULL COMMENT '方式',
  `price` int(11) NOT NULL COMMENT '价格',
  `time` mediumtext NOT NULL COMMENT '时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `id`(`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='套餐购买记录' ROW_FORMAT=DYNAMIC;
DROP TABLE IF EXISTS `__PREFIX__blacklist`;
CREATE TABLE `__PREFIX__blacklist` (
  `id` int(200) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `notes` text COMMENT '备注',
  `ip` varchar(255) NOT NULL COMMENT 'ip',
  `user_id` int(200) DEFAULT NULL,
  `time` datetime NOT NULL COMMENT '添加时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `id`(`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='黑名单';
--
-- 表的结构 `__PREFIX__card`
--
DROP TABLE IF EXISTS `__PREFIX__card`;
CREATE TABLE `__PREFIX__card` (
  `id` int(200) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `kmcode` varchar(255) NOT NULL COMMENT '充值卡',
  `time` datetime NOT NULL COMMENT '生成时间',
  `money` decimal(10,3) NOT NULL COMMENT '金额',
  `status` int(1) DEFAULT '0' COMMENT '使用状态，0表示未使用，1表示已使用',
  `uid` int(200) DEFAULT NULL,
  `ip` varchar(200) DEFAULT NULL COMMENT '使用者IP，支持IPv4和IPv6',
  `remark` varchar(255) DEFAULT NULL COMMENT '备注',
  `sytime` datetime DEFAULT NULL COMMENT '使用时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `id`(`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='充值卡表';
--
-- 表的结构 `__PREFIX__cmsapi`
--
DROP TABLE IF EXISTS `__PREFIX__cmsapi`;
CREATE TABLE `__PREFIX__cmsapi` (
  `cmsapi_id` int(200) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `cmsapi_name` mediumtext NOT NULL COMMENT '苹果CMS接口名称',
  `cmsapi_url` mediumtext NOT NULL COMMENT '苹果CMS接口地址',
  `cmsapi_type` mediumtext NOT NULL COMMENT '此接口生效的套餐ID名称',
  `cmsapi_format` mediumtext NOT NULL COMMENT '苹果CMS接口格式',
  `cmsapi_remark` mediumtext NOT NULL COMMENT '苹果CMS接口备注',
  `cmsapi_auth` tinyint(4) NOT NULL DEFAULT '0' COMMENT '是否开启授权：1开，0关',
  `cmsapi_price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `cmsapi_freetime` mediumtext COMMENT '买解析赠送采集接口到期时长',
  `cmsapi_price_month` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '月价格',
  `cmsapi_price_quarter` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '季价格',
  `cmsapi_price_half_year` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '半年价格',
  `cmsapi_price_year` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '年价格',
  `cmsapi_duration_month` mediumtext NOT NULL COMMENT '月时长（天）',
  `cmsapi_duration_quarter` int(11) NOT NULL DEFAULT '0' COMMENT '季时长（天）',
  `cmsapi_duration_half_year` int(11) NOT NULL DEFAULT '0' COMMENT '半年时长（天）',
  `cmsapi_duration_year` int(11) NOT NULL DEFAULT '0' COMMENT '年时长（天）',
    PRIMARY KEY (`cmsapi_id`) USING BTREE,
  INDEX `cmsapi_id`(`cmsapi_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='苹果cms接口';
--
-- 表的结构 `__PREFIX__code`
--
DROP TABLE IF EXISTS `__PREFIX__code`;
CREATE TABLE `__PREFIX__code` (
  `id` int(200) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `code` varchar(255) NOT NULL COMMENT '验证码',
  `email` mediumtext NOT NULL COMMENT '邮箱地址',
  `endtime` mediumtext NOT NULL COMMENT '过期时间',
  `status` int(1) DEFAULT '1',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `id`(`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='验证码表';
--
-- 表的结构 `__PREFIX__invite`
--
DROP TABLE IF EXISTS `__PREFIX__invite`;
CREATE TABLE `__PREFIX__invite` (
  `id` int(200) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `uid` mediumtext NOT NULL COMMENT '邀请者uid',
  `inviteuser` mediumtext NOT NULL COMMENT '被邀请注册的uid',
  `time` mediumtext NOT NULL COMMENT '注册的时间',
  `pay` mediumtext NOT NULL COMMENT '赠送返回的点数',
  `ip` mediumtext NOT NULL,
  `admin_id` int(11) DEFAULT NULL COMMENT '所属代理管理员ID（代理招待时记录）',
    PRIMARY KEY (`id`) USING BTREE,
  INDEX `id`(`id`) USING BTREE,
  INDEX `admin_id`(`admin_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- 表的结构 `__PREFIX__json`
--
DROP TABLE IF EXISTS `__PREFIX__json`;
CREATE TABLE `__PREFIX__json` (
  `id` int(200) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `Json_name` mediumtext NOT NULL COMMENT '名称',
  `Json_str` mediumtext COMMENT '官网地址',
  `Json_api` mediumtext NOT NULL COMMENT 'api地址',
  `Json_main` int(11) NOT NULL,
  `Json_type` mediumtext NOT NULL,
  `status` tinyint(1) DEFAULT '1' COMMENT '状态 1启用 0禁用',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `id`(`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='解析配置';
--
-- 转存表中的数据 `__PREFIX__json`
--


INSERT INTO `__PREFIX__json` (`id`,`Json_name`,`Json_str`,`Json_api`,`Json_main`,`Json_type`) VALUES (1, '主用（影视）', '', '', 1, 'all');
INSERT INTO `__PREFIX__json` (`id`,`Json_name`,`Json_str`,`Json_api`,`Json_main`,`Json_type`) VALUES (2, '备用（影视）', '', '', 2, 'all');
INSERT INTO `__PREFIX__json` (`id`,`Json_name`,`Json_str`,`Json_api`,`Json_main`,`Json_type`) VALUES (3, '主用（短视频）', '', '', 1, 'dsp');
INSERT INTO `__PREFIX__json` (`id`,`Json_name`,`Json_str`,`Json_api`,`Json_main`,`Json_type`) VALUES (4, '备用（短视频）', '', '', 2, 'dsp');
INSERT INTO `__PREFIX__json` (`id`,`Json_name`,`Json_str`,`Json_api`,`Json_main`,`Json_type`) VALUES (5, '腾讯', 'v.qq.com', '', 0, 'all');
INSERT INTO `__PREFIX__json` (`id`,`Json_name`,`Json_str`,`Json_api`,`Json_main`,`Json_type`) VALUES (6, '芒果', 'www.mgtv.com', '', 0, 'all');
INSERT INTO `__PREFIX__json` (`id`,`Json_name`,`Json_str`,`Json_api`,`Json_main`,`Json_type`) VALUES (7, '爱奇艺', 'www.iqiyi.com', '', 0, 'all');
INSERT INTO `__PREFIX__json` (`id`,`Json_name`,`Json_str`,`Json_api`,`Json_main`,`Json_type`) VALUES (8, '优酷', 'v.youku.com', '', 0, 'all');
INSERT INTO `__PREFIX__json` (`id`,`Json_name`,`Json_str`,`Json_api`,`Json_main`,`Json_type`) VALUES (9, 'B站', 'www.bilibili.com', '', 0, 'all');
INSERT INTO `__PREFIX__json` (`id`,`Json_name`,`Json_str`,`Json_api`,`Json_main`,`Json_type`) VALUES (10, '西瓜', 'www.ixigua.com', '', 0, 'all');
INSERT INTO `__PREFIX__json` (`id`,`Json_name`,`Json_str`,`Json_api`,`Json_main`,`Json_type`) VALUES (11, '搜狐', 'tv.sohu.com', '', 0, 'all');
--
-- 表的结构 `__PREFIX__recharge`
--

DROP TABLE IF EXISTS `__PREFIX__recharge`;
CREATE TABLE `__PREFIX__recharge` (
  `id` int(200) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `order_id` varchar(255) NOT NULL COMMENT '订单号',
  `kami` varchar(255) DEFAULT NULL COMMENT '卡密信息',
  `fs` varchar(255) DEFAULT NULL,
  `status` int(1) DEFAULT '0' COMMENT '充值状态 0失败1成功',
  `money` decimal(10,3) NOT NULL COMMENT '充值金额',
  `user_id` int(200) NOT NULL COMMENT '充值用户UID',
  `time` int(200) NOT NULL COMMENT '提交时间',
  `intime` int(200) DEFAULT NULL COMMENT '充值时间',
  `trade_no` text COMMENT '第三方单号',
  `qr_code` text COMMENT '二维码',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `id`(`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='充值记录id';
--
-- 表的结构 `__PREFIX__reurl`
--
DROP TABLE IF EXISTS `__PREFIX__reurl`;
CREATE TABLE `__PREFIX__reurl` (
  `id` int(200) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `name` text NOT NULL COMMENT '替换资源名称',
  `old_url` text NOT NULL COMMENT '老资源 地址',
  `new_url` text NOT NULL COMMENT '新资源地址',
  `intime` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT '提交时间',
  `auth` text COMMENT '提交的身份',
  `auth_id` int(11) DEFAULT NULL COMMENT '提交的ID',
  `examine` int(11) DEFAULT '0' COMMENT '审核的状态',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `id`(`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='资源替换';
--
-- 表的结构 `__PREFIX__setting`
--

DROP TABLE IF EXISTS `__PREFIX__setting`;
CREATE TABLE `__PREFIX__setting` (
  `id` int(200) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `name` mediumtext NOT NULL COMMENT '网站名称',
  `keyword` mediumtext NOT NULL COMMENT '关键词',
  `content` mediumtext NOT NULL COMMENT '介绍',
  `ym` mediumtext NOT NULL COMMENT '域名',
  `template` mediumtext NOT NULL COMMENT '模板',
  `inside` varchar(255) DEFAULT 'inside1' COMMENT '内页',
  `beian` mediumtext NOT NULL COMMENT '备案号',
  `logo` mediumtext NOT NULL COMMENT 'logo地址',
  `dy` mediumtext NOT NULL,
  `jx` mediumtext NOT NULL,
  `cj_authip_key` mediumtext NOT NULL COMMENT '采集的key',
  `cjip` int(1) DEFAULT '1' COMMENT '采集IP开关',
  `authip_key` mediumtext NOT NULL COMMENT '解析key',
  `authip` mediumtext NOT NULL COMMENT 'ip判断的开关',
  `checkqq` mediumtext NOT NULL COMMENT '检测QQ',
  `qqlogin` mediumtext NOT NULL COMMENT 'QQ登录开关',
  `mailreg` mediumtext NOT NULL,
  `khd` mediumtext NOT NULL,
  `emailHost` mediumtext NOT NULL COMMENT '邮件host',
  `emailssl` mediumtext NOT NULL COMMENT '邮件ssl',
  `emailport` mediumtext NOT NULL,
  `emailuser` mediumtext NOT NULL,
  `emailpass` mediumtext NOT NULL,
  `kefuqq` mediumtext NOT NULL,
  `qqgroupurl` mediumtext NOT NULL COMMENT 'QQ群号',
  `background` mediumtext NOT NULL,
  `fdvideo` mediumtext NOT NULL,
  `notice_type` int(1) DEFAULT '1' COMMENT '首页弹窗',
  `cjdiyurl` mediumtext NOT NULL,
  `notice` mediumtext COMMENT '首页公告',
  `notices` text COMMENT '内页公告',
  `Json_m3u8` text COMMENT 'Json_m3u8地址',
  `quick_rainbow_callback` text,
  `quick_rainbow_appid` text,
  `quick_rainbow_key` text,
  `quick_type` int(1) DEFAULT '0',
  `cache` int(1) DEFAULT '1',
  `cache_time` int(200) DEFAULT '180',
  `reg_type` int(1) DEFAULT '1',
  `pack_enabled` int(1) DEFAULT '0' COMMENT '打包开关：1开 0关',
  `pack_api_url` mediumtext COMMENT '外部打包API地址',
  `pack_api_id` varchar(64) DEFAULT NULL COMMENT '外部打包API用户ID',
  `pack_api_key` varchar(255) DEFAULT NULL COMMENT '外部打包API Key',
  `pack_api_appid` varchar(255) DEFAULT NULL COMMENT '外部打包API AppID',
  `pack_api_secret` varchar(255) DEFAULT NULL COMMENT '外部打包API Secret',
  `pack_cost` int(11) DEFAULT '1' COMMENT '每次打包消耗额度',
  `epay_api` mediumtext COMMENT '易支付接口地址',
  `epay_pid` varchar(255) DEFAULT NULL COMMENT '易支付商户ID',
  `epay_key` varchar(255) DEFAULT NULL COMMENT '易支付商户密钥',
  `whatpay` int(1) DEFAULT '1' COMMENT '支付方式 1易支付 2支付宝当面付',
  `public_key` mediumtext COMMENT '支付宝公钥',
  `private_key` mediumtext COMMENT '支付宝私钥',
  `appid` mediumtext COMMENT '支付宝appid',
  `reg_points` int(11) NOT NULL DEFAULT '0' COMMENT '注册送点数',
  `reg_captcha` tinyint(1) NOT NULL DEFAULT '0' COMMENT '注册验证码开关',
  `reg_email_code` tinyint(1) NOT NULL DEFAULT '0' COMMENT '注册邮箱验证码开关',
  `login_captcha` tinyint(1) NOT NULL DEFAULT '0' COMMENT '登录验证码开关',
  `invite_reward` decimal(10,3) NOT NULL DEFAULT '0.000' COMMENT '邀请奖励',
  `default_way` varchar(20) NOT NULL DEFAULT '包点' COMMENT '默认用户类型',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `id`(`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
--
-- 转存表中的数据 `__PREFIX__setting`
--


INSERT INTO `__PREFIX__setting` (`id`,`name`,`keyword`,`content`,`ym`,`template`,`inside`,`beian`,`logo`,`dy`,`jx`,`cj_authip_key`,`cjip`,`authip_key`,`authip`,`checkqq`,`qqlogin`,`mailreg`,`khd`,`emailHost`,`emailssl`,`emailport`,`emailuser`,`emailpass`,`kefuqq`,`qqgroupurl`,`background`,`fdvideo`,`notice_type`,`cjdiyurl`,`notice`,`notices`,`Json_m3u8`,`quick_rainbow_callback`,`quick_rainbow_appid`,`quick_rainbow_key`,`quick_type`,`cache`,`cache_time`,`reg_type`,`pack_enabled`,`pack_api_url`,`pack_api_id`,`pack_api_key`,`pack_cost`) VALUES
(1, '网站名称', '关键词', '介绍', '域名', 'index2', 'inside1', '备案', 'http://auth.58km.cn/storage/20250709/c0dea636e9900174510ef6b74202ad72.png', '1', '1', '聚合解析NB666', 1, '聚合解析NB666', '1', '0', '1', '0', '../artdm.zip', 'smtp.163.com', 'ssl', '465', '呃呃呃', '没', '后台设置', '后台设置', '222', 'error.mp4', 1, 'http://dm.apptotv.top/?ac=dm&url=', '', '', 'https://cj.lziapi.com/api.php/provide/vod/from/lzm3u8/at/xml/', NULL, NULL, NULL, 0, 0, 180, 1, 0, NULL, NULL, NULL, 1);





-- 表的结构 `__PREFIX__shop`
--

DROP TABLE IF EXISTS `__PREFIX__shop`;
CREATE TABLE `__PREFIX__shop` (
  `id` int(200) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `name` text NOT NULL COMMENT '套餐名称',
  `price` decimal(10,3) NOT NULL COMMENT '套餐价格',
  `content` text NOT NULL COMMENT '套餐介绍',
  `dd` text NOT NULL COMMENT '值',
  `fs` mediumtext NOT NULL,
  `fullnum` int(11) NOT NULL,
  `bind_enable` tinyint(1) NOT NULL DEFAULT '0' COMMENT '绑定解析开关 1开0关',
  `bind_json_ids` text COMMENT '绑定解析接口ID，逗号分隔',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `id`(`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='套餐表';
--
-- 表的结构 `__PREFIX__shop_time`
--
DROP TABLE IF EXISTS `__PREFIX__shop_time`;
CREATE TABLE `__PREFIX__shop_time` (
  `id` int(200) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `uid` int(200) NOT NULL COMMENT '用户UID',
  `cj_auth_ip` varchar(255) DEFAULT NULL COMMENT '用户采集授权IP',
  `cmsapi_id` varchar(50) NOT NULL COMMENT '采集接口 ID',
  `cmsapi_name` varchar(255) NOT NULL COMMENT '采集接口名称',
  `intime` datetime NOT NULL COMMENT '采集套餐购买时间',
  `end_time` datetime NOT NULL COMMENT '接口到期时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `id`(`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='采集套餐时间表';
--
-- 表的结构 `__PREFIX__shop_log`
--
DROP TABLE IF EXISTS `__PREFIX__shop_log`;
CREATE TABLE `__PREFIX__shop_log` (
  `id` int(200) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `uid` int(200) NOT NULL COMMENT '用户UID',
  `shop_id` int(200) NOT NULL COMMENT '套餐ID',
  `shop_name` varchar(255) NOT NULL COMMENT '套餐名称',
  `price` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '购买价格',
  `time` datetime NOT NULL COMMENT '购买时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `uid`(`uid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='套餐购买记录';
--
-- 表的结构 `__PREFIX__cmsapi_log`
--
DROP TABLE IF EXISTS `__PREFIX__cmsapi_log`;
CREATE TABLE `__PREFIX__cmsapi_log` (
  `id` int(200) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `uid` int(200) NOT NULL COMMENT '用户UID',
  `cmsapi_id` varchar(50) NOT NULL COMMENT '采集接口ID',
  `cmsapi_name` varchar(255) NOT NULL COMMENT '采集接口名称',
  `type` varchar(50) DEFAULT NULL COMMENT '购买类型',
  `price` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '购买价格',
  `duration` int(11) NOT NULL DEFAULT '0' COMMENT '有效期天数',
  `time` datetime NOT NULL COMMENT '购买时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `uid`(`uid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='采集套餐购买记录';
--
-- 表的结构 `__PREFIX__json_group`
--
DROP TABLE IF EXISTS `__PREFIX__json_group`;
CREATE TABLE `__PREFIX__json_group` (
  `id` int(200) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `name` varchar(255) NOT NULL COMMENT '分组名称',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='解析接口分组';
--
-- 表的结构 `__PREFIX__cms_log`
--
DROP TABLE IF EXISTS `__PREFIX__cms_log`;
CREATE TABLE `__PREFIX__cms_log` (
  `id` int(200) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `uid` int(200) DEFAULT NULL COMMENT '用户UID',
  `cmsapi_id` int(200) DEFAULT NULL COMMENT '采集接口ID',
  `ip` varchar(64) DEFAULT NULL COMMENT '请求IP',
  `time` datetime DEFAULT NULL COMMENT '采集时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `uid`(`uid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='采集调用日志';
--
-- 表的结构 `__PREFIX__new`
--
DROP TABLE IF EXISTS `__PREFIX__new`;
CREATE TABLE `__PREFIX__new` (
  `id` int(200) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `title` varchar(255) NOT NULL COMMENT '标题',
  `content` text NOT NULL COMMENT '内容',
  `time` datetime NOT NULL COMMENT '发布时间',
  `mail_push` int(1) DEFAULT '0' COMMENT '是否邮件推送',
  `user_id` text COMMENT '推送用户',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态 1显示 0隐藏',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `id`(`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `__PREFIX__qiupian` (
   `id` int(200) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `user_id` int(200) DEFAULT NULL COMMENT 'UID',
  `name` varchar(255) NOT NULL COMMENT '影片名称',
  `type` int(1) DEFAULT NULL COMMENT '类型',
  `year` varchar(255) DEFAULT NULL COMMENT '上映时间',
  `describe` text COMMENT '影片的描述',
  `demand` text COMMENT '客户需求',
  `status` int(1) DEFAULT '1' COMMENT '状态',
  `number` varchar(255) DEFAULT NULL COMMENT '编号',
  `time` datetime DEFAULT NULL COMMENT '提交时间',
  `processing_time` datetime DEFAULT NULL COMMENT '处理的时间',
  `completion_time` datetime DEFAULT NULL COMMENT '完成的时间',
  `img` varchar(255) DEFAULT NULL COMMENT '影片图片',
  `reason` text COMMENT '拒绝的原因',
  `director` varchar(255) DEFAULT NULL COMMENT '导演',
  `actors` text COMMENT '主演',
  `imdbRating` text COMMENT 'IMDb',
     PRIMARY KEY (`id`) USING BTREE,
  INDEX `id`(`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
--
--
-- 表的结构 `__PREFIX__work`
--
DROP TABLE IF EXISTS `__PREFIX__work`;
CREATE TABLE `__PREFIX__work` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `admin_id` int(11) NOT NULL DEFAULT '0' COMMENT '所属管理员ID',
  `name` varchar(255) NOT NULL DEFAULT '' COMMENT '工单标题',
  `state` tinyint(1) NOT NULL DEFAULT '0' COMMENT '状态 0待处理 1处理中 2已完成',
  `time` datetime DEFAULT NULL COMMENT '创建时间',
  `update_time` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `admin_id`(`admin_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='工单表';
--
-- 表的结构 `__PREFIX__work_reply`
--
DROP TABLE IF EXISTS `__PREFIX__work_reply`;
CREATE TABLE `__PREFIX__work_reply` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `work_id` int(11) NOT NULL DEFAULT '0' COMMENT '工单ID',
  `admin_id` int(11) NOT NULL DEFAULT '0' COMMENT '回复者ID',
  `details` text COMMENT '回复内容',
  `type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '类型 1管理员回复 2用户回复',
  `time` datetime DEFAULT NULL COMMENT '回复时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `work_id`(`work_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='工单回复表';
-- 表的结构 `__PREFIX__user`
--
DROP TABLE IF EXISTS `__PREFIX__user`;
CREATE TABLE `__PREFIX__user` (
  `id` int(200) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `uid` int(200) NOT NULL COMMENT 'UID',
  `user` varchar(255) NOT NULL COMMENT '账号',
  `pass` varchar(255) NOT NULL COMMENT '密码',
   `my` text COMMENT 'm密钥',
  `email` varchar(255) NOT NULL COMMENT '邮箱地址',
  `qq` int(200) DEFAULT NULL COMMENT '绑定的QQ号码',
  `access_token` text COMMENT 'QQaccess_token',
  `access_token_wx` text COMMENT '微信access_token',
  `state` int(1) DEFAULT '1' COMMENT '状态',
  `time` mediumtext NOT NULL COMMENT '注册时间',
  `ip` mediumtext NOT NULL COMMENT '注册IP',
  `auth_ip` mediumtext COMMENT '解析授权IP',
  `cj_auth_ip` mediumtext COMMENT '采集授权IP',
  `points` int(200) DEFAULT '0' COMMENT '可用点数',
  `daily_limit` int(200) DEFAULT '0' COMMENT '每日使用的上限',
  `pack_quota` int(200) DEFAULT '0' COMMENT '用户打包额度',
  `bind_json_ids` text COMMENT '绑定的解析接口ID，逗号分隔',
  `fullnum` int(200) DEFAULT NULL COMMENT '时效用户的每日上限',
  `client_name` varchar(255) DEFAULT NULL COMMENT '客户端名称',
  `daynum` int(200) DEFAULT '0' COMMENT '今日调用次数',
  `daytime` datetime DEFAULT '2025-01-18 00:00:01' COMMENT '恢复使用时间',
  `way` varchar(200) DEFAULT NULL COMMENT '方式：1包点 2包月',
  `bytime` date DEFAULT NULL COMMENT '包月到期时间',
  `money` decimal(10,3) DEFAULT NULL COMMENT '余额',
  `you_index` text COMMENT '自定义的提示内容',
  `you_href` text COMMENT '你的地址',
  `img` mediumtext,
  `logo` mediumtext,
  `button` mediumtext,
  `dmkupass` mediumtext,
  `dmfirst` mediumtext,
  `player` mediumtext,
  `referer` mediumtext,
  `key` mediumtext,
  `defense` text,
  `tj` text,
  `byjx` text,
  `sj` int(100) DEFAULT NULL COMMENT '上级ID（邀请人UID）',
  `sjuser` int(11) DEFAULT NULL COMMENT '所属代理管理员ID（admin.id）',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `id`(`id`) USING BTREE,
  INDEX `uid`(`uid`) USING BTREE,
  INDEX `sjuser`(`sjuser`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
--
-- 表的结构 `__PREFIX__userlog`
--

DROP TABLE IF EXISTS `__PREFIX__userlog`;
CREATE TABLE `__PREFIX__userlog` (
  `id` int(200) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `url` mediumtext NOT NULL COMMENT '调用URL',
  `ip` mediumtext NOT NULL COMMENT 'IP',
  `uid` mediumtext NOT NULL COMMENT '调用UID',
  `sid` int(11) DEFAULT NULL COMMENT '调用ID',
  `intime` mediumtext NOT NULL COMMENT '调用时间',
  `status` mediumtext NOT NULL COMMENT '调用状态',
  `uselogjxtime` float DEFAULT '0' COMMENT '所需时间',
   PRIMARY KEY (`id`) USING BTREE,
  INDEX `id`(`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='调用记录';

--
-- 表的结构 `__PREFIX__lottery_config`
--
DROP TABLE IF EXISTS `__PREFIX__lottery_config`;
CREATE TABLE `__PREFIX__lottery_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `is_enabled` tinyint(1) DEFAULT '1' COMMENT '是否开启抽奖',
  `daily_limit` int(11) DEFAULT '3' COMMENT '每日抽奖次数限制',
  `prizes` text COMMENT '奖品配置JSON',
  `create_time` datetime DEFAULT CURRENT_TIMESTAMP,
  `update_time` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='抽奖配置表';

--
-- 转存表中的数据 `__PREFIX__lottery_config`
--
INSERT INTO `__PREFIX__lottery_config` VALUES (1, 1, 3, '[{\"name\":\"100点数\",\"type\":\"points\",\"value\":100,\"probability\":30,\"sort\":1},{\"name\":\"50点数\",\"type\":\"points\",\"value\":50,\"probability\":35,\"sort\":2},{\"name\":\"1元余额\",\"type\":\"money\",\"value\":1,\"probability\":20,\"sort\":3},{\"name\":\"10次打包额度\",\"type\":\"pack_quota\",\"value\":10,\"probability\":10,\"sort\":4},{\"name\":\"谢谢参与\",\"type\":\"empty\",\"value\":0,\"probability\":5,\"sort\":5}]', '2024-01-01 00:00:00', '2024-01-01 00:00:00');

--
-- 表的结构 `__PREFIX__lottery_record`
--
DROP TABLE IF EXISTS `__PREFIX__lottery_record`;
CREATE TABLE `__PREFIX__lottery_record` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT '用户ID',
  `uid` varchar(64) DEFAULT '' COMMENT '用户UID',
  `username` varchar(100) DEFAULT NULL COMMENT '用户名',
  `prize_type` varchar(50) NOT NULL COMMENT '奖品类型',
  `prize_name` varchar(100) NOT NULL COMMENT '奖品名称',
  `prize_value` decimal(10,2) DEFAULT '0.00' COMMENT '奖品价值',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态 1正常',
  `ip` varchar(45) DEFAULT NULL COMMENT '抽奖IP',
  `create_time` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '抽奖时间',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `create_time` (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='抽奖记录表';

--
-- 表的结构 `__PREFIX__coupon`
--
DROP TABLE IF EXISTS `__PREFIX__coupon`;
CREATE TABLE `__PREFIX__coupon` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(100) NOT NULL COMMENT '优惠卷代码',
  `name` varchar(255) NOT NULL COMMENT '优惠卷名称',
  `discount_type` tinyint(1) DEFAULT '1' COMMENT '优惠类型 1固定金额 2百分比',
  `discount_value` decimal(10,2) NOT NULL COMMENT '优惠金额/百分比',
  `min_amount` decimal(10,2) DEFAULT '0.00' COMMENT '最小使用金额',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态 1有效 0无效',
  `is_used` tinyint(1) DEFAULT '0' COMMENT '是否已使用 0未使用 1已使用',
  `user_id` int(11) DEFAULT NULL COMMENT '使用用户ID',
  `used_time` datetime DEFAULT NULL COMMENT '使用时间',
  `used_order` varchar(255) DEFAULT NULL COMMENT '使用订单号',
  `admin_id` int(11) DEFAULT '1' COMMENT '创建管理员ID',
  `create_time` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `expire_time` datetime DEFAULT NULL COMMENT '过期时间',
  `remark` varchar(500) DEFAULT NULL COMMENT '备注',
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `user_id` (`user_id`),
  KEY `status` (`status`),
  KEY `is_used` (`is_used`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='优惠卷表';

--
-- 表的结构 `__PREFIX__coupon_log`
--
DROP TABLE IF EXISTS `__PREFIX__coupon_log`;
CREATE TABLE `__PREFIX__coupon_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `coupon_id` int(11) NOT NULL COMMENT '优惠卷ID',
  `user_id` int(11) NOT NULL COMMENT '用户ID',
  `username` varchar(100) DEFAULT NULL COMMENT '用户名',
  `package_name` varchar(255) DEFAULT NULL COMMENT '购买套餐名称',
  `original_price` decimal(10,2) NOT NULL COMMENT '原价',
  `discount_amount` decimal(10,2) NOT NULL COMMENT '优惠金额',
  `final_price` decimal(10,2) NOT NULL COMMENT '实付金额',
  `order_no` varchar(255) DEFAULT NULL COMMENT '订单号',
  `use_time` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '使用时间',
  `ip` varchar(45) DEFAULT NULL COMMENT '使用IP',
  PRIMARY KEY (`id`),
  KEY `coupon_id` (`coupon_id`),
  KEY `user_id` (`user_id`),
  KEY `use_time` (`use_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='优惠卷使用记录表';

SET FOREIGN_KEY_CHECKS = 1;
