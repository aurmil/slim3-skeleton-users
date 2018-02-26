<?php
declare(strict_types = 1);

namespace App\Middlewares;

use App\Models\User;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Interfaces\RouterInterface;

class AllowOnlyGuests
{
    /**
     * @var User
     */
    protected $user;

    /**
     * @var RouterInterface
     */
    protected $router;

    public function __construct(User $user, RouterInterface $router)
    {
        $this->user = $user;
        $this->router = $router;
    }

    public function __invoke(
        RequestInterface $request,
        ResponseInterface $response,
        callable $next
    ): ResponseInterface {
        if ($this->user->isLogged()) {
            return $response->withRedirect(
                $this->router->pathFor('user-account')
            );
        }

        return $next($request, $response);
    }
}
