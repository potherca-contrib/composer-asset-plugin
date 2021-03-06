<?php

/*
 * This file is part of the Fxp Composer Asset Plugin package.
 *
 * (c) François Pluchino <francois.pluchino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Fxp\Composer\AssetPlugin\Installer;

use Composer\Util\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\Glob;

/**
 * Manager of ignore patterns for bower.
 *
 * @author Martin Hasoň <martin.hason@gmail.com>
 * @author François Pluchino <francois.pluchino@gmail.com>
 */
class BowerIgnoreManager
{
    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var Finder
     */
    private $finder;

    /**
     * Constructor.
     *
     * @param Filesystem|null $filesystem The filesystem
     */
    public function __construct(Filesystem $filesystem = null)
    {
        $this->filesystem = $filesystem ?: new Filesystem();
        $this->finder = Finder::create()->ignoreVCS(true)->ignoreDotFiles(false);
        $this->finder->path('/^\//');
    }

    /**
     * Adds an ignore pattern.
     *
     * @param string $pattern The pattern
     */
    public function addPattern($pattern)
    {
        if (0 === strpos($pattern, '!')) {
            $this->finder->notPath(Glob::toRegex(substr($pattern, 1), true, false));
        } else {
            $this->finder->path(Glob::toRegex($pattern, true, false));
        }
    }

    /**
     * Deletes all files and directories that matches patterns in specified directory.
     *
     * @param string $dir The path to the directory
     */
    public function deleteInDir($dir)
    {
        $paths = iterator_to_array($this->finder->in($dir));

        /* @var \SplFileInfo $path */
        foreach ($paths as $path) {
            $this->filesystem->remove($path);
        }
    }
}
