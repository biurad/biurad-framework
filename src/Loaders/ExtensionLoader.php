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

namespace Biurad\Framework\Loaders;

use Composer\Autoload\ClassLoader;
use Composer\InstalledVersions;
use Nette\DI\CompilerExtension;
use Nette\InvalidArgumentException;
use Nette\NotSupportedException;

class ExtensionLoader
{
    /**
     * Returns the file path for a given compiler extension resource.
     *
     * A Resource can be a file or a directory.
     *
     * The resource name must follow the following pattern:
     *
     *     "@CompilerExtension/path/to/a/file.something"
     *
     * where CompilerExtension is the name of the nette-di extension
     * and the remaining part is the relative path in the class.
     *
     * @param CompilerExtension $extension
     * @param string            $name
     * @param bool              $throw
     *
     * @throws InvalidArgumentException if the file cannot be found or the name is not valid
     * @throws RuntimeException         if the name contains invalid/unsafe characters
     * @throws NotSupportedException    if the $name doesn't match in $extension
     *
     * @return string The absolute path of the resource
     */
    public static function getLocation(CompilerExtension $extension, string $name, bool $throw = true)
    {
        [$bundleName, $path] = self::getExtensionPath($name, $throw);

        if (false === \strpos(\get_class($extension), $bundleName)) {
            throw new NotSupportedException(\sprintf('Resource path is not supported for %s', $bundleName));
        }

        /** @var \RecursiveIteratorIterator|\SplFileInfo[] $iterator */
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($bundlePath = self::findComposerDirectory($extension)),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if (\strlen($file->getPathname()) === \strlen($bundlePath . $path) && \file_exists($bundlePath . $path)) {
                return \strtr($file->getPathname(), ['\\' => '/']);
            }
        }

        throw new InvalidArgumentException(\sprintf('Unable to find file "%s".', $name));
    }

    /**
     * @param CompilerExtension $extension
     *
     * @return string
     */
    private static function findComposerDirectory(CompilerExtension $extension): string
    {
        $path      = \dirname((new \ReflectionClass(ClassLoader::class))->getFileName());
        $directory = \dirname((new \ReflectionObject($extension))->getFileName());

        foreach (self::getPackagist($path . '/installed.json') as $package) {
            $packagePath = \str_replace(['\\', '/'], \DIRECTORY_SEPARATOR, \dirname($path, 1) . '/' . $package['name']);

            if (!str_starts_with($directory, $packagePath)) {
                continue;
            }

            $pathPrefix = \current($package['autoload']['psr-4']
                ?? $package['autoload']['psr-0']
                ?? $package['autoload']['classmap']);

            return \sprintf('%s/%s/', $packagePath, \rtrim($pathPrefix, '/'));
        }

        return \dirname($directory, 1) . '/';
    }

    private static function getPackagist(string $path)
    {
        return \class_exists(InstalledVersions::class)
            ? InstalledVersions::getRawData()
            : \json_decode(\file_get_contents($path . '/installed.json'), true);
    }

    private static function getExtensionPath(string $name, bool $throw)
    {
        if ('@' !== $name[0] && $throw) {
            throw new InvalidArgumentException(\sprintf('A resource name must start with @ ("%s" given).', $name));
        }

        if (false !== \strpos($name, '..')) {
            throw new \RuntimeException(\sprintf('File name "%s" contains invalid characters (..).', $name));
        }

        $path = '';

        if (false !== \strpos($bundleName = \substr($name, 1), '/')) {
            [$bundleName, $path] = \explode('/', $bundleName, 2);
        }

        return [$bundleName, $path];
    }
}
