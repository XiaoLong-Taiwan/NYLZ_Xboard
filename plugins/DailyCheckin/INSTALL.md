# 每日签到插件安装指南

## ✅ 问题已修复

所有已知问题都已解决！主要修复内容：

1. **正确的目录位置**: 插件已移动到 `plugins/DailyCheckin/`
2. **正确的命名空间**: 使用 `Plugin\DailyCheckin` 而不是 `App\Plugins\DailyCheckin`
3. **配置文件格式**: 已修复为 Xboard 标准格式
4. **数据库兼容性**: 修复了外键数据类型不兼容问题

## 🚀 安装步骤

### 方法一：通过管理后台安装（推荐）

1. 登录 Xboard 管理后台
2. 进入插件管理页面
3. 找到"每日签到"插件
4. 点击"安装"按钮
5. 安装完成后点击"启用"
6. 进入插件配置页面设置参数

### 方法二：命令行安装

```bash
# 进入项目根目录
cd /path/to/xboard

# 如果之前安装失败，先清理数据
php plugins/DailyCheckin/cleanup.php

# 运行插件安装脚本
php plugins/DailyCheckin/install.php install

# 查看安装状态
php plugins/DailyCheckin/install.php status
```

## ⚙️ 配置说明

安装后需要配置以下参数：

### 基础设置
- **启用签到功能**: 开启/关闭插件
- **奖励类型**: 余额奖励 / 流量奖励 / 两者都有
- **基础余额奖励**: 每次签到获得的余额（单位：分）
- **基础流量奖励**: 每次签到获得的流量（单位：MB）

### 连续签到设置
- **连续签到奖励**: 是否启用连续签到额外奖励
- **连续奖励倍数**: 连续签到的奖励倍数（如1.5）
- **最大连续天数**: 连续奖励的最大天数（如7天）

### 其他设置
- **重置时间**: 每日签到重置时间（小时）
- **显示排行榜**: 是否显示签到排行榜

## 📊 API接口

插件安装后会自动注册以下API接口：

### 用户接口
```
GET  /api/v1/plugin/daily-checkin/status   - 获取签到状态
POST /api/v1/plugin/daily-checkin/checkin  - 执行签到
GET  /api/v1/plugin/daily-checkin/history  - 签到历史
GET  /api/v1/plugin/daily-checkin/ranking  - 排行榜
```

### 管理员接口
```
GET    /api/v1/admin/plugin/daily-checkin/stats    - 统计数据
GET    /api/v1/admin/plugin/daily-checkin/records  - 签到记录
DELETE /api/v1/admin/plugin/daily-checkin/records/{id} - 删除记录
GET    /api/v1/admin/plugin/daily-checkin/config   - 获取配置
POST   /api/v1/admin/plugin/daily-checkin/config   - 更新配置
```

## 🕐 定时任务（可选）

为了自动重置中断签到用户的连续天数，建议添加定时任务：

```bash
# 编辑 crontab
crontab -e

# 添加以下行（每天凌晨1点执行）
0 1 * * * cd /path/to/xboard && php artisan checkin:reset-continuous
```

## 🗄️ 数据库表

插件会自动创建以下数据库表：

- `daily_checkins` - 签到记录表
- `checkin_stats` - 签到统计表

## 🔧 故障排除

### 常见问题

1. **类名冲突错误**: 已修复，确保使用正确的命名空间
2. **配置文件错误**: 已修复为标准格式
3. **路径错误**: 插件已移动到正确位置
4. **外键约束错误**: 已移除外键约束，使用索引代替（与Xboard架构一致）

### 如果安装失败

如果遇到数据库相关错误，请运行清理脚本：

```bash
# 清理之前的安装残留
php plugins/DailyCheckin/cleanup.php

# 重新安装
php plugins/DailyCheckin/install.php install
```

### 检查插件状态

```bash
# 检查插件是否正确安装
php artisan route:list | grep daily-checkin

# 检查数据库表是否创建
php artisan tinker
>>> \Schema::hasTable('daily_checkins')
>>> \Schema::hasTable('checkin_stats')
```

## 📝 版本信息

- **版本**: 1.0.0
- **兼容性**: Xboard 1.0.0+
- **PHP要求**: 8.1+
- **Laravel要求**: 11.0+

## 🎯 下一步

1. 安装并启用插件
2. 配置奖励参数
3. 在前端集成签到组件
4. 设置定时任务（可选）
5. 测试签到功能

插件现在应该可以正常工作了！如果遇到问题，请检查日志文件或联系技术支持。
