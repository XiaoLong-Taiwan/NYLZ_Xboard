# 每日签到插件 - 错误修复说明

## 错误：`spl_object_hash(): Argument #1 ($object) must be of type object, array given`

### 问题描述

在启用插件后，Xboard系统报错：
```
spl_object_hash(): Argument #1 ($object) must be of type object, array given
```

### 原因分析

这个错误发生在钩子注册过程中。在Xboard的钩子系统中，`HookManager::register`和`HookManager::registerFilter`方法使用`spl_object_hash()`函数来为回调生成唯一标识符。

问题代码位于`app/Services/Plugin/HookManager.php`：
```php
// 使用随机键存储回调，避免相同优先级覆盖
$actions[$hook][$priority][spl_object_hash($callback)] = $callback;
```

然而，我们的插件使用数组形式的回调：
```php
$this->listen('user.login', [$this, 'onUserLogin']);
```

PHP的`spl_object_hash()`函数只接受对象作为参数，不接受数组，因此导致错误。

### 解决方案

将数组形式的回调改为闭包（匿名函数）形式：

```php
// 修改前
$this->listen('user.login', [$this, 'onUserLogin']);
$this->filter('user.dashboard.widgets', [$this, 'addDashboardWidget']);

// 修改后
$this->listen('user.login', function($user) {
    $this->onUserLogin($user);
});
$this->filter('user.dashboard.widgets', function($widgets) {
    return $this->addDashboardWidget($widgets);
});
```

### 修复文件

1. `plugins/DailyCheckin/Plugin.php`
   - 修改了`registerHooks()`方法，使用闭包替代数组回调

### 技术说明

1. **PHP回调的两种形式**：
   - 数组形式：`[$object, 'methodName']`
   - 闭包形式：`function($param) use ($object) { $object->methodName($param); }`

2. **为什么使用闭包更安全**：
   - 闭包是对象，可以被`spl_object_hash()`处理
   - 闭包可以捕获上下文变量（通过`use`关键字）
   - 闭包提供更好的类型安全性

3. **钩子系统工作原理**：
   - 钩子注册时，回调被存储在容器中
   - 钩子触发时，按优先级顺序执行回调
   - 使用`spl_object_hash()`确保回调的唯一性

### 如何验证修复

1. 重新安装并启用插件
2. 检查Xboard系统是否正常运行，不再报错
3. 测试签到功能是否正常工作

### 预防措施

在开发Xboard插件时，始终使用闭包形式注册钩子，避免使用数组形式的回调：

```php
// 推荐方式
$this->listen('hook.name', function($param) {
    // 处理逻辑
});

// 不推荐方式
$this->listen('hook.name', [$this, 'methodName']);
```

### 相关文件

- `app/Services/Plugin/HookManager.php` - Xboard钩子管理器
- `app/Services/Plugin/AbstractPlugin.php` - 插件抽象类
- `plugins/DailyCheckin/Plugin.php` - 我们的插件实现
