<?php

namespace App\Http\Middleware;

use App\Enums\Permission;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectWithoutMoneyAccess
{
    /**
     * The home page ("Bugün") is all money; a user who may not see money lands on the customers instead of a 403.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->can(Permission::MoneyView->value) !== true) {
            return redirect()->route('app.customers.index');
        }

        return $next($request);
    }
}
