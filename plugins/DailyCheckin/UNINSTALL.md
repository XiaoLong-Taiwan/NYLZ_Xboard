# 卸载指南

## 🗑️ **完整卸载说明**

每日签到插件提供了完整的卸载功能，确保卸载时删除所有相关数据，不留任何残留。

## 📋 **卸载方法**

### **方法一：管理后台卸载（推荐）**

1. **登录管理后台**
   - 进入 Xboard 管理后台
   - 导航到插件管理页面

2. **找到插件**
   - 在已安装插件列表中找到"每日签到"
   - 确认插件状态（启用/禁用）

3. **执行卸载**
   - 点击"卸载"按钮
   - 确认卸载操作
   - 等待卸载完成

### **方法二：命令行卸载**

```bash
cd plugins/DailyCheckin

# 使用专用卸载脚本（推荐）
php uninstall.php

# 或使用安装脚本的卸载功能
php install.php uninstall
```

## 🔍 **卸载过程详解**

### **第一阶段：禁用插件**
- 调用插件的 `cleanup()` 方法
- 移除钩子监听器
- 清理缓存数据
- 停止插件服务

### **第二阶段：卸载插件**
- 调用插件的 `uninstall()` 方法
- 删除数据库表
- 清理迁移记录
- 删除插件记录

### **第三阶段：验证清理**
- 检查插件记录是否删除
- 验证数据库表是否清理
- 确认迁移记录是否移除
- 验证缓存是否清理

## 🗄️ **删除的数据**

卸载时会删除以下所有数据：

### **数据库表**
- `daily_checkins` - 签到记录表
- `checkin_stats` - 签到统计表

### **数据库记录**
- `v2_plugins` 表中的插件记录
- `migrations` 表中的迁移记录

### **应用数据**
- 所有签到记录
- 用户签到统计
- 插件配置数据
- 缓存数据

### **系统集成**
- 钩子监听器
- 路由注册
- 视图命名空间
- 命令注册

## ⚠️ **重要提醒**

### **数据不可恢复**
- ⚠️ 卸载会**永久删除**所有签到数据
- ⚠️ 删除的数据**无法恢复**
- ⚠️ 请在卸载前确认是否需要备份数据

### **备份建议**
如果需要保留历史数据，请在卸载前备份：

```sql
-- 备份签到记录
CREATE TABLE daily_checkins_backup AS SELECT * FROM daily_checkins;

-- 备份统计数据
CREATE TABLE checkin_stats_backup AS SELECT * FROM checkin_stats;
```

### **生产环境注意事项**
- 🚨 在生产环境卸载前请三思
- 🚨 建议先在测试环境验证卸载过程
- 🚨 确保用户了解数据将被删除

## 🔧 **故障排除**

### **卸载失败**
如果通过管理后台卸载失败：

```bash
# 使用命令行强制卸载
cd plugins/DailyCheckin
php uninstall.php
```

### **残留数据检查**
卸载后如果发现残留数据：

```bash
# 使用清理脚本
php quick_cleanup.php

# 或手动检查
php verify_compatibility.php
```

### **手动清理**
如果自动卸载失败，可以手动清理：

```sql
-- 删除插件记录
DELETE FROM v2_plugins WHERE code = 'daily_checkin';

-- 删除数据库表
DROP TABLE IF EXISTS daily_checkins;
DROP TABLE IF EXISTS checkin_stats;

-- 清理迁移记录
DELETE FROM migrations WHERE migration LIKE '%daily_checkin%';
DELETE FROM migrations WHERE migration LIKE '%checkin%';
```

## ✅ **卸载验证**

卸载完成后，验证以下项目：

### **数据库检查**
```sql
-- 检查插件记录
SELECT * FROM v2_plugins WHERE code = 'daily_checkin';
-- 应该返回空结果

-- 检查数据库表
SHOW TABLES LIKE '%checkin%';
-- 应该返回空结果

-- 检查迁移记录
SELECT * FROM migrations WHERE migration LIKE '%checkin%';
-- 应该返回空结果
```

### **功能检查**
- [ ] 用户面板不再显示签到组件
- [ ] API接口返回404错误
- [ ] 管理后台插件列表中不再显示
- [ ] 相关路由不再可访问

## 🔄 **重新安装**

卸载完成后，如需重新安装：

1. **确认清理完成**
   - 运行验证检查
   - 确保没有残留数据

2. **重新安装**
   - 通过管理后台安装
   - 或使用安装脚本

3. **配置插件**
   - 重新设置配置参数
   - 测试功能是否正常

插件的完整卸载功能确保了干净的卸载过程，让你可以放心地安装、测试和卸载插件！
