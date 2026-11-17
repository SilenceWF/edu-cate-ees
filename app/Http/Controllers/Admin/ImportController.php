<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Audit;
use App\Services\RosterImporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;

class ImportController extends Controller
{
    public function form()
    {
        return view('admin.import', ['plan' => null]);
    }

    /** Dry run: show what the file would change before anything is written. */
    public function preview(Request $request, RosterImporter $importer)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls|max:10240']);

        File::ensureDirectoryExists(storage_path('app/imports'));
        $name = Str::uuid().'.xlsx';
        $request->file('file')->move(storage_path('app/imports'), $name);

        try {
            $rows = $importer->readSpreadsheet(storage_path('app/imports/'.$name));
        } catch (Throwable $e) {
            return back()->withErrors(['file' => __('admin.import.unreadable')]);
        }
        if (! $rows) {
            return back()->withErrors(['file' => __('admin.import.empty')]);
        }

        $request->session()->put('import', ['file' => $name, 'original' => $request->file('file')->getClientOriginalName()]);

        return view('admin.import', ['plan' => $importer->plan($rows), 'original' => $request->file('file')->getClientOriginalName()]);
    }

    public function apply(Request $request, RosterImporter $importer)
    {
        $state = $request->session()->pull('import');
        $path = $state ? storage_path('app/imports/'.basename($state['file'])) : null;
        if (! $path || ! is_file($path)) {
            return redirect()->route('admin.import')->withErrors(['file' => __('admin.import.expired')]);
        }

        $plan = $importer->apply($importer->readSpreadsheet($path));
        File::delete($path);
        Audit::log('roster.imported', null, ['file' => $state['original']] + $plan['summary']);

        return redirect()->route('admin.dashboard')->with('status', __('admin.import.done', ['create' => $plan['summary']['create'], 'update' => $plan['summary']['update']]));
    }
}
