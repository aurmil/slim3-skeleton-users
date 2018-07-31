<?php

namespace App\Controllers;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Respect\Validation\Validator as v;
use Slim\Http\Request;
use Slim\Http\Response;

use App\Exceptions\EmailNotSentException;
use App\Exceptions\InvalidPasswordException;
use App\Exceptions\SecurityThrottleException;
use App\Exceptions\TokenNotFoundException;
use App\Exceptions\UserActivatedException;
use App\Exceptions\UserFoundException;
use App\Exceptions\UserNotActivatedException;
use App\Exceptions\UserNotFoundException;

class UserController extends Controller
{
    public function signupForm(RequestInterface $request, ResponseInterface $response)
    {
        return $this->render($response, 'user/signup.twig');
    }

    public function signup(Request $request, Response $response)
    {
        $data = $request->getParsedBody();
        $success = false;
        $message = 'An error occurred, please try again.';
        $redirectUrl = $this->router->pathFor('user-signup-form');

        if (false !== $request->getAttribute('csrf_status')) {
            $valid = v::arrayType()
                ->key('email', v::stringType()->notEmpty()->email())
                ->key('password', v::stringType()->notEmpty())
                ->keyValue('password_confirmation', 'equals', 'password')
                ->validate($data);

            $message = 'Invalid data, please correct them and try again.';

            if ($valid) {
                try {
                    $sendActivationEmail = $this->user->shouldSendActivationEmail();
                    $activateAccountUrl = '';

                    if ($sendActivationEmail) {
                        $activateAccountUrl = $request->getUri()->getBaseUrl()
                            . $this->router->relativePathFor('user-activate', [
                                'id' => '{id}',
                                'code' => '{code}'
                            ]);
                    }

                    $this->user->register(
                        $data['email'],
                        $data['password'],
                        ['user'],
                        $activateAccountUrl
                    );

                    $success = true;
                    $message = 'Your account has successfully been created. '
                            . 'Account activation email sent to ' . $data['email'];

                    if (!$sendActivationEmail) {
                        $this->flash->addMessage('success', 'Your account has successfully been created.');
                        $redirectUrl = $this->router->pathFor('user-account');
                    }
                } catch (UserFoundException $e) {
                    $message = $e->getMessage();
                } catch (EmailNotSentException $e) {
                    $message = 'Your account has successfully been created but activation email could not be sent.'
                        . '<br>Please try to get it again <a href="'
                        . $this->router->pathFor('user-send-activation-form')
                        . '">here</a>.';
                } catch (\Exception $e) {
                    $message = 'An error occurred, please try again later.';
                    $this->logger->error($e);
                }
            }
        }

        if ($message) {
            $this->flash->addMessage('signup_form_success', $success);
            $this->flash->addMessage('signup_form_message', $message);
        }

        if (!$success) {
            $this->flash->addMessage('signup_form_data', $data);
        }

        return $response->withRedirect($redirectUrl);
    }

    public function loginForm(RequestInterface $request, ResponseInterface $response)
    {
        return $this->render($response, 'user/login.twig');
    }

    public function login(Request $request, Response $response)
    {
        $data = $request->getParsedBody();
        $success = false;
        $message = 'An error occurred, please try again.';
        $redirectUrl = $this->router->pathFor('user-login-form');

        if (false !== $request->getAttribute('csrf_status')) {
            $valid = v::arrayType()
                ->key('email', v::stringType()->notEmpty()->email())
                ->key('password', v::stringType()->notEmpty())
                ->validate($data);

            $message = 'Invalid data, please correct them and try again.';

            if ($valid) {
                try {
                    $rememberMe = ($this->user->isRememberMeAllowed()
                        && isset($data['remember_me'])
                        && is_string($data['remember_me'])
                        && 'on' === $data['remember_me']);

                    $this->user->login(
                        $data['email'],
                        $data['password'],
                        $rememberMe
                    );

                    $success = true;
                    $this->flash->addMessage('success', 'Successfully logged in.');
                    $redirectUrl = $this->router->pathFor('user-account');
                } catch (UserNotFoundException $e) {
                    $message = $e->getMessage();
                } catch (UserNotActivatedException $e) {
                    $message = $e->getMessage();
                } catch (SecurityThrottleException $e) {
                    $message = $e->getMessage();
                } catch (\Exception $e) {
                    $message = 'An error occurred, please try again later.';
                    $this->logger->error($e);
                }
            }
        }

        if ($message) {
            $this->flash->addMessage('login_form_success', $success);
            $this->flash->addMessage('login_form_message', $message);
        }

        if (!$success) {
            $this->flash->addMessage('login_form_data', $data);
        }

        return $response->withRedirect($redirectUrl);
    }

