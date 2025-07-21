# 每日签到插件

一个功能完整的每日签到系统插件，支持余额和流量奖励，连续签到额外奖励。

## 功能特性

- ✅ **每日签到** - 用户每天可签到一次获得奖励
- 🎁 **多种奖励** - 支持余额、流量或两者同时奖励
- 🔥 **连续签到** - 连续签到获得额外奖励倍数
- 📊 **统计数据** - 完整的签到统计和历史记录
- 🏆 **排行榜** - 多维度签到排行榜
- ⚙️ **灵活配置** - 丰富的管理员配置选项
- 🎨 **美观界面** - 现代化的用户界面组件

## 安装方法

### 方法一：通过管理后台安装（推荐）
1. 登录 Xboard 管理后台
2. 进入插件管理页面
3. 找到"每日签到"插件
4. 点击"安装"按钮（会自动清理残留数据）
5. 安装完成后点击"启用"

### 方法二：使用安装脚本
```bash
cd plugins/DailyCheckin
php install.php install  # 会自动清理残留数据
```

### 测试专用清理工具
```bash
# 快速清理（测试时使用）
php quick_cleanup.php

# 完整清理
php cleanup.php

# 验证配置
php test_config.php
```

### 🎯 **自动清理功能**
插件现在具备自动清理功能：
- ✅ 每次安装前自动清理残留数据
- ✅ 无需手动删除旧数据
- ✅ 方便反复测试安装

## 卸载方法

### 方法一：通过管理后台卸载（推荐）
1. 进入插件管理页面
2. 找到"每日签到"插件
3. 点击"卸载"按钮
4. 确认卸载（会自动删除所有数据）

### 方法二：使用卸载脚本
```bash
cd plugins/DailyCheckin

# 专用卸载脚本（推荐）
php uninstall.php

# 或使用安装脚本的卸载功能
php install.php uninstall
```

### 🗑️ **完整卸载功能**
卸载时会自动删除：
- ✅ 插件记录
- ✅ 所有数据库表（daily_checkins, checkin_stats）
- ✅ 迁移记录
- ✅ 所有签到数据
- ✅ 缓存数据
- ✅ 钩子监听器

## 配置说明

### 基础配置

- **启用签到功能**: 控制插件的开启/关闭
- **奖励类型**: 
  - `balance` - 仅余额奖励
  - `traffic` - 仅流量奖励  
  - `both` - 余额+流量奖励
- **基础余额奖励**: 每次签到获得的基础余额（单位：分）
- **基础流量奖励**: 每次签到获得的基础流量（单位：MB）

### 连续签到配置

- **连续签到奖励**: 是否启用连续签到额外奖励
- **连续奖励倍数**: 连续签到的奖励倍数（如1.5表示每连续一天奖励增加50%）
- **最大连续天数**: 连续签到奖励的最大天数，超过后重置倍数
- **重置时间**: 每日签到重置时间（小时）

### 界面配置

- **显示签到排行榜**: 是否在用户界面显示排行榜

## API接口

### 用户接口

#### 获取签到状态
```
GET /api/v1/plugin/daily-checkin/status
```

#### 执行签到
```
POST /api/v1/plugin/daily-checkin/checkin
```

#### 获取签到历史
```
GET /api/v1/plugin/daily-checkin/history?page=1&limit=30
```

#### 获取排行榜
```
GET /api/v1/plugin/daily-checkin/ranking?type=continuous&limit=10
```

排行榜类型：
- `continuous` - 当前连续签到天数
- `max_continuous` - 最大连续签到天数
- `total` - 总签到次数
- `balance` - 累计获得余额
- `traffic` - 累计获得流量

### 管理员接口

#### 获取统计数据
```
GET /api/v1/admin/plugin/daily-checkin/stats
```

#### 获取签到记录
```
GET /api/v1/admin/plugin/daily-checkin/records?page=1&limit=20
```

#### 删除签到记录
```
DELETE /api/v1/admin/plugin/daily-checkin/records/{id}
```

#### 获取/更新配置
```
GET /api/v1/admin/plugin/daily-checkin/config
POST /api/v1/admin/plugin/daily-checkin/config
```

#### 重置用户统计
```
POST /api/v1/admin/plugin/daily-checkin/reset-stats
```

## 数据库结构

### daily_checkins 表
存储每次签到的详细记录
- `user_id`: int (与v2_user.id兼容)
- `balance_reward`: int (余额奖励，单位：分)
- `traffic_reward`: bigint (流量奖励，单位：字节)
- `created_at/updated_at`: int (Unix时间戳)

### checkin_stats 表
存储用户的签到统计数据
- `user_id`: int (与v2_user.id兼容)
- `total_balance_earned`: bigint (累计余额)
- `total_traffic_earned`: bigint (累计流量)
- `created_at/updated_at`: int (Unix时间戳)

## 定时任务

建议添加以下定时任务来自动重置中断签到用户的连续天数：

```bash
# 每天凌晨1点执行
0 1 * * * php artisan checkin:reset-continuous
```

## 钩子集成

插件集成了以下钩子：

- `user.login` - 用户登录时检查签到状态
- `user.dashboard.widgets` - 在用户面板添加签到小部件

## 前端集成

插件提供了现成的前端组件：

- **签到小部件** - 可嵌入到用户仪表板
- **签到历史页面** - 显示用户签到记录
- **排行榜组件** - 显示签到排行榜

## 奖励计算规则

### 基础奖励
根据配置的基础奖励值发放

### 连续奖励
连续签到的奖励倍数计算公式：
```
倍数 = 1 + (当前连续天数-1) * (配置倍数-1) / (最大连续天数-1)
```

例如：配置倍数1.5，最大连续天数7
- 第1天：1.0倍
- 第2天：1.08倍  
- 第3天：1.17倍
- ...
- 第7天：1.5倍

## 安全考虑

- 每个用户每天只能签到一次（数据库唯一约束）
- 记录签到IP和User-Agent用于审计
- 所有API接口都需要用户认证
- 管理员接口需要管理员权限
- 使用索引而非外键约束（与Xboard架构保持一致）

## 故障排除

### 常见问题

1. **签到失败** - 检查插件是否启用，用户是否已认证
2. **奖励未到账** - 检查用户余额/流量字段是否正确更新
3. **连续天数错误** - 运行重置命令或手动调整统计数据
4. **事务错误** - 已修复嵌套事务问题，使用优化的事务处理

### 日志记录

插件会记录关键操作的日志，可在Laravel日志中查看：
- 签到成功/失败
- 奖励发放记录
- 定时任务执行结果

## 版本历史

### v1.0.0
- 初始版本
- 基础签到功能
- 连续签到奖励
- 管理后台
- API接口
- 前端组件
