<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Packs\PackException;
use App\Packs\PackExporter;
use App\Packs\PackLoader;
use App\Services\Audit;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class SettingsController extends Controller
{
    public function index()
    {
        return view('admin.settings', [
            'campaigns' => Campaign::orderByDesc('id')->get(),
            'brokenLink' => Audit::firstBrokenLink(),
        ]);
    }

    public function kioskPin(Request $request, Settings $settings)
    {
        $data = $request->validate(['pin' => ['required', 'regex:/^\d{4,8}$/', 'confirmed']], ['pin.regex' => __('admin.settings.pin_format')]);
        $settings->set('kiosk', ['pin_hash' => Hash::make($data['pin'])]);
        Audit::log('settings.kiosk_pin_changed');

        return back()->with('status', __('admin.settings.pin_saved'));
    }

    public function quiz(Request $request, Settings $settings)
    {
        $data = $request->validate(['pass_threshold' => 'required|integer|min:1|max:100']);
        Audit::log('settings.pass_threshold_changed', null, ['from' => $settings->get('quiz.pass_threshold'), 'to' => (int) $data['pass_threshold']]);
        $settings->set('quiz', ['pass_threshold' => (int) $data['pass_threshold']]);

        return back()->with('status', __('admin.settings.saved'));
    }

    /** Start a new training cycle, or switch back to an existing one. */
    public function campaign(Request $request)
    {
        if ($request->filled('make_current')) {
            $campaign = Campaign::findOrFail($request->integer('make_current'));
        } else {
            $data = $request->validate(['name' => 'required|string|max:100', 'starts_on' => 'nullable|date', 'ends_on' => 'nullable|date|after_or_equal:starts_on']);
            $campaign = Campaign::create($data);
        }
        $campaign->makeCurrent();
        Audit::log('campaign.made_current', null, ['campaign_id' => $campaign->id, 'name' => $campaign->name]);

        return back()->with('status', __('admin.settings.campaign_started', ['name' => $campaign->name]));
    }

    public function packUpload(Request $request, PackLoader $loader)
    {
        $request->validate(['pack' => 'required|file|mimes:zip|max:51200']);

        try {
            $summary = $loader->install($request->file('pack')->getRealPath());
        } catch (PackException $e) {
            return back()->withErrors($e->errors);
        }

        return back()->with('status', __('pack.installed', $summary));
    }

    public function packExport(PackExporter $exporter)
    {
        Audit::log('pack.exported');

        return response()->download($exporter->export())->deleteFileAfterSend();
    }
}
