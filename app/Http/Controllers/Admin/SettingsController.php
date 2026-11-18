<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Packs\PackException;
use App\Packs\PackExporter;
use App\Packs\PackLoader;
use App\Services\Audit;
use App\Support\Favicon;
use App\Support\Logo;
use App\Support\MailStatus;
use App\Support\Settings;
use App\Support\SystemCheck;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use Throwable;

class SettingsController extends Controller
{
    public function index(SystemCheck $system, Logo $logo, Favicon $favicon, MailStatus $mail)
    {
        return view('admin.settings', [
            'campaigns' => Campaign::orderByDesc('id')->get(),
            'brokenLink' => Audit::firstBrokenLink(),
            'system' => $system->report(),
            'logoUrl' => $logo->url(),
            'faviconUrl' => $favicon->url(),
            'mail' => $mail,
        ]);
    }

    public function logo(Request $request, Logo $logo)
    {
        $request->validate(['logo' => 'required|file|max:'.Logo::MAX_KB]);

        try {
            $logo->store($request->file('logo'));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['logo' => $e->getMessage()]);
        }
        Audit::log('settings.logo_changed', null, ['file' => $request->file('logo')->getClientOriginalName()]);

        return back()->with('status', __('admin.logo.saved'));
    }

    public function mailToggle(Request $request, Settings $settings, MailStatus $mail)
    {
        $on = $request->boolean('enabled');
        if ($on && ! $mail->configured()) {
            return back()->withErrors(['mail' => __('admin.mail.missing', ['keys' => implode(', ', $mail->missing())])]);
        }
        $settings->set('mail', array_merge((array) $settings->get('mail', []), ['sending_enabled' => $on]));
        Audit::log($on ? 'mail.sending_enabled' : 'mail.sending_disabled', null, ['mailer' => $mail->mailer(), 'from' => $mail->from()]);

        return back()->with('status', __($on ? 'admin.mail.enabled' : 'admin.mail.disabled'));
    }

    /** Send a test message to the signed-in admin (works even while sending is switched off). */
    public function mailTest(Request $request, MailStatus $mail)
    {
        $to = $request->user()->email;
        if (! $mail->configured()) {
            return back()->withErrors(['mail' => __('admin.mail.missing', ['keys' => implode(', ', $mail->missing())])]);
        }
        if (! $to) {
            return back()->withErrors(['mail' => __('admin.mail.no_own_email')]);
        }

        try {
            Mail::raw(__('admin.mail.test_body', ['company' => setting('company.name'), 'url' => url('/'), 'date' => local_dt(now())]),
                fn ($m) => $m->to($to)->subject(__('admin.mail.test_subject', ['company' => setting('company.name')])));
        } catch (Throwable $e) {
            report($e);
            Audit::log('mail.test_failed', $request->user(), ['error' => mb_substr($e->getMessage(), 0, 300)]);

            return back()->withErrors(['mail' => $e->getMessage()]);
        }
        Audit::log('mail.test_sent', $request->user(), ['to' => $to, 'from' => $mail->from()]);

        return back()->with('status', __('admin.mail.test_ok', ['to' => $to]));
    }

    public function favicon(Request $request, Favicon $favicon)
    {
        $request->validate(['favicon' => 'required|file|max:'.Favicon::MAX_KB]);

        try {
            $favicon->store($request->file('favicon'));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['favicon' => $e->getMessage()]);
        }
        Audit::log('settings.favicon_changed', null, ['file' => $request->file('favicon')->getClientOriginalName()]);

        return back()->with('status', __('admin.favicon.saved'));
    }

    public function faviconRemove(Favicon $favicon)
    {
        $favicon->remove();
        Audit::log('settings.favicon_removed');

        return back()->with('status', __('admin.favicon.removed'));
    }

    public function logoRemove(Logo $logo)
    {
        $logo->remove();
        Audit::log('settings.logo_removed');

        return back()->with('status', __('admin.logo.removed'));
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

    public function packUpload(Request $request, PackLoader $loader, SystemCheck $system)
    {
        $request->validate(['pack' => 'required|file|mimes:zip|max:51200']);
        if ($missing = $system->missingFor('excel')) {
            return back()->withErrors(['pack' => __('admin.system.missing', ['ext' => implode(', ', $missing)])]);
        }

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
