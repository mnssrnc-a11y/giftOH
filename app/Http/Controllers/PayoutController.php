<?php

namespace App\Http\Controllers;

use App\Mail\TransactionVerificationCode;
use App\Services\MailSettingsService;
use App\Services\Payouts\PayoutException;
use App\Services\Payouts\PayoutService;
use App\Services\FundingService;
use App\Services\VerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Automatic transfer of approved funds: the super admin starts it, confirms with a code emailed
 * to them, and the payout provider reports the result to the webhook.
 */
class PayoutController extends Controller
{
    private const SESSION_KEY = 'pending_payout';

    private const CODES = 'transaction_verification_codes';

    public function __construct(
        private PayoutService $payouts,
        private FundingService $funding,
        private VerificationService $codes
    ) {
    }

    /** Step 1: check the transfer can be made, then email the super admin a code. */
    public function start(string $id, MailSettingsService $mail)
    {
        $request = $this->funding->getRequestById($id);
        abort_if($request === null, 404);

        $check = $this->payouts->readiness($request);
        if (! $check['ready']) {
            return $this->back($id)->with('alert_error', $check['problems'][0]);
        }
        if (! $mail->canSend()) {
            return $this->back($id)->with('alert_error', 'Email is not set up, so the transfer code cannot be sent. Save the Brevo key in System settings first.');
        }

        $user = Auth::user();
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        try {
            Mail::to($user->email)->send(new TransactionVerificationCode($code, $user->fname ?: 'there'));
        } catch (\Throwable $exception) {
            report($exception);

            return $this->back($id)->with('alert_error', 'The transfer code could not be emailed. Try again in a few minutes.');
        }
        $this->codes->store($user->email, $code, self::CODES);
        session([self::SESSION_KEY => $id]);

        return $this->back($id)->with('status', "We emailed a 6-digit code to {$user->email}. Enter it to send ₱" . number_format($check['amount'], 2) . '.');
    }

    /** Step 2: the code is right, so the money is sent. */
    public function confirm(Request $request, string $id)
    {
        if ($request->boolean('cancel')) {
            session()->forget(self::SESSION_KEY);

            return $this->back($id)->with('status', 'Transfer cancelled. Nothing was sent.');
        }

        $validated = $request->validate(['code' => 'required|digits:6']);
        if (session(self::SESSION_KEY) !== $id) {
            return $this->back($id)->with('alert_error', 'Start the transfer again: the code was for another request or has expired.');
        }

        $user = Auth::user();
        $result = $this->codes->verify($user->email, $validated['code'], self::CODES);
        if (! $result['success']) {
            return $this->back($id)->with('alert_error', $result['error']);
        }
        session()->forget(self::SESSION_KEY);

        try {
            $payout = $this->payouts->send($id, Auth::id());
        } catch (PayoutException $exception) {
            report($exception);

            return $this->back($id)->with('alert_error', 'The transfer was not sent: ' . $exception->getMessage());
        }

        return $this->back($id)->with('status', '₱' . number_format($payout['amount'], 2) . ' sent. The release is recorded automatically when the bank or e-wallet confirms it.');
    }

    /** The payout provider's result (no sign-in: checked with the webhook token). */
    public function webhook(Request $request)
    {
        $token = (string) config('payouts.xendit.webhook_token');
        if ($token === '' || ! hash_equals($token, (string) $request->header('x-callback-token'))) {
            Log::warning('Payout webhook with a wrong or missing token was refused.', ['ip' => $request->ip()]);

            return response()->json(['message' => 'Invalid token'], 401);
        }

        $handled = $this->payouts->handleWebhook($request->all());

        return response()->json(['received' => true, 'handled' => $handled]);
    }

    private function back(string $id)
    {
        return redirect()->to(route('superadmin.fund-request.show', $id) . '#transfer');
    }
}
