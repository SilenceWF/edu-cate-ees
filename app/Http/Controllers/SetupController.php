<?php

namespace App\Http\Controllers;

use App\Packs\PackException;
use App\Packs\PackLoader;
use App\Support\Settings;
use App\Support\SystemCheck;
use Illuminate\Http\Request;

/** First-run screen, available only until a company pack has been installed. */
class SetupController extends Controller
{
    public function show(Settings $settings)
    {
        if ($settings->installed()) {
            return redirect()->route('login');
        }

        return view('setup', ['hasDemo' => is_file(base_path('packs/demo/pack.yaml'))]);
    }

    public function install(Request $request, PackLoader $loader, Settings $settings)
    {
        abort_if($settings->installed(), 403);
        $request->validate(['pack' => 'nullable|file|mimes:zip|max:51200']);
        if ($request->hasFile('pack') && ($missing = app(SystemCheck::class)->missingFor('excel'))) {
            return back()->withErrors(['pack' => __('admin.system.missing', ['ext' => implode(', ', $missing)])]);
        }

        try {
            $summary = $loader->install($request->hasFile('pack') ? $request->file('pack')->getRealPath() : base_path('packs/demo'));
        } catch (PackException $e) {
            return back()->withErrors($e->errors);
        }

        return redirect()->route('login')->with('status', __('pack.installed', $summary));
    }
}
