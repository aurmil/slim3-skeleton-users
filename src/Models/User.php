<?php
declare(strict_types = 1);

namespace App\Models;

use Cartalyst\Sentinel\Sentinel;
use Cartalyst\Sentinel\Users\UserInterface;
use Cartalyst\Sentinel\Reminders\EloquentReminder;
use Cartalyst\Sentinel\Activations\EloquentActivation;

use App\Exceptions\EmailNotSentException;
use App\Exceptions\InvalidPasswordException;
use App\Exceptions\SecurityThrottleException;
use App\Exceptions\TokenNotFoundException;
use App\Exceptions\UserActivatedException;
use App\Exceptions\UserFoundException;
use App\Exceptions\UserNotActivatedException;
use App\Exceptions\UserNotFoundException;

class User
{
    /**
     * @var Sentinel
     */
    protected $sentinel;

    /**
     * @var UserMailer
     */
    protected $userMailer;

    /**
     * @var bool
     */
    protected $allowRememberMe = false;

    /**
     * @var bool
     */
    protected $sendActivationEmail = false;

    public function __construct(Sentinel $sentinel, UserMailer $userMailer)
    {
        $this->sentinel = $sentinel;
        $this->userMailer = $userMailer;
    }

    public function allowRememberMe(bool $allow = true)
    {
        $this->allowRememberMe = $allow;
    }

    public function isRememberMeAllowed(): bool
    {
        return $this->allowRememberMe;
    }

    public function setSendActivationEmail(bool $send = true)
    {
        $this->sendActivationEmail = $send;
    }

    public function shouldSendActivationEmail(): bool
    {
        return $this->sendActivationEmail;
    }

    public function isLogged(): bool
    {
        return !!$this->sentinel->check();
    }

    /**
     * @return UserInterface|false
     */
    public function getCurrentUser()
    {
        $user = $this->sentinel->getUser();

        // I want to return false, not null
        return $user ?: false;
    }

    /**
     * @return UserInterface|false
     */
    public function findById(int $userId)
    {
        $user = $this->sentinel->findUserById($userId);

        // I want to return false, not null
        return $user ?: false;
    }

    /**
     * @return UserInterface|false
     */
    public function findByLogin(string $login)
    {
        $user = $this->sentinel->findUserByCredentials(['login' => $login]);

        // I want to return false, not null
        return $user ?: false;
    }

    /**
     * @return UserInterface|false
     */
    public function findByCredentials(string $login, string $password)
    {
        $user = $this->findByLogin($login);

        if ($user && $this->sentinel->validateCredentials($user, [
            'password' => $password,
        ])) {
            return $user;
        }

        return false;
    }

    /**
     * @param string[] $roles
     *
     * @throws \InvalidArgumentException
     * @throws \Exception
     * @throws UserFoundException
     */
    public function register(
        string $login,
        string $password,
        array $roles = [],
        string $activateAccountUrl = '',
        bool $autologin = true
    ): UserInterface {
        $sendActivationEmail = $this->shouldSendActivationEmail();

        if ($sendActivationEmail && !$activateAccountUrl) {
            throw new \InvalidArgumentException('Activation URL is required');
        }

        if ($this->findByLogin($login)) {
            throw new UserFoundException('This login is already used.');
        }

        $user = $this->sentinel->register([
            'login' => $login,
            'password' => $password,
        ], !$sendActivationEmail);

        if (!$user) {
            throw new \Exception();
        }

        foreach ($roles as $slug) {
            $role = $this->sentinel->findRoleBySlug($slug);

            if ($role) {
                $role->users()->attach($user);
            }
        }

        if ($sendActivationEmail) {
            $activation = $this->createAccountActivationToken($user);
            $this->sendAccountActivationToken(
                $user,
                str_replace(
                    ['{id}', '{code}'],
                    [$user->getUserId(), $activation->code],
                    $activateAccountUrl
                )
            );
        } elseif ($autologin) {
            try {
                $this->login($login, $password);
            } catch (\Exception $e) {
                // account was successfully created,
                // no need to return false if a login error occurs
            }
        }

        return $user;
    }

