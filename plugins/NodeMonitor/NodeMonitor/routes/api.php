<?php

use Illuminate\Support\Facades\Route;
use Plugin\NodeMonitor\Controllers\NodeMonitorController;

Route::group([
    'prefix' => 'api/v1/node-monitor',
    'middleware' => ['auth:sanctum'] // 需要认证
], function () {
    // 手动检查所有节点状态
    Route::post('/check', [NodeMonitorController::class, 'checkNodes']);
    
    // 获取节点状态
    Route::get('/status', [NodeMonitorController::class, 'getNodesStatus']);
    
    // 发送测试通知
    Route::post('/test-notification', [NodeMonitorController::class, 'sendTestNotification']);
    
    // 清除状态缓存
    Route::delete('/cache', [NodeMonitorController::class, 'clearCache']);
});

// 公开接口（不需要认证）
Route::group([
    'prefix' => 'api/v1/node-monitor/public'
], function () {
    // 获取节点状态概览（仅返回基本信息）
    Route::get('/overview', [NodeMonitorController::class, 'getNodesStatus']);
});