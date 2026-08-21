<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use app\Services\FirebaseService;
class AuditController extends Controller
{
    protected $firebaseService;

    public function index()
    {
        // Fetch all audit logs from the database
        $auditLogs = \App\Models\AuditLog::all();

        // Return the view with the audit logs
        return view('adminPage.audit-logs', compact('auditLogs'));
    }

    public function show($id)
    {
        // Fetch a specific audit log by ID
        $auditLog = \App\Models\AuditLog::findOrFail($id);

        // Return the view with the specific audit log
        return view('adminPage.audit-log-detail', compact('auditLog'));
    }

    public function emailReport()
    {
        // Fetch all audit logs from the database
        $auditLogs = \App\Models\AuditLog::all();

        // Here you would typically format the audit logs into a report (e.g., PDF, CSV, etc.)
        // For simplicity, let's assume we are sending a simple email with the logs

        \Mail::to(auth()->user($role= 'admin')->email)->send(new \App\Mail\AuditReport($auditLogs));
        return redirect()->back()->with('status', 'Audit report emailed successfully.');
    }

    public function downloadReport()
    {
        // Fetch all audit logs from the database
        $auditLogs = \App\Models\AuditLog::all();

        // Here you would typically format the audit logs into a downloadable report (e.g., PDF, CSV, etc.)
        // For simplicity, let's assume we are generating a CSV file

        $filename = 'audit_report_' . now()->format('Ymd_His') . '.csv';
        $handle = fopen($filename, 'w+');
        fputcsv($handle, ['ID', 'User ID', 'Action', 'Created At']);

        foreach ($auditLogs as $log) {
            fputcsv($handle, [$log->id, $log->user_id, $log->action, $log->created_at]);
        }

        fclose($handle);

        return response()->download($filename)->deleteFileAfterSend(true);
    }

}