    public function logout(RequestInterface $request, Response $response)
    {
        $this->user->logout($this->user->getCurrentUser());
        $this->flash->addMessage('success', 'Successfully logged out.');

        return $response->withRedirect($this->router->pathFor('home'));
    }

    public function forgotPasswordForm(RequestInterface $request, ResponseInterface $response)
    {
        return $this->render($response, 'user/forgot-password.twig');
    }

    public function forgotPassword(Request $request, Response $response)
    {
        $data = $request->getParsedBody();
        $success = false;
        $message = 'An error occurred, please try again.';
        $redirectUrl = $this->router->pathFor('user-forgot-password-form');

        if (false !== $request->getAttribute('csrf_status')) {
            $valid = v::arrayType()
                ->key('email', v::stringType()->notEmpty()->email())
                ->validate($data);

            $message = 'Invalid data, please correct them and try again.';

            if ($valid) {
                try {
                    $user = $this->user->findByLogin($data['email']);

                    // do not give information on which account exists or not
                    // so no Exception thrown if no user found

                    if ($user) {
                        $reminder = $this->user->createForgotPasswordToken($user);
                        $resetPasswordUrl = $request->getUri()->getBaseUrl()
                            . $this->router->relativePathFor('user-reset-password-form', [
                                'id' => $user->getUserId(),
                                'code' => $reminder->code
                            ]);
                        $this->user->sendForgotPasswordToken(
                            $user,
                            $resetPasswordUrl
                        );
                    }

                    $success = true;
                    $message = 'If this account exists, a recovery email was sent to ' . $data['email'];
                } catch (\Exception $e) {
                    $message = 'An error occurred, please try again later.';
                    $this->logger->error($e);
                }
            }
        }

        if ($message) {
            $this->flash->addMessage('forgot_password_form_success', $success);
            $this->flash->addMessage('forgot_password_form_message', $message);
        }

        if (!$success) {
            $this->flash->addMessage('forgot_password_form_data', $data);
        }

        return $response->withRedirect($redirectUrl);
    }

    public function resetPasswordForm(RequestInterface $request, Response $response, $data)
    {
        $success = false;
        $message = '';
        $redirectUrl = $this->router->pathFor('user-forgot-password-form');

        // Check data format here and not force it through a regex in the route
        // as the route solution would lead the user to a 404 error page
        // in case of wrong parameters, which is not user-friendly / comprehensive
        $valid = v::arrayType()
            ->key('id', v::stringType()->notEmpty()->digit()->noWhitespace())
            ->key('code', v::stringType()->notEmpty()->alnum()->noWhitespace())
            ->validate($data);

        if ($valid) {
            try {
                $user = $this->user->findById((int) $data['id']);

                if ($user && $this->user->getForgotPasswordToken(
                    $user,
                    $data['code']
                )) {
                    return $this->render($response, 'user/reset-password.twig', $data);
                }
            } catch (\Exception $e) {
                $message = 'An error occurred, please try again later.';
                $this->logger->error($e);
            }
        }

        if (!$message) {
            $message = 'Invalid or expired code, please check again the email'
                    . ' you received or ask for a new one below.';
        }

        $this->flash->addMessage('forgot_password_form_success', $success);
        $this->flash->addMessage('forgot_password_form_message', $message);

        return $response->withRedirect($redirectUrl);
    }

