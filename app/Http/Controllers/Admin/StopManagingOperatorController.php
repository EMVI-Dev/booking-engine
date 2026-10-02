<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\OperatorAccountService;
use Illuminate\Http\RedirectResponse;

class StopManagingOperatorController extends Controller
{
    /**
     * Leave the operator desk the admin was managing and return to the admin operator list.
     */
    public function __invoke(OperatorAccountService $accounts): RedirectResponse
    {
        $accounts->stopManaging();

        return redirect()->route('admin.operators.index');
    }
}
