<?php

namespace App\Http\Controllers;

use App\Mail\ToolkitSubmissionMail;
use App\Models\ToolkitItem;
use App\Models\ToolkitSubmission;
use App\Services\Audit;
use App\Support\MailStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * The ISMS toolkit: forms, procedures and guides that managers, management and IT use in their
 * daily work, with a fresh copy of each template on download and direct submission of a filled-in
 * form to the security officer. Downloads and submissions go to the audit log.
 */
class ToolkitController extends Controller
{
    /** Filled-in forms people may submit. */
    public const SUBMISSION_EXTENSIONS = ['docx', 'xlsx', 'pdf', 'zip'];

    public function index(Request $request, MailStatus $mail)
    {
        $user = $request->user();
        $type = in_array($request->query('type'), ToolkitItem::TYPES, true) ? $request->query('type') : '';
        $q = mb_substr(trim((string) $request->query('q', '')), 0, 100);
        $items = ToolkitItem::ordered(ToolkitItem::active());

        return view('toolkit.index', [
            'items' => $items,
            'codes' => $items->pluck('code')->all(),
            'counts' => collect(ToolkitItem::TYPES)->mapWithKeys(fn ($t) => [$t => $items->where('type', $t)->count()])->all(),
            'visibleIds' => $items->filter(fn (ToolkitItem $i) => $i->matches($type, $q))->pluck('id')->all(),
            'type' => $type,
            'q' => $q,
            'mySubmissions' => $user->toolkitSubmissions()->with('item')->latest('submitted_at')->latest('id')->limit(10)->get(),
            'allSubmissions' => $user->isStaffViewer()
                ? ToolkitSubmission::with('item', 'user')->latest('submitted_at')->latest('id')->limit(100)->get()
                : null,
            'canSend' => $mail->canSend(),
            'recipient' => setting('company.contact_email'),
        ]);
    }

    /** A fresh copy of the master template, under a clean dated name; the master is never touched. */
    public function download(Request $request, ToolkitItem $item)
    {
        abort_unless(($item->is_active || $request->user()->hasRole('admin')) && $item->hasFile(), 404);
        Audit::log('toolkit.downloaded', $request->user(), ['code' => $item->code, 'type' => $item->type]);

        return response()->download($item->filePath(), $item->downloadName());
    }

    public function submit(Request $request, ToolkitItem $item, MailStatus $mail)
    {
        abort_unless($item->is_active && $item->acceptsSubmissions(), 404);
        $allowed = implode(',', self::SUBMISSION_EXTENSIONS);
        $request->validate([
            'file' => "required|file|extensions:$allowed|mimes:$allowed|max:20480",
            'reference_note' => 'nullable|string|max:255',
        ], [], ['file' => __('ui.toolkit.submit_form.file'), 'reference_note' => __('ui.toolkit.submit_form.note')]);

        $upload = $request->file('file');
        $original = Str::limit(basename(str_replace('\\', '/', $upload->getClientOriginalName())), 200, '');
        $stored = now()->format('Ymd-His').'-'.(Str::slug($item->code) ?: 'form').'-'.Str::lower(Str::random(6))
            .'.'.strtolower($upload->getClientOriginalExtension());
        File::ensureDirectoryExists(ToolkitSubmission::storageDir());
        $upload->move(ToolkitSubmission::storageDir(), $stored);

        $submission = ToolkitSubmission::create([
            'toolkit_item_id' => $item->id,
            'user_id' => $request->user()->id,
            'reference_note' => filled($request->input('reference_note')) ? trim($request->input('reference_note')) : null,
            'file_path' => $stored,
            'original_filename' => $original,
            'submitted_at' => now(),
        ]);

        $to = setting('company.contact_email');
        $mailError = null;
        if ($to && $mail->canSend()) {
            try {
                Mail::to($to)->send(new ToolkitSubmissionMail($submission));
                $submission->forceFill(['emailed_to' => $to])->save();
            } catch (Throwable $e) {
                report($e);
                $mailError = $e->getMessage();
                Audit::log('toolkit.mail_failed', $request->user(), ['code' => $item->code, 'submission_id' => $submission->id, 'error' => mb_substr($mailError, 0, 300)]);
            }
        }

        Audit::log('toolkit.submitted', $request->user(), [
            'code' => $item->code, 'submission_id' => $submission->id, 'note' => $submission->reference_note,
            'emailed_to' => $submission->emailed_to,
        ]);

        $redirect = redirect()->route('toolkit.index')->withFragment('submissions');
        if ($submission->emailed_to) {
            return $redirect->with('status', __('ui.toolkit.submitted_emailed', ['email' => $to]));
        }

        // Not sent by the server: offer the person's own mail program, with the details filled in.
        return $redirect->with('status', __('ui.toolkit.submitted_saved'))
            ->with('toolkit_mailto', $to ? (new ToolkitSubmissionMail($submission))->mailtoHref() : null)
            ->with('toolkit_mail_error', $mailError);
    }

    public function submissionDownload(Request $request, ToolkitSubmission $submission)
    {
        $user = $request->user();
        abort_unless($submission->user_id === $user->id || $user->isStaffViewer(), 403);
        abort_unless(is_file($submission->path()), 404);
        Audit::log('toolkit.submission_downloaded', $submission->user_id, ['code' => $submission->item?->code, 'submission_id' => $submission->id]);

        return response()->download($submission->path(), $submission->original_filename);
    }
}
