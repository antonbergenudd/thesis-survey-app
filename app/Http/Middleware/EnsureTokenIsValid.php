<?php
 
namespace App\Http\Middleware;
 
use Closure;
use App\Models\User;
 
class EnsureTokenIsValid
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (! in_array($request->token, User::all()->pluck('token')->toArray())) {
            return redirect('login');
        }
 
        return $next($request);
    }
}