    /**
     * @throws UserNotFoundException
     * @throws UserNotActivatedException
     * @throws SecurityThrottleException
     */
    public function login(
        string $login,
        string $password,
        bool $rememberMe = false
    ): UserInterface {
        try {
            $user = $this->sentinel->authenticate(
                ['login' => $login, 'password' => $password],
                ($rememberMe && $this->isRememberMeAllowed()),
                true
            );

            if (!$user) {
                throw new UserNotFoundException('Login or password is invalid.');
            }

            // we don't want the controller to know anything about Sentinel
        } catch (\Cartalyst\Sentinel\Checkpoints\NotActivatedException $e) {
            throw new UserNotActivatedException($e->getMessage());
        } catch (\Cartalyst\Sentinel\Checkpoints\ThrottlingException $e) {
            throw new SecurityThrottleException($e->getMessage());
        }

        return $user;
    }

    public function logout(UserInterface $user)
    {
        $this->sentinel->logout($user);
    }

    /**
     * @throws \Exception
     * @throws InvalidPasswordException
     */
    public function changePassword(
        UserInterface $user,
        string $oldPassword,
        string $newPassword
    ) {
        if (!$this->sentinel->validateCredentials($user, [
            'password' => $oldPassword,
        ])) {
            throw new InvalidPasswordException('Current password is not valid.');
        }

        if ($oldPassword === $newPassword) {
            throw new InvalidPasswordException('New password can\'t be the same as the old one.');
        }

        if (!$this->sentinel->update($user, ['password' => $newPassword])) {
            throw new \Exception();
        }
    }

    /**
     * @return EloquentReminder|false
     */
    public function getForgotPasswordToken(
        UserInterface $user,
        string $code = ''
    ) {
        $reminders = $this->sentinel->getReminderRepository();
        $reminders->removeExpired();

        return $reminders->exists($user, $code ?: null);
    }

    public function createForgotPasswordToken(
        UserInterface $user
    ): EloquentReminder {
        $reminder = $this->getForgotPasswordToken($user);

        if (!$reminder) {
            $reminder = $this->sentinel->getReminderRepository()->create($user);
        }

        return $reminder;
    }

    /**
     * @throws EmailNotSentException
     */
    public function sendForgotPasswordToken(
        UserInterface $user,
        string $resetPasswordUrl
    ) {
        if (!$this->userMailer->sendResetPasswordEmail(
            $user->email,
            $resetPasswordUrl
        )) {
            throw new EmailNotSentException();
        }
    }

    /**
     * @throws \Exception
     * @throws TokenNotFoundException
     * @throws InvalidPasswordException
     */
    public function resetPassword(
        UserInterface $user,
        string $code,
        string $password
    ) {
        $reminder = $this->getForgotPasswordToken($user, $code);

        if (!$reminder) {
            throw new TokenNotFoundException('Reset password token is invalid or expired.');
        }

        if ($this->sentinel->validateCredentials($user, [
            'password' => $password,
        ])) {
            throw new InvalidPasswordException('New password can\'t be the same as the old one.');
        }

        if (!$this->sentinel->getReminderRepository()->complete(
            $user,
            $code,
            $password
        )) {
            throw new \Exception();
        }
    }

    /**
     * @return EloquentActivation|false
     */
    public function getAccountActivationToken(
        UserInterface $user,
        string $code = ''
    ) {
        $activations = $this->sentinel->getActivationRepository();
        $activations->removeExpired();

        return $activations->exists($user, $code ?: null);
    }

    /**
     * @throws UserActivatedException
     */
    public function createAccountActivationToken(
        UserInterface $user
    ): EloquentActivation {
        if ($this->sentinel->getActivationRepository()->completed($user)) {
            throw new UserActivatedException('User is already activated. Try to log in.');
        }

        $activation = $this->getAccountActivationToken($user);

        if (!$activation) {
            $activation = $this->sentinel->getActivationRepository()->create($user);
        }

        return $activation;
    }

    /**
     * @throws EmailNotSentException
     */
    public function sendAccountActivationToken(
        UserInterface $user,
        string $activateAccountUrl
    ) {
        if (!$this->userMailer->sendActivateAccountEmail(
            $user->email,
            $activateAccountUrl
        )) {
            throw new EmailNotSentException();
        }
    }

    /**
     * @throws \Exception
     * @throws UserActivatedException
     * @throws TokenNotFoundException
     */
    public function activate(UserInterface $user, string $code)
    {
        $activations = $this->sentinel->getActivationRepository();

        if ($activations->completed($user)) {
            throw new UserActivatedException('User is already activated. Try to log in.');
        }

        $activation = $this->getAccountActivationToken($user, $code);

        if (!$activation) {
            throw new TokenNotFoundException('Account activation token is invalid or expired.');
        }

        if (!$activations->complete($user, $code)) {
            throw new \Exception();
        }
    }
}
