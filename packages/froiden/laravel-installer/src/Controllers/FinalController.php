<?php

namespace Froiden\LaravelInstaller\Controllers;

use Illuminate\Routing\Controller;
use Froiden\LaravelInstaller\Helpers\InstalledFileManager;
use App\Models\User;
use App\Enums\DefaultSystemRolesEnum;
use Illuminate\Http\Request;

class FinalController extends Controller
{
    /**
     * Update installed file and display finished view.
     *
     * @param InstalledFileManager $fileManager
     * @return \Illuminate\View\View
     */
    public function finish(InstalledFileManager $fileManager, Request $request)
    {
        $user = User::where('access_panel', 'admin')->first();
        $fileManager->update();

        $details = $request->only(['name', 'email', 'mobile', 'password']);

        return view('vendor.installer.finished', compact('user', 'details'));
    }
}
