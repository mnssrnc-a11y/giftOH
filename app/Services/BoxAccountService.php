<?php

namespace App\Services;

use App\Repositories\FirebaseDeviceAccountRepository;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Kreait\Firebase\Exception\Auth\EmailNotFound;
use Kreait\Firebase\Exception\Auth\UserNotFound;

/**
 * Sign-ins of the smart donation boxes. Each box signs in to Firebase with its own email and
 * password, and the database rules let it write only its own totals (which can never go down).
 * Without this, anyone with the public Firebase settings could change the totals the release
 * line relies on.
 */
class BoxAccountService
{
    public const EMAIL_DOMAIN = 'boxes.giftofhope.org';

    public function __construct(
        private FirebaseService $firebase,
        private FirebaseDeviceAccountRepository $accounts
    ) {
    }

    /** @return array<string, string> box id => uid of its sign-in */
    public function accounts(): array
    {
        return $this->accounts->byBox();
    }

    public static function emailFor(string $boxId): string
    {
        return 'box-' . Str::lower($boxId) . '@' . self::EMAIL_DOMAIN;
    }

    /**
     * Create the box's sign-in, or give it a new password. The password is returned once, to be
     * put in the box; it is not stored anywhere in the app.
     *
     * @return array{box: string, email: string, password: string, uid: string}
     */
    public function issue(string $boxId): array
    {
        if (! preg_match('/^[A-Za-z0-9_-]{1,64}$/', $boxId)) {
            throw new InvalidArgumentException('A box id uses letters, numbers, - and _ only.');
        }

        $auth = $this->firebase->getAuth();
        $email = self::emailFor($boxId);
        $password = Str::password(24, symbols: false);

        try {
            $uid = $auth->getUserByEmail($email)->uid;
            $auth->updateUser($uid, ['password' => $password, 'disabled' => false]);
        } catch (UserNotFound|EmailNotFound) {
            $uid = $auth->createUser([
                'email' => $email,
                'emailVerified' => true,
                'password' => $password,
                'displayName' => "Donation box {$boxId}",
            ])->uid;
        }
        $this->accounts->link($uid, $boxId);

        return ['box' => $boxId, 'email' => $email, 'password' => $password, 'uid' => $uid];
    }
}
