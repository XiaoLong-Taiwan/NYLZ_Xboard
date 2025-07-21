<?php

namespace Plugin\DailyCheckin;

use App\Services\Plugin\AbstractPlugin;
use Plugin\DailyCheckin\Services\CheckinService;
use Illuminate\Support\Facades\Route;

class Plugin extends AbstractPlugin
{
    /**
     * 插件启动时调用
     */
    public function boot(): void
    {
        // 注册路由
        $this->registerRoutes();

        // 注册钩子监听器
        $this->registerHooks();

        // 注册视图
        $this->registerViews();

        // 注册命令
        $this->registerCommands();
    }

    /**
     * 插件安装时调用
     */
    public function install(): void
    {
        // 安装前自动清理残留数据（方便测试）
        $this->cleanupBeforeInstall();

        // 运行数据库迁移
        $this->runMigrations();
    }

    /**
     * 插件卸载时调用
     */
    public function uninstall(): void
    {
        \Log::info('DailyCheckin: 开始卸载插件');

        try {
            // 1. 清理数据库表
            $this->dropTables();

            // 2. 清理迁移记录
            $this->cleanupMigrations();

            // 3. 清理其他数据
            $this->cleanupData();

            \Log::info('DailyCheckin: 插件卸载完成');

        } catch (\Exception $e) {
            \Log::error('DailyCheckin: 卸载过程中出现错误: ' . $e->getMessage());
            // 不抛出异常，确保卸载过程能够完成
        }
    }

    /**
     * 插件禁用时调用（cleanup方法）
     */
    public function cleanup(): void
    {
        \Log::info('DailyCheckin: 开始清理插件');

        try {
            // 移除钩子监听器
            $this->removeHooks();

            // 清理缓存
            $this->clearCache();

            \Log::info('DailyCheckin: 插件清理完成');

        } catch (\Exception $e) {
            \Log::warning('DailyCheckin: 清理过程中出现警告: ' . $e->getMessage());
        }
    }

    /**
     * 插件更新时调用
     */
    public function update(string $oldVersion, string $newVersion): void
    {
        // 处理版本更新逻辑
    }

    /**
     * 注册路由
     */
    protected function registerRoutes(): void
    {
        Route::prefix('api/v1/plugin/daily-checkin')
            ->middleware(['auth:sanctum'])
            ->group(function () {
                Route::get('/status', [\Plugin\DailyCheckin\Controllers\CheckinController::class, 'getStatus']);
                Route::post('/checkin', [\Plugin\DailyCheckin\Controllers\CheckinController::class, 'checkin']);
                Route::get('/history', [\Plugin\DailyCheckin\Controllers\CheckinController::class, 'getHistory']);
                Route::get('/ranking', [\Plugin\DailyCheckin\Controllers\CheckinController::class, 'getRanking']);
            });

        // 管理员路由
        Route::prefix('api/v1/admin/plugin/daily-checkin')
            ->middleware(['auth:sanctum', 'admin'])
            ->group(function () {
                Route::get('/stats', [\Plugin\DailyCheckin\Controllers\AdminController::class, 'getStats']);
                Route::get('/records', [\Plugin\DailyCheckin\Controllers\AdminController::class, 'getRecords']);
                Route::delete('/records/{id}', [\Plugin\DailyCheckin\Controllers\AdminController::class, 'deleteRecord']);
                Route::get('/config', [\Plugin\DailyCheckin\Controllers\AdminController::class, 'getConfig']);
                Route::post('/config', [\Plugin\DailyCheckin\Controllers\AdminController::class, 'updateConfig']);
                Route::post('/reset-stats', [\Plugin\DailyCheckin\Controllers\AdminController::class, 'resetUserStats']);
            });
    }

    /**
     * 注册钩子
     */
    protected function registerHooks(): void
    {
        // 用户登录时显示签到提醒
        $this->listen('user.login', function($user) {
            $this->onUserLogin($user);
        });

        // 在用户面板添加签到入口
        $this->filter('user.dashboard.widgets', function($widgets) {
            return $this->addDashboardWidget($widgets);
        });
    }

    /**
     * 注册视图
     */
    protected function registerViews(): void
    {
        // 注册视图命名空间
        view()->addNamespace('DailyCheckin', $this->getViewsPath());
    }

    /**
     * 用户登录事件处理
     */
    public function onUserLogin($user): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $checkinService = new CheckinService();
        $todayChecked = $checkinService->hasTodayChecked($user->id);
        
