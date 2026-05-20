<?php

namespace Webhub\BackupViewer\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Webhub\BackupViewer\BackupViewer;

class Authorize
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(BackupViewer::check($request), 403);

        return $next($request);
    }
}
