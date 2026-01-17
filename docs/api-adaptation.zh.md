# 物流追踪 API 适配指南

[English](api-adaptation.md) | 中文

本文档说明如何编写自定义物流追踪 API 适配器，以及必须满足的要求。适配器文件放在 `track_api/` 目录中，并由 `track_api.php` 根据配置的 provider 名称加载。

## 适配要求

你的 provider **必须**：

1. **放在 `track_api/`**，文件名与 provider key 一致（例如 `mycarrier.php`）。
2. **提供处理函数** `track_api_<provider>_events`，参数为：
   - `$logistics_no`（字符串，运单号）
   - `$send_way`（字符串或 null，配送方式）
3. **返回数组**，包含以下键：
   - `status`（字符串）：用户侧推荐值为 `ok`、`no_data`、`untrackable`。
   - `data`（数组）：事件列表（当 `status` 非 `ok` 时为空）。
   - `raw`（可选字符串）：原始响应或调试信息（用于管理员调试）。
4. **事件结构需统一**：
   ```php
   [
     [
       'time' => '2024-01-01 12:34:56',
       'desc' => 'Shipment picked up',
       'loc'  => 'Shanghai, CN' // 可选
     ],
     ...
   ]
   ```
5. **不要在 provider 内 `exit`/`die`**，而是返回 `status` + `raw` 让调用方决定如何展示错误。

`track_api.php` 会对 provider 名称做过滤并校验处理函数是否存在，因此文件名和函数名必须保持一致。

## 状态含义

- `ok`：成功获取追踪数据。
- `no_data`：接口有响应但没有可用事件（或远程错误）。
- `untrackable`：该配送方式无法在公网追踪（例如平信）。
- `provider_missing` / `provider_invalid`：由 `track_api.php` 返回，表示文件或处理函数缺失。

## 新增 provider 步骤

1. **创建文件**：`track_api/mycarrier.php`。
2. **实现处理函数**：
   ```php
   <?php
   function track_api_mycarrier_events($logistics_no, $send_way = null) {
       $logistics_no = trim((string)$logistics_no);
       $send_way = strtolower(trim((string)$send_way));

       if ($logistics_no === '') {
           return ['status' => 'no_data', 'data' => [], 'raw' => 'Missing tracking number.'];
       }

       // 调用你的 API，并规范化事件结构。
       $events = [];

       return [
           'status' => empty($events) ? 'no_data' : 'ok',
           'data' => $events,
           'raw' => ''
       ];
   }
   ```
3. **在 `config.php` 中配置 provider**：
   ```php
   $gsTrackingProvider = 'mycarrier';
   // 如需 API key，请在此新增配置变量。
   ```
4. **验证效果**：打开订单详情页，确认物流时间线渲染正常。

## 推荐实践

- **时间格式统一**为 `Y-m-d H:i:s`。
- **优先返回本地化描述**（如果 API 支持）。
- **设置请求超时**（cURL timeout 是合理默认值）。
- **API key 放在 `config.php`**，不要硬编码在 provider 文件里。
- **`raw` 存原始响应**，便于管理员调试。

## 参考实现

`track_api/17track.php` 是标准参考实现：包含 API key 检查、注册后拉取、响应解析与事件规范化流程。建议以此为模板扩展。
