<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ValidateAdminIP
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $ip_addresses = [
            '127.0.0.1',
            '83.249.252.205',
            '85.242.198.117',
            '94.191.152.174'
        ];

        if(! in_array($request->ip(), $ip_addresses)) {
            abort(403, 'Tough luck buddy!');
        } 
        return $next($request);
    }
}
