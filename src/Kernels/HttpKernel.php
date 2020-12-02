<?php

declare(strict_types=1);

/*
 * This file is part of Biurad opensource projects.
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

namespace Biurad\Framework\Kernels;

use Biurad\Framework\AbstractKernel;
use Biurad\Http\Response;
use GuzzleHttp\Exception\BadResponseException;
use Laminas\HttpHandlerRunner\Emitter\SapiStreamEmitter;
use Nette\Utils\Helpers;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Throwable;

class HttpKernel extends AbstractKernel
{
    /**
     * {@inheritdoc}
     */
    public function serve(ServerRequestInterface $request, bool $catch = true)
    {
        $response = parent::serve($request, $catch);

        // Send response to  the browser...
        if ($response instanceof ResponseInterface) {
            (new SapiStreamEmitter())->emit($response);
        }

        return $response;
    }

    /**
     * {@inheritdoc}
     */
    public function handleRequest(ServerRequestInterface $request)
    {
        foreach ($this->dispatchers as $dispatcher) {
            if ($dispatcher->canServe()) {
                return $this->container->callMethod([$dispatcher, 'serve'], [$this, $request]);
            }
        }

        throw new RuntimeException('Unable to locate active dispatcher.');
    }

    /**
     * {@inheritdoc}
     */
    protected function handleThrowable(Throwable $e, ServerRequestInterface $request): ResponseInterface
    {
        if (null === $this->container->getParameter('env.APP_ERROR_PAGE')) {
            throw $e;
        }

        /** @var Response $response */
        $response = $this->container->get(ResponseFactoryInterface::class)->createResponse();

        // ensure that we actually have an error response
        if ($e instanceof BadResponseException) {
            // keep the HTTP status code and headers
            $response = $e->getResponse();
            $request  = $e->getRequest();
        } else {
            $response = $response->withStatus(500);
        }

        if (null !== $errorPage = $this->container->getParameter('env.APP_ERROR_PAGE')) {
            $response->getBody()->write(
                Helpers::capture(
                    function () use ($request, $e, $errorPage): void {
                        require \sprintf('%s/%s', $this->getBase(), $errorPage);
                    }
                )
            );
        }

        return $response;
    }
}
