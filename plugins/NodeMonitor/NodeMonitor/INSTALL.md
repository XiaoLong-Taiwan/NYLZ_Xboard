# NodeMonitor 插件安装指南

## 📋 系统要求

- XBoard >= 1.0.0
- PHP >= 7.4
- Laravel Framework
- 网络连接（用于Telegram API调用）

## 🚀 安装步骤

### 1. 上传插件文件

将整个 `NodeMonitor` 目录上传到XBoard的 `plugins/` 目录下：

```
xboard/
└── plugins/
    └── NodeMonitor/
        ├── Plugin.php
        ├── config.json
        ├── Controllers/
        ├── routes/
        ├── Commands/
        └── README.md
```

### 2. 注册命令（可选）

如果要使用命令行功能，需要在Laravel的 `app/Console/Kernel.php` 中注册命令：

```php
protected $commands = [
    // 其他命令...
    \Plugin\NodeMonitor\Commands\NodeMonitorCommand::class,
];
```

### 3. 配置定时任务

在服务器的crontab中添加Laravel定时任务（如果尚未配置）：

```bash
# 编辑crontab
crontab -e

# 添加以下行（请替换为实际的项目路径）
* * * * * cd /path/to/xboard && php artisan schedule:run >> /dev/null 2>&1
```

### 4. 启用插件

1. 登录XBoard管理后台
2. 进入「插件管理」页面
3. 找到「节点状态监控」插件
4. 点击「启用」

## ⚙️ 配置插件

### Telegram Bot 配置

#### 创建Telegram Bot

1. 在Telegram中搜索 `@BotFather`
2. 发送 `/newbot` 开始创建流程
3. 按提示输入机器人名称（如：NodeMonitor Bot）
4. 输入机器人用户名（如：your_node_monitor_bot）
5. 复制获得的Bot Token

#### 获取Chat ID

**方法一：通过API获取**
1. 将机器人添加到目标群组或频道
2. 在群组中发送任意消息
3. 访问：`https://api.telegram.org/bot<YOUR_BOT_TOKEN>/getUpdates`
4. 在返回的JSON中找到 `"chat":{"id":-1001234567890}` 中的ID

**方法二：使用第三方工具**
- 搜索 `@userinfobot` 并发送 `/start`
- 转发群组消息给这个机器人获取群组ID

### 节点配置

在插件配置页面设置要监控的节点，格式为：
```
节点名称1:主机地址1:端口1,节点名称2:主机地址2:端口2
```

**示例：**
```
主服务器:example.com:443,数据库:db.example.com:3306,Redis:cache.example.com:6379
```

**支持的地址格式：**
- 域名：`example.com`
- IPv4：`192.168.1.100`
- IPv6：`[2001:db8::1]`（需要用方括号包围）

## 🧪 测试配置

### 1. 发送测试通知

**通过API：**
```bash
curl -X POST "https://your-domain.com/api/v1/node-monitor/test-notification" \
  -H "Authorization: Bearer YOUR_API_TOKEN"
```

**通过命令行：**
```bash
php artisan node-monitor:check --test-notify
```

### 2. 手动检查节点

```bash
php artisan node-monitor:check
```

### 3. 查看节点状态

```bash
php artisan node-monitor:check --status
```

## 🔧 高级配置

### 自定义检查间隔

默认每60秒检查一次，可以根据需要调整：
- **高频监控**：30秒（适用于关键服务）
- **标准监控**：60秒（推荐设置）
- **低频监控**：300秒（适用于稳定服务）

### 超时和重试设置

- **超时时间**：建议5-30秒，根据网络环境调整
- **重试次数**：建议2-5次，避免误报

### 通知策略

- **离线通知**：始终发送
- **恢复通知**：可选择开启/关闭
- **重复通知**：插件会避免重复发送相同状态的通知

## 📊 监控最佳实践

### 1. 节点选择

- 监控关键服务端口（如Web服务的80/443端口）
- 包含数据库连接（如MySQL的3306端口）
- 监控缓存服务（如Redis的6379端口）
- 考虑负载均衡器和CDN节点

### 2. 告警策略

- 设置合理的检查间隔，避免过于频繁
- 启用重试机制，减少网络抖动造成的误报
- 根据服务重要性决定是否启用恢复通知

### 3. 维护建议

- 定期检查日志文件
- 监控插件自身的运行状态
- 及时更新Telegram Bot Token（如有变更）

## 🔍 故障排除

### 常见问题

1. **插件无法启用**
   - 检查文件权限
   - 确认PHP版本兼容性
   - 查看Laravel日志

2. **定时任务不执行**
   - 确认crontab配置正确
   - 检查Laravel定时任务是否正常
   - 验证插件是否已启用

3. **通知发送失败**
   - 验证Bot Token正确性
   - 确认Chat ID格式
   - 检查网络连接
   - 查看Telegram API限制

4. **节点检查不准确**
   - 调整超时时间
   - 增加重试次数
   - 确认节点地址和端口
   - 检查防火墙设置

### 日志位置

- Laravel日志：`storage/logs/laravel.log`
- 插件相关日志搜索关键词：`node_monitor`、`NodeMonitor`

### 调试模式

在 `.env` 文件中设置：
```
APP_DEBUG=true
LOG_LEVEL=debug
```

## 📞 技术支持

如遇到问题，请提供以下信息：
- XBoard版本
- PHP版本
- 错误日志
- 插件配置（隐藏敏感信息）

联系方式：
- 作者：大熊
- 项目仓库：提交Issue获取支持

## 🔄 更新说明

更新插件时：
1. 备份当前配置
2. 替换插件文件
3. 重新配置（如有必要）
4. 测试功能正常

---

**注意：** 请妥善保管Telegram Bot Token，避免泄露给他人。