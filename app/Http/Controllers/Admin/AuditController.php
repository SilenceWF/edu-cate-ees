<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.audit', [
            'logs' => AuditLog::with(['actor', 'target'])
                ->when($request->filled('action'), fn ($q) => $q->where('action', $request->input('action')))
                ->orderByDesc('id')->paginate(50)->withQueryString(),
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
        ]);
    }
}
