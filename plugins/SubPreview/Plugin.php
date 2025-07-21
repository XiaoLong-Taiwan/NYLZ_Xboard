<?php

namespace Plugin\SubPreview;

use App\Services\Plugin\AbstractPlugin;
use App\Services\UserService;
use App\Utils\Helper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

class Plugin extends AbstractPlugin
{
    public function boot(): void
    {
        $this->registerHooks();
    }

    protected function registerHooks(): void
    {
        $this->listen('client.subscribe.before', function ($user) {
            if ($this->isBrowserAccess(request())) {
                return $this->handleBrowserSubscribe();
            }
            return null;
        });
    }


    /**
     * 处理浏览器访问订阅的情况
     */
    private function handleBrowserSubscribe()
    {
        $user = Auth::user();
        $userService = new UserService();
        $useTraffic = $user['u'] + $user['d'];
        $totalTraffic = $user['transfer_enable'];
        $remainingTraffic = Helper::trafficConvert($totalTraffic - $useTraffic);
        $expiredDate = $user['expired_at'] ? date('Y-m-d', $user['expired_at']) : __('Unlimited');
        $resetDay = $userService->getResetDay($user);

        // 获取通用订阅地址
        $subscriptionUrl = Helper::getSubscribeUrl($user->token);

        // 生成二维码
        $writer = new \BaconQrCode\Writer(
            new \BaconQrCode\Renderer\ImageRenderer(
                new \BaconQrCode\Renderer\RendererStyle\RendererStyle(200),
                new \BaconQrCode\Renderer\Image\SvgImageBackEnd()
            )
        );
        $qrCode = base64_encode($writer->writeString($subscriptionUrl));

        $data = [
            'username' => $user->email,
            'status' => $userService->isAvailable($user) ? 'active' : 'inactive',
            'data_limit' => $totalTraffic ? Helper::trafficConvert($totalTraffic) : '∞',
            'data_used' => Helper::trafficConvert($useTraffic),
            'expired_date' => $expiredDate,
            'reset_day' => $resetDay,
            'subscription_url' => $subscriptionUrl,
            'qr_code' => $qrCode
        ];

        // 只有当 device_limit 不为 null 时才添加到返回数据中
        if ($user->device_limit !== null) {
            $data['device_limit'] = $user->device_limit;
        }


        return $this->intercept(
            response($this->view('subscribe', $data))
        );
    }

    /**
     * 检查是否是浏览器访问
     */
    private function isBrowserAccess(Request $request): bool
    {
        $userAgent = strtolower($request->input('flag', $request->header('User-Agent', '')));
        return str_contains($userAgent, 'mozilla')
            || str_contains($userAgent, 'chrome')
            || str_contains($userAgent, 'safari')
            || str_contains($userAgent, 'edge');
    }
}