        if (!$todayChecked) {
            // 可以在这里添加签到提醒逻辑
            // 比如发送通知或设置session提醒
        }
    }

    /**
     * 添加仪表板小部件
     */
    public function addDashboardWidget($widgets): array
    {
        if (!$this->isEnabled()) {
            return $widgets;
        }

        $widgets[] = [
            'name' => 'daily_checkin',
            'title' => '每日签到',
            'component' => 'DailyCheckinWidget',
            'order' => 10
        ];

        return $widgets;
    }

    /**
     * 检查插件是否启用
     */
    protected function isEnabled(): bool
    {
        return (bool) $this->getConfig('enable', false);
    }

    /**
     * 运行数据库迁移
     */
    protected function runMigrations(): void
    {
        $migrationsPath = $this->getMigrationsPath();
        if (is_dir($migrationsPath)) {
            \Artisan::call('migrate', [
                '--path' => 'plugins/DailyCheckin/database/migrations',
                '--force' => true
            ]);
        }
    }

    /**
     * 注册命令
     */
    protected function registerCommands(): void
    {
        if (app()->runningInConsole()) {
            app('Illuminate\Contracts\Console\Kernel')->registerCommand(
                new \Plugin\DailyCheckin\Console\Commands\ResetContinuousCheckin()
            );
        }
    }

    /**
     * 安装前清理残留数据（方便测试）
     */
    protected function cleanupBeforeInstall(): void
    {
        try {
            \Log::info('DailyCheckin: 开始清理安装前残留数据');

            // 1. 删除插件记录
            $deleted = \DB::table('v2_plugins')->where('code', 'daily_checkin')->delete();
            if ($deleted > 0) {
                \Log::info("DailyCheckin: 删除了 {$deleted} 条插件记录");
            }

            // 2. 删除数据库表
            \DB::statement('DROP TABLE IF EXISTS daily_checkins');
            \DB::statement('DROP TABLE IF EXISTS checkin_stats');
            \Log::info('DailyCheckin: 删除了数据库表');

            // 3. 清理迁移记录
            $deletedMigrations = \DB::table('migrations')
                ->where('migration', 'like', '%daily_checkin%')
                ->orWhere('migration', 'like', '%checkin%')
                ->delete();
            if ($deletedMigrations > 0) {
                \Log::info("DailyCheckin: 删除了 {$deletedMigrations} 条迁移记录");
            }

            \Log::info('DailyCheckin: 安装前清理完成');

        } catch (\Exception $e) {
            \Log::warning('DailyCheckin: 清理过程中出现警告: ' . $e->getMessage());
            // 不抛出异常，继续安装过程
        }
    }

    /**
     * 删除数据库表
     */
    protected function dropTables(): void
    {
        try {
            \DB::statement('DROP TABLE IF EXISTS daily_checkins');
            \DB::statement('DROP TABLE IF EXISTS checkin_stats');
            \Log::info('DailyCheckin: 数据库表已删除');
        } catch (\Exception $e) {
            \Log::error('DailyCheckin: 删除数据库表失败: ' . $e->getMessage());
        }
    }

    /**
     * 清理迁移记录
     */
    protected function cleanupMigrations(): void
    {
        try {
            $deletedMigrations = \DB::table('migrations')
                ->where('migration', 'like', '%daily_checkin%')
                ->orWhere('migration', 'like', '%checkin%')
                ->delete();

            if ($deletedMigrations > 0) {
                \Log::info("DailyCheckin: 删除了 {$deletedMigrations} 条迁移记录");
            }
        } catch (\Exception $e) {
            \Log::error('DailyCheckin: 清理迁移记录失败: ' . $e->getMessage());
        }
    }

    /**
     * 移除钩子监听器
     */
    protected function removeHooks(): void
    {
        try {
            // 移除注册的钩子监听器（移除整个钩子）
            $this->removeListener('user.login');
            $this->removeListener('user.dashboard.widgets');
            \Log::info('DailyCheckin: 钩子监听器已移除');
        } catch (\Exception $e) {
            \Log::warning('DailyCheckin: 移除钩子监听器失败: ' . $e->getMessage());
        }
    }

    /**
     * 清理缓存
     */
    protected function clearCache(): void
    {
        try {
            // 清理插件相关的缓存
            \Cache::forget('daily_checkin_config');
            \Cache::forget('daily_checkin_stats');
            \Log::info('DailyCheckin: 缓存已清理');
        } catch (\Exception $e) {
            \Log::warning('DailyCheckin: 清理缓存失败: ' . $e->getMessage());
        }
    }

    /**
     * 清理其他数据
     */
    protected function cleanupData(): void
    {
        try {
            // 清理临时文件、日志等
            // 这里可以添加其他清理逻辑
            \Log::info('DailyCheckin: 其他数据清理完成');
        } catch (\Exception $e) {
            \Log::warning('DailyCheckin: 清理其他数据失败: ' . $e->getMessage());
        }
    }
}
