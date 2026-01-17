# Giveaway System 数据库结构（当前）

[English](database-schema.md) | 中文

本文档描述系统当前使用的 MySQL 结构。

## 数据库
- 数据库名称：`giveaway_sys`
- MySQL 版本（导出来源）：`8.0.35`
- 默认字符集：`utf8mb3`

## 数据表

### `users`
用户账号与分组信息。

| 列名 | 类型 | 可空 | 默认值 | 说明 |
| --- | --- | --- | --- | --- |
| `uid` | varchar(50) | NO | — | 主键。用户标识。 |
| `pwdhash` | varchar(255) | NO | — | 密码哈希。 |
| `qq` | varchar(20) | NO | — | QQ 标识。 |
| `user_group` | enum('new','auto','owner') | YES | `new` | 用户分组权限。 |
| `res_count` | int | YES | `0` | 预约次数。 |
| `get_count` | int | YES | `0` | 成功领取次数。 |
| `autoget` | tinyint(1) | YES | `0` | 自动领取标记。 |
| `verified` | tinyint(1) | YES | `0` | 认证标记。 |
| `disabled` | tinyint(1) | YES | `0` | 禁用标记。 |

主键：`uid`

---

### `addresses`
用户收货地址。

| 列名 | 类型 | 可空 | 默认值 | 说明 |
| --- | --- | --- | --- | --- |
| `aid` | int | NO | AUTO_INCREMENT | 主键。 |
| `uid` | varchar(50) | YES | NULL | 外键，关联 `users.uid`。 |
| `name` | varchar(255) | YES | NULL | 收件人姓名。 |
| `postcode` | varchar(20) | YES | NULL | 邮编。 |
| `addr` | text | YES | NULL | 地址文本。 |
| `phone` | varchar(20) | YES | NULL | 电话号码。 |
| `is_default` | tinyint(1) | YES | `0` | 默认地址标记。 |
| `is_intl` | tinyint(1) | YES | `0` | 国际地址标记。 |
| `is_deleted` | tinyint(1) | YES | `0` | 软删除标记。 |

索引：
- `PRIMARY KEY (aid)`
- `KEY uid (uid)`

约束：
- `addresses_ibfk_1`: `uid` → `users.uid`

---

### `events`
分发活动定义。

| 列名 | 类型 | 可空 | 默认值 | 说明 |
| --- | --- | --- | --- | --- |
| `eid` | char(4) | NO | — | 主键。活动 ID。 |
| `name` | varchar(100) | NO | — | 活动名称。 |
| `due_date` | datetime | YES | NULL | 截止时间（分钟精度）。 |
| `start_at` | datetime | YES | NULL | 预约开放时间。 |
| `total` | int | NO | — | 总库存。 |
| `used` | int | YES | `0` | 已使用库存。 |
| `send_date` | varchar(50) | YES | NULL | 发货日期/备注。 |
| `allow_group` | varchar(50) | YES | `new,auto` | 允许的用户组。 |
| `autogroup` | tinyint(1) | YES | `0` | 自动分组标记。 |
| `choice_amount` | int | YES | `1` | 允许选择数量。 |
| `send_way` | varchar(50) | YES | `post` | 配送方式。 |
| `xa_allow` | tinyint(1) | YES | `0` | XA 允许标记。 |
| `is_hidden` | tinyint(1) | YES | `0` | 隐藏标记。 |

主键：`eid`

索引：
- `PRIMARY KEY (eid)`
- `KEY idx_events_start_at (start_at)`

---

### `orders`
用户预约/订单，与活动和地址关联。

| 列名 | 类型 | 可空 | 默认值 | 说明 |
| --- | --- | --- | --- | --- |
| `oid` | varchar(20) | NO | — | 主键。订单 ID。 |
| `uid` | varchar(50) | YES | NULL | 外键，关联 `users.uid`。 |
| `eid` | char(4) | YES | NULL | 外键，关联 `events.eid`。 |
| `aid` | int | YES | NULL | 外键，关联 `addresses.aid`。 |
| `choice` | int | YES | NULL | 选项序号。 |
| `xa` | char(1) | YES | `0` | XA 选择。 |
| `is_auto` | tinyint(1) | YES | `0` | 自动标记。 |
| `state` | tinyint | YES | `0` | 订单状态。 |
| `logistics_no` | varchar(100) | YES | NULL | 物流单号。 |
| `user_hidden` | tinyint(1) | YES | `0` | 对用户隐藏标记。 |

索引：
- `PRIMARY KEY (oid)`
- `KEY uid (uid)`
- `KEY eid (eid)`
- `KEY fk_order_address (aid)`

约束：
- `orders_ibfk_1`: `uid` → `users.uid`
- `orders_ibfk_2`: `eid` → `events.eid`
- `fk_order_address`: `aid` → `addresses.aid`
