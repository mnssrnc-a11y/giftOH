<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Fund;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;
use app\Services\FirebaseService;
class FundController extends Controller
{
    protected $firebaseService;

    public function storeFund(Request $request)
    {
        // Validate form data
        $validated = $request->validate([
            'org_name' => 'required|string|max:255',
            'contact_person' => 'required|string|max:255',
            'contact_email' => 'required|email|max:255',
            'phone' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'org_type' => 'required|string|max:255',
            'other_org_type' => 'nullable|string|max:255',
            'tax_id' => 'required|string|max:255',
            'org_website' => 'nullable|url|max:255',
            'annual_report' => 'nullable|file|mimes:pdf|max:2048', // Max 2MB
            'mission' => 'required|string',
            'category' => 'required|string',
            'financial_report' => 'nullable|file|mimes:pdf|max:2048',
            'doc_image' => 'required|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'id_image' => 'required|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'bank_statement' => 'required|file|mimes:jpg,jpeg,png,pdf|max:2048',
        ]);

        // Determine the role
        $role = ($request->has('role') && $request->role == 'individual') ? 'individual' : 'organization';

        // Create fund
        $fund = Fund::create([
            'user_id' => Auth::id(),
            'org_name' => $validated['org_name'],
            'contact_person' => $validated['contact_person'],
            'contact_email' => $validated['contact_email'],
            'contact_phone' => $validated['phone'],
            'address' => $validated['address'],
            'org_type' => $validated['org_type'],
            'other_org_type' => $validated['other_org_type'],
            'tax_id' => $validated['tax_id'],
            'org_website' => $validated['org_website'],
            'mission' => $validated['mission'],
            'category' => $validated['category'],
            'role' => $role,

            'doc_image' => $request->file('doc_image')->store('documents', 'public'),
            'id_image' => $request->file('id_image')->store('valid_ids', 'public'),
            'bank_statement' => $request->file('bank_statement')->store('bank_statements', 'public'),
        ]);

        // Upload files
        if ($request->hasFile('annual_report') && $request->file('annual_report')->isValid()) {
            $annualReportPath = $request->file('annual_report')->store('annual_reports', 'public');
            $fund->annual_report = $annualReportPath;
        }

        if ($request->hasFile('financial_report') && $request->file('financial_report')->isValid()) {
            $financialReportPath = $request->file('financial_report')->store('financial_reports', 'public');
            $fund->financial_report = $financialReportPath;
        }

        $fund->save();

        return redirect()->route('fund')
            ->with('success', 'Fund created successfully!');
    }
}
