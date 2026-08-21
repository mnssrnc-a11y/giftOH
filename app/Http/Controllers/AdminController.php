<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Mail\PasswordResetCode;
use Illuminate\Support\Facades\Mail;
use App\Mail\LoginAuthCode;
use App\Mail\VerifyEmail;
use App\Rules\RealEmail;
use app\Services\FirebaseService;

class AdminController extends Controller
{
    protected $firebaseService;

    public function admin()
    {
        if (Auth::user()->role != 'admin') {
            return redirect()->route('login');
        }

        // Fetch pending requests sorted by AI score (highest/most-critical first)
        $pendingRequests = \App\Models\Funding::pending()
            ->with(['user', 'category'])
            ->orderByDesc('ai_score')
            ->get();

        return view('adminPage.admin', compact('pendingRequests'));
    }

    public function adminApproval()
    {
        if (Auth::user()->role != 'admin') {
            return redirect()->route('login');
        }

        // Fetch pending requests sorted by AI score (highest/most-critical first)
        $pendingRequests = \App\Models\Funding::pending()
            ->with(['user', 'category'])
            ->orderByDesc('ai_score')
            ->get();

        return view('adminPage.admin-approval-verify', compact('pendingRequests'));
    }

    public function adminFundApprove()
    {
        $_REQUEST['FunndingAppeal'] = 'approve';
        $fundId = $_REQUEST['funding_id'];
        $fundingRequest = \App\Models\Funding::find($fundId);
        $fundingRequest->status = 'approved';
        $fundingRequest->save();
        return redirect()->route('admin');
    }

    public function adminFundReject()
    {
        $_REQUEST['FunndingAppeal'] = 'reject';
        $fundId = $_REQUEST['funding_id'];
        $fundingRequest = \App\Models\Funding::find($fundId);
        $fundingRequest->status = 'rejected';
        $fundingRequest->save();
        return redirect()->route('admin');
    }
}
