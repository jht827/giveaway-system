# 草案：自动到点开启

[English](auto-event-draft.md) | 中文

## 目标
- 新增事件模式：在指定开始时间后**自动开放预约**。
- 活动在开始前**可见**，但若提前进入，UI/API 返回“还没到时间”。
- 首页在存在开始时间时**展示开放时间**。
- 活动截止时间精度从**天级别**提升到**分钟级**。

## 数据库变更（草案）

### 1) 新增自动开放时间
在 `events` 表新增字段，用于存放**开放预约时间**。

```sql
ALTER TABLE events
  ADD COLUMN start_at DATETIME NULL COMMENT 'Reservation open time (auto-open)';
```

说明：
- 保持现有 `is_hidden` 语义不变。
- `start_at` 控制何时可预约。未到时间时活动可见但不可预约。
- `start_at` 为 NULL 时视为“立即开放”（当前行为）。

### 2) 提升截止时间精度
把 `due_date` 从 `DATE` 修改为 `DATETIME`，以支持分钟级时间。

```sql
ALTER TABLE events
  MODIFY COLUMN due_date DATETIME NULL COMMENT 'Event due time (minute precision)';
```

### 可选：开放时间索引
如需高效查询即将开放的活动：

```sql
CREATE INDEX idx_events_start_at ON events (start_at);
```

## 应用行为（草案）

### 活动可见性
- `is_hidden = 0` → 活动显示在列表中，不受 `start_at` 影响。
- `is_hidden = 1` → 活动完全隐藏（保持不变）。

### 预约入口限制
当用户（包含管理员/活动所有者）尝试预约时：
- `start_at` 为 NULL → 允许（现有行为）。
- `start_at` 在未来 → 阻止，提示：**“还没到时间 / Not time yet.”**
- `start_at` <= 当前时间 → 允许（仍需满足其他规则）。

### 首页展示
- 若活动存在 `start_at`，在首页卡片/列表中展示：
  - 示例：`开放时间: 2026-01-15 20:30`
- 若 `start_at` 为 NULL，则不显示开放时间。

## 数据回填 / 迁移说明
- 现有 `due_date` 为 `DATE` 的数据，迁移到 `DATETIME` 时默认补 `00:00`。
- 旧活动如需默认立即开放，可把 `start_at` 设为 `NULL`。

## API / UI 注意事项（草案）
- 活动数据应包含 `start_at`，并在首页及管理员编辑页展示。
- 校验规则：
  - `start_at` 与 `due_date` 同时存在时，应保证 `start_at` <= `due_date`。
  - `autogroup` 尚未实现，暂不与其绑定新行为。
  - 时间以 UTC+8 存储；若用户不在 UTC+8，应按本地时区展示。

## 待确认问题
1. 当 `autogroup` 或其他标志开启时，是否要求必须设置 `start_at`？
