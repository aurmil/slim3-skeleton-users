<?php

$onlyGuestsMiddleware = new App\Middlewares\AllowOnlyGuests(
    $container->user,
    $container->router
);

$onlyLoggedUsersMiddleware = new App\Middlewares\AllowOnlyLoggedUsers(
    $container->user,
    $container->router
);

$app->get('/', 'App\Controllers\FrontController:home')
    ->setName('home');

$app->group('/user', function () use (
    $container,
    $onlyGuestsMiddleware,
    $onlyLoggedUsersMiddleware
) {
    $controller = 'App\Controllers\UserController';

    $this->get('/signup', $controller . ':signupForm')
        ->setName('user-signup-form')
        ->add($onlyGuestsMiddleware);

    $this->post('/signup', $controller . ':signup')
        ->setName('user-signup')
        ->add($onlyGuestsMiddleware);

    if ($container->user->shouldSendActivationEmail()) {
        $this->get('/send-activation', $controller . ':sendActivationForm')
            ->setName('user-send-activation-form')
            ->add($onlyGuestsMiddleware);

        $this->post('/send-activation', $controller . ':sendActivation')
            ->setName('user-send-activation')
            ->add($onlyGuestsMiddleware);

        $this->get('/activate/{id}/{code}', $controller . ':activate')
            ->setName('user-activate')
            ->add($onlyGuestsMiddleware);
    }

    $this->get('/login', $controller . ':loginForm')
        ->setName('user-login-form')
        ->add($onlyGuestsMiddleware);

    $this->post('/login', $controller . ':login')
        ->setName('user-login')
        ->add($onlyGuestsMiddleware);

    $this->get('/logout', $controller . ':logout')
        ->setName('user-logout')
        ->add($onlyLoggedUsersMiddleware);

    $this->get('/forgot-password', $controller . ':forgotPasswordForm')
        ->setName('user-forgot-password-form')
        ->add($onlyGuestsMiddleware);

    $this->post('/forgot-password', $controller . ':forgotPassword')
        ->setName('user-forgot-password')
        ->add($onlyGuestsMiddleware);

    $this->get('/reset-password/{id}/{code}', $controller . ':resetPasswordForm')
        ->setName('user-reset-password-form')
        ->add($onlyGuestsMiddleware);

    $this->post('/reset-password', $controller . ':resetPassword')
        ->setName('user-reset-password')
        ->add($onlyGuestsMiddleware);

    $this->get('/account', $controller . ':account')
        ->setName('user-account')
        ->add($onlyLoggedUsersMiddleware);

    $this->get('/change-password', $controller . ':changePasswordForm')
        ->setName('user-change-password-form')
        ->add($onlyLoggedUsersMiddleware);

    $this->post('/change-password', $controller . ':changePassword')
        ->setName('user-change-password')
        ->add($onlyLoggedUsersMiddleware);
});

// Page not found handler
$container['notFoundHandler'] = function ($container) {
    return function (
        Psr\Http\Message\RequestInterface $request,
        Psr\Http\Message\ResponseInterface $response,
        Slim\Exception\NotFoundException $exception = null
    ) use ($container) {
        return $container->view->render(
            $response->withStatus(404),
            'errors/not-found.twig'
        );
    };
};
