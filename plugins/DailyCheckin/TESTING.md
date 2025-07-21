# 测试指南

## 🧪 **快速测试流程**

### **1. 快速清理**
```bash
cd plugins/DailyCheckin
php quick_cleanup.php
```

### **2. 通过管理后台安装**
1. 进入 Xboard 管理后台
2. 插件管理 → 找到"每日签到"
3. 点击"安装"（自动清理+安装）
4. 点击"启用"

### **3. 测试功能**
- 访问用户面板查看签到组件
- 测试API接口
- 检查数据库表创建

## 🛠️ **清理工具**

### **快速清理（推荐）**
```bash
php quick_cleanup.php
```
- 最快速的清理方式
- 适合反复测试

### **完整清理**
```bash
php cleanup.php
```
- 详细的清理过程
- 显示清理详情

### **安装脚本清理**
```bash
php install.php install
```
- 安装前自动清理
- 一步到位

## 🔍 **验证工具**

### **配置验证**
```bash
php test_config.php
```

### **兼容性检查**
```bash
php verify_compatibility.php
```

### **安装前检查**
```bash
php pre_install_check.php
```

## 📊 **测试API接口**

### **用户接口**
```bash
# 获取签到状态
curl -H "Authorization: Bearer YOUR_TOKEN" \
     http://your-domain/api/v1/plugin/daily-checkin/status

# 执行签到
curl -X POST -H "Authorization: Bearer YOUR_TOKEN" \
     http://your-domain/api/v1/plugin/daily-checkin/checkin

# 获取签到历史
curl -H "Authorization: Bearer YOUR_TOKEN" \
     http://your-domain/api/v1/plugin/daily-checkin/history

# 获取排行榜
curl -H "Authorization: Bearer YOUR_TOKEN" \
     http://your-domain/api/v1/plugin/daily-checkin/ranking
```

### **管理员接口**
```bash
# 获取统计数据
curl -H "Authorization: Bearer ADMIN_TOKEN" \
     http://your-domain/api/v1/admin/plugin/daily-checkin/stats

# 获取签到记录
curl -H "Authorization: Bearer ADMIN_TOKEN" \
     http://your-domain/api/v1/admin/plugin/daily-checkin/records
```

## 🗑️ **卸载测试**

### **卸载工具**
```bash
# 专用卸载脚本（推荐）
php uninstall.php

# 安装脚本的卸载功能
php install.php uninstall

# 快速清理（不完整卸载）
php quick_cleanup.php
```

### **卸载验证**
卸载后检查以下项目：
- [ ] 插件记录已删除
- [ ] 数据库表已删除
- [ ] 迁移记录已清理
- [ ] 缓存已清理
- [ ] 钩子监听器已移除

## 🎯 **常见测试场景**

### **场景1：首次安装**
1. 确保数据库干净
2. 通过管理后台安装
3. 验证表创建和配置

### **场景2：重复安装**
1. 不需要手动清理
2. 直接重新安装
3. 自动清理生效

### **场景3：配置修改**
1. 修改插件配置
2. 重新安装测试
3. 验证配置生效

### **场景4：完整卸载测试**
1. 安装插件并创建测试数据
2. 通过管理后台卸载
3. 验证所有数据已删除
4. 重新安装验证干净环境

### **场景5：数据迁移**
1. 创建测试数据
2. 重新安装
3. 验证数据处理

## ⚠️ **注意事项**

1. **测试环境**: 请在测试环境进行，避免影响生产数据
2. **备份数据**: 重要数据请提前备份
3. **权限检查**: 确保有数据库操作权限
4. **日志查看**: 出现问题时查看Laravel日志

## 🚀 **自动化测试**

插件内置自动清理功能，每次安装都会：
- ✅ 自动删除旧的插件记录
- ✅ 自动删除旧的数据表
- ✅ 自动清理迁移记录
- ✅ 重新创建干净的环境

这样你就可以专注于功能测试，而不用担心数据残留问题！
