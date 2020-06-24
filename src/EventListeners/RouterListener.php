<?php

declare(strict_types=1);

/*
 * This file is part of BiuradPHP opensource projects.
 *
 * PHP version 7.2 and above required
 *
 * @author    Divine Niiquaye Ibok <divineibok@gmail.com>
 * @copyright 2019 Biurad Group (https://biurad.com/)
 * @license   https://opensource.org/licenses/BSD-3-Clause License
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace BiuradPHP\MVC\EventListeners;

use BiuradPHP\Events\Interfaces\EventSubscriberInterface;
use BiuradPHP\Http\Exceptions\ClientExceptions\MethodNotAllowedException;
use BiuradPHP\Http\Exceptions\ClientExceptions\NotFoundException;
use BiuradPHP\HttpCache\HttpCache;
use BiuradPHP\MVC\Events\ExceptionEvent;
use BiuradPHP\MVC\Events\RequestEvent;
use BiuradPHP\MVC\Exceptions\NotConfiguredException;
use BiuradPHP\MVC\HomeController;
use BiuradPHP\MVC\KernelEvents;
use DomainException;
use Flight\Routing\Interfaces\RouteCollectorInterface;
use Nette\DI\Container;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Initializes the context from the request and sets request attributes based on a matching route.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 *
 * @final
 */
class RouterListener implements EventSubscriberInterface
{
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function onKernelRequest(RequestEvent $event, RouteCollectorInterface $router): void
    {
        // $_SERVER variables are not fully supported on CLI
        if ($event->getKernel()->runningInConsole()) {
            return;
        }

        $request = $event->getRequest();

        // matching a request is more powerful than matching a URL path + context, so try that first
        try {
            if ($this->container->has('http.cache')) {
                $response = $this->container->get(HttpCache::class)->handle($request);
                $event->setResponse($response);

                return;
            }

            $event->setResponse($router->handle($request));
        } catch (DomainException $e) {
            if (0 === \strpos($e->getMessage(), 'Unfotunately current uri ')) {
                $exception = new MethodNotAllowedException();
                $exception->withMessage($e->getMessage());

                throw $exception;
            }

            $baseUri = \rtrim(\dirname($request->getServerParams()['SCRIPT_NAME']), '/');
            $message = \sprintf(
                'No route found for "%s %s". The route is wrongly configured',
                $request->getMethod(),
                $request->getUri()->getPath()
            );

            // Replace exception to return homepage
            if (\rtrim($request->getUri()->getPath(), '/') === \ltrim($baseUri, '\\')) {
                throw new NotConfiguredException(
                    \sprintf('No route detected for homepage ["%s"]', $request->getUri()->getPath()),
                    404
                );
            }

            if ($referer = $request->getHeaderLine('Referer')) {
                $message .= \sprintf(' (from "%s")', $referer);
            }

            $exception = new NotFoundException();
            $exception->withMessage($message);
            $exception->withPreviousException($e);

            throw $exception;
        }
    }

    /**
     * If Homepage URL is not set, let make the page be visible.
     *
     * @param ExceptionEvent $event
     */
    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if ($exception instanceof NotConfiguredException) {
            $response = $this->createWelcomeResponse($event->getRequest());

            $event->setResponse($response->withStatus($exception->getCode()));
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST   => [['onKernelRequest', 32]],
            KernelEvents::EXCEPTION => ['onKernelException', -64],
        ];
    }

    private function createWelcomeResponse(ServerRequestInterface $request): ResponseInterface
    {
        // Incase we have nette/di installed...
        if ($this->container instanceof Container) {
            $homeController = $this->container->make(HomeController::class, [true]);

            return $homeController->handle($request);
        }

        $homeController            = new HomeController(true);
        $homeController->container = $this->container;

        return $homeController->handle($request);
    }
}
