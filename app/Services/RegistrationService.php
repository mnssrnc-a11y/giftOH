<?php

namespace App\Services;

use App\Models\FirebaseUser;
use App\Repositories\FirebaseUserRepository;

/**
 * Creates a requester account from the sign-up form. Used after the emailed code is confirmed,
 * and directly when email sending is not set up yet (then email verification starts off, so the
 * person can sign in without a code until the foundation configures email).
 */
class RegistrationService
{
    public function __construct(
        private FirebaseUserRepository $users,
        private AuditLogger $audit,
        private NotificationService $notifications
    ) {
    }

    /**
     * @param  array  $data  the pending registration: fname, lname, mname, email, password (hashed), phone, address, gender, date_of_birth, profile_picture
     */
    public function createAccount(array $data, bool $emailConfirmed): FirebaseUser
    {
        $record = $this->users->create([
            'fname' => $data['fname'],
            'lname' => $data['lname'],
            'mname' => $data['mname'] ?? null,
            'email' => $data['email'],
            'password' => $data['password'],
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'gender' => $data['gender'] ?? null,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'profile_picture' => $data['profile_picture'] ?? null,
            'role' => 'normaluser',
            'email_confirmed' => $emailConfirmed,
            // Sign-in codes need working email; they can be turned on in Settings once it is set up.
            'email_notifications' => $emailConfirmed,
        ]);

        if (! $emailConfirmed) {
            $this->audit->record('account', "Account created without an emailed code (email sending not set up): {$data['email']}", (string) $record['id']);
        }
        $this->notifications->notifyRole('super_admin', 'new_account', 'New requester account',
            trim("{$data['fname']} {$data['lname']}") . " ({$data['email']}) signed up" . ($emailConfirmed ? '.' : ' without an emailed code, because email is not set up yet.'),
            route('superadmin', [], false) . '#accounts');

        return new FirebaseUser($record);
    }
}
