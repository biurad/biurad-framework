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

namespace Biurad\Framework\DependencyInjection\Extensions;

use Biurad\Cache\AdapterFactory;
use Biurad\Cache\CacheItemPool;
use Biurad\Cache\SimpleCache;
use Biurad\Cache\TagAwareCache;
use Biurad\Framework\DependencyInjection\Extension;
use Biurad\Framework\ExtensionLoader;
use Biurad\Framework\HttpKernel;
use Biurad\Framework\Interfaces\HttpKernelInterface;
use Cache\Adapter\Doctrine\DoctrineCachePool;
use Doctrine\Common\Cache\Cache as DoctrineCache;
use Nette;
use Nette\DI\Definitions\Statement;
use Nette\Schema\Expect;

class FrameworkExtension extends Extension
{
    /**
     * {@inheritDoc}
     */
    public function getConfigSchema(): Nette\Schema\Schema
    {
        return Nette\Schema\Expect::structure([
            'content_security_policy' => Nette\Schema\Expect::bool(false),
            'error_template'          => Nette\Schema\Expect::string(),
            'dispatchers'             => Nette\Schema\Expect::arrayOf(Expect::string()->assert('class_exists')),
            'imports'                 => Nette\Schema\Expect::list(),
            'cache_driver'            => Nette\Schema\Expect::string()->default(\extension_loaded('apcu') ? 'apcu' : 'array'),
        ])->castTo('array');
    }

    /**
     * {@inheritDoc}
     */
    public function loadConfiguration(): void
    {
        $container   = $this->getContainerBuilder();

        // Cache ...
        $container->register(
            $this->prefix('cache_doctrine'),
            new Statement(
                [AdapterFactory::class, 'createHandler'],
                [$this->getFromConfig('cache_driver')]
            )
        )->setType(DoctrineCache::class);

        if (\class_exists(DoctrineCachePool::class)) {
            $container->register($this->prefix('cache_psr6'), TagAwareCache::class);
        } else {
            $container->register($this->prefix('cache_psr6'), CacheItemPool::class);
        }
        $container->register($this->prefix('cache_psr16'), SimpleCache::class);

        foreach ($this->compiler->getExtensions() as $name => $extension) {
            foreach ($this->getFromConfig('imports') as $resource) {
                try {
                    $path = ExtensionLoader::getLocation($extension, $resource);
                } catch (Nette\NotSupportedException $e) {
                    continue;
                }

                $this->compiler->loadDefinitionsFromConfig([$name => $this->loadFromFile($path)]);
            }
        }

        $framework = $container->register($this->prefix('app'), HttpKernel::class)
            ->setType(HttpKernelInterface::class);

        foreach ($this->getFromConfig('dispatchers') as $dispatcher) {
            $framework->addSetup('addDispatcher', [new Statement($dispatcher)]);
        }

        $container->addAlias('application', $this->prefix('app'));
    }
}
