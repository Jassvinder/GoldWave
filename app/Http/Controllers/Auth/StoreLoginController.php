<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\LoginWithStoreCredentials;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StoreLoginRequest;
use Illuminate\Http\RedirectResponse;

/** T-117 (19-09-2026) — Admin/Store Login, one of the two tabs on the shared `/login` page (the other being Super Admin's own Fortify email+password login). */
class StoreLoginController extends Controller
{
    public function login(StoreLoginRequest $request, LoginWithStoreCredentials $action): RedirectResponse
    {
        $action($request->string('store_code')->toString(), $request->string('password')->toString());
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }
}