    public function resetPassword(Request $request, Response $response)
    {
        $data = $request->getParsedBody();
        $success = false;
        $message = '';
        $redirectUrl = $this->router->pathFor('user-forgot-password-form');

        // we just want to know to which form we can redirect the user
        // but we don't want to make DB requests before checking CSRF status
        // so we just check data format, not if token exists or is valid

        $valid = v::arrayType()
            ->key('id', v::stringType()->notEmpty()->digit()->noWhitespace())
            ->key('code', v::stringType()->notEmpty()->alnum()->noWhitespace())
            ->validate($data);
        $goToForgotForm = !$valid;

        if ($valid) {
            $redirectUrl = $this->router->pathFor('user-reset-password-form', [
                'id' => $data['id'],
                'code' => $data['code'],
            ]);

            $message = 'An error occurred, please try again.';

            if (false !== $request->getAttribute('csrf_status')) {
                $valid = v::arrayType()
                    ->key('password', v::stringType()->notEmpty())
                    ->keyValue('password_confirmation', 'equals', 'password')
                    ->validate($data);

                $message = 'Invalid data, please correct them and try again.';

                if ($valid) {
                    try {
                        $user = $this->user->findById((int) $data['id']);

                        if (!$user) {
                            throw new UserNotFoundException('User not found.');
                        }

                        $this->user->resetPassword(
                            $user,
                            $data['code'],
                            $data['password']
                        );

                        $success = true;
                        $this->flash->addMessage('success', 'Your password has successfully been updated.');
                        $redirectUrl = $this->router->pathFor('user-login-form');
                    } catch (UserNotFoundException $e) {
                        $goToForgotForm = true;
                    } catch (TokenNotFoundException $e) {
                        $goToForgotForm = true;
                    } catch (InvalidPasswordException $e) {
                        $message = $e->getMessage();
                    } catch (\Exception $e) {
                        $message = 'An error occurred, please try again later.';
                        $this->logger->error($e);
                    }
                }
            }
        }

        if ($goToForgotForm) {
            $this->flash->addMessage('forgot_password_form_success', false);
            $this->flash->addMessage(
                'forgot_password_form_message',
                'Invalid or expired code, please check again the email you received or ask for a new one below.'
            );

            $redirectUrl = $this->router->pathFor('user-forgot-password-form');
        }

        if ($message) {
            $this->flash->addMessage('reset_password_form_success', $success);
            $this->flash->addMessage('reset_password_form_message', $message);
        }

//        if (!$success) {
//            $this->flash->addMessage('reset_password_form_data', $data);
//        }

        return $response->withRedirect($redirectUrl);
    }

    public function account(RequestInterface $request, ResponseInterface $response)
    {
        return $this->render($response, 'user/account.twig');
    }

    public function changePasswordForm(RequestInterface $request, ResponseInterface $response)
    {
        return $this->render($response, 'user/change-password.twig');
    }

    public function changePassword(Request $request, Response $response)
    {
        $data = $request->getParsedBody();
        $success = false;
        $message = 'An error occurred, please try again.';
        $redirectUrl = $this->router->pathFor('user-change-password-form');

        if (false !== $request->getAttribute('csrf_status')) {
            $valid = v::arrayType()
                ->key('current_password', v::stringType()->notEmpty())
                ->key('password', v::stringType()->notEmpty())
                ->keyValue('password_confirmation', 'equals', 'password')
                ->validate($data);

            $message = 'Invalid data, please correct them and try again.';

            if ($valid) {
                try {
                    $this->user->changePassword(
                        $this->user->getCurrentUser(),
                        $data['current_password'],
                        $data['password']
                    );

                    $success = true;
                    $this->flash->addMessage('success', 'Your password has successfully been updated.');
                    $redirectUrl = $this->router->pathFor('user-account');
                } catch (InvalidPasswordException $e) {
                    $message = $e->getMessage();
                } catch (\Exception $e) {
                    $message = 'An error occurred, please try again later.';
                    $this->logger->error($e);
                }
            }
        }

        if ($message) {
            $this->flash->addMessage('change_password_form_success', $success);
            $this->flash->addMessage('change_password_form_message', $message);
        }

//        if (!$success) {
//            $this->flash->addMessage('change_password_form_data', $data);
//        }

        return $response->withRedirect($redirectUrl);
    }

