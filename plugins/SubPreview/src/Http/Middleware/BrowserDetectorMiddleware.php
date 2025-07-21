<?php

namespace Plugin\BrowserDetector\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class BrowserDetectorMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // 将浏览器检测结果存储在请求中
        $request->attributes->set('is_browser', $this->isBrowserAccess($request));
        
        return $next($request);
    }

    /**
     * 检测是否是浏览器访问
     */
    protected function isBrowserAccess(Request $request): bool
    {
        $userAgent = $request->header('User-Agent');
        $accept = $request->header('Accept');

        if (empty($userAgent)) {
            return false;
        }

        if (str_contains($accept, 'text/html')) {
            return true;
        }

        $browserSignatures = [
            'Mozilla',
            'Chrome',
            'Safari',
            'Firefox',
            'Edge',
            'Opera'
        ];

        foreach ($browserSignatures as $signature) {
            if (str_contains($userAgent, $signature)) {
                return true;
            }
        }

        return false;
    }
} 