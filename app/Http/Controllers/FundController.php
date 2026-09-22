<?php

namespace App\Http\Controllers;

use App\Services\FundingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FundController extends Controller
{
    public function __construct(
        private FundingService $fundingService
    ) {
    }

    public function storeFund(Request $request)
    {
        if ($denialReason = $this->fundingService->requestDenialReason(Auth::id())) {
            return back()->withErrors(['fund_request' => $denialReason])->withInput();
        }

        $validated = $request->validate([
            'org_name' => 'required|string|max:255',
            'contact_person' => 'required|string|max:255',
            'contact_email' => 'required|email|max:255',
            'phone' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'tax_id' => 'required|string|max:255',
            'mission' => 'required|string',
            'category' => 'required|string',
            'amount_requested' => 'required|numeric|min:1',
            'barangay_clr' => 'nullable|file|mimes:pdf,png,jpeg,jpg|max:5000',
            'financial_rprt' => 'nullable|file|mimes:jpg,png,jpeg,pdf|max:5000',
            'doc_image' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5000',
            'id_image' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5000',
        ]);

        $this->fundingService->createRequest([
            'user_id' => Auth::id(),
            'category_id' => 1,
            'status_id' => 1,
            'category_name' => $validated['category'],
            'amount_requested' => $validated['amount_requested'],
            'status_name' => 'pending',
            'org_name' => $validated['org_name'],
            'contact_person' => $validated['contact_person'],
            'contact_email' => $validated['contact_email'],
            'contact_phone' => $validated['phone'],
            'address' => $validated['address'],
            'tax_id' => $validated['tax_id'],
            'mission' => $validated['mission'],
            'category' => $validated['category'],
            'barangay_clr' => $request->hasFile('barangay_clr') && $request->file('barangay_clr')->isValid()
                ? $request->file('barangay_clr')->store('barangay_clearances', 'public')
                : null,
            'doc_image' => $request->file('doc_image')->store('doc_images', 'public'),
            'id_image' => $request->file('id_image')->store('valid_ids', 'public'),
            'financial_rprt' => $request->hasFile('financial_rprt') && $request->file('financial_rprt')->isValid()
                ? $request->file('financial_rprt')->store('financial_reports', 'public')
                : null,
        ]);

        return redirect()->route('user')->with('success', 'Fund request created successfully and is now pending admin approval.');
    }
}