    public function sendActivationForm(RequestInterface $request, ResponseInterface $response)
    {
        return $this->render($response, 'user/send-activation.twig');
    }

    public function sendActivation(Request $request, Response $response)
    {
        $data = $request->getParsedBody();
        $success = false;
        $message = 'An error occurred, please try again.';
        $redirectUrl = $this->router->pathFor('user-send-activation-form');

        if (false !== $request->getAttribute('csrf_status')) {
            $valid = v::arrayType()
                ->key('email', v::stringType()->notEmpty()->email())
                ->validate($data);

            $message = 'Invalid data, please correct them and try again.';

            if ($valid) {
                try {
                    $user = $this->user->findByLogin($data['email']);

                    // do not give information on which account exists or not
                    // so no Exception thrown if no user found

                    if ($user) {
                        $activation = $this->user->createAccountActivationToken($user);
                        $activateAccountUrl = $request->getUri()->getBaseUrl()
                            . $this->router->relativePathFor('user-activate', [
                                'id' => $user->getUserId(),
                                'code' => $activation->code
                            ]);
                        $this->user->sendAccountActivationToken(
                            $user,
                            $activateAccountUrl
                        );
                    }

                    $success = true;
                    $message = 'If this account exists, an activation email was sent to ' . $data['email'];
                } catch (UserActivatedException $e) {
                    $this->flash->addMessage('success', $e->getMessage());
                    $redirectUrl = $this->router->pathFor('user-login');
                } catch (\Exception $e) {
                    $message = 'An error occurred, please try again later.';
                    $this->logger->error($e);
                }
            }
        }

        if ($message) {
            $this->flash->addMessage('send_activation_form_success', $success);
            $this->flash->addMessage('send_activation_form_message', $message);
        }

        if (!$success) {
            $this->flash->addMessage('send_activation_form_data', $data);
        }

        return $response->withRedirect($redirectUrl);
    }

    public function activate(RequestInterface $request, Response $response, $data)
    {
        $success = false;
        $message = '';
        $redirectUrl = $this->router->pathFor('user-send-activation-form');

        // Check data format here and not force it through a regex in the route
        // as the route solution would lead the user to a 404 error page
        // in case of wrong parameters, which is not user-friendly / comprehensive
        $valid = v::arrayType()
            ->key('id', v::stringType()->notEmpty()->digit()->noWhitespace())
            ->key('code', v::stringType()->notEmpty()->alnum()->noWhitespace())
            ->validate($data);

        if ($valid) {
            try {
                $user = $this->user->findById((int) $data['id']);

                if ($user) {
                    $this->user->activate($user, $data['code']);
                    $this->flash->addMessage('success', 'Your account has successfully been activated.');
                    $redirectUrl = $this->router->pathFor('user-login');
                }
            } catch (UserActivatedException $e) {
                $this->flash->addMessage('success', $e->getMessage());
                $redirectUrl = $this->router->pathFor('user-login');
            } catch (TokenNotFoundException $e) {
                $message = $e->getMessage();
            } catch (\Exception $e) {
                $message = 'An error occurred, please try again later.';
                $this->logger->error($e);
            }
        }

        if ((!$valid || !$user) && !$message) {
            $message = 'Account activation token is invalid or expired.';
        }

        if ($message) {
            $this->flash->addMessage('send_activation_form_success', $success);
            $this->flash->addMessage('send_activation_form_message', $message);
        }

        if (!$success) {
            $this->flash->addMessage('send_activation_form_data', $data);
        }

        return $response->withRedirect($redirectUrl);
    }
}
