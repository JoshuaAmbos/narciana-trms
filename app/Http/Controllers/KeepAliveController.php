<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/**
 * "Stay logged in" button on the idle warning. Any request refreshes the session's last-activity time,
 * so this only has to answer "204 No Content".
 */
class KeepAliveController extends Controller
{
    public function __invoke(): Response
    {
        return response()->noContent();
    }
}
