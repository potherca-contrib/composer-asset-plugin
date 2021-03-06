<?php

/*
 * This file is part of the Fxp Composer Asset Plugin package.
 *
 * (c) François Pluchino <francois.pluchino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Fxp\Composer\AssetPlugin\Tests\Fixtures\Repository\Vcs;

use Composer\Config;
use Composer\IO\IOInterface;
use Composer\Repository\Vcs\VcsDriverInterface;

/**
 * Mock vcs driver.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 */
class MockVcsDriver implements VcsDriverInterface
{
    /**
     * @var bool
     */
    public static $supported = true;

    /**
     * {@inheritdoc}
     */
    public function initialize()
    {
        // no action
    }

    /**
     * {@inheritdoc}
     */
    public function getComposerInformation($identifier)
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getRootIdentifier()
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getBranches()
    {
        return array();
    }

    /**
     * {@inheritdoc}
     */
    public function getTags()
    {
        return array();
    }

    /**
     * {@inheritdoc}
     */
    public function getDist($identifier)
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getSource($identifier)
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getUrl()
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function hasComposerFile($identifier)
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function cleanup()
    {
        // no action
    }

    /**
     * {@inheritdoc}
     */
    public static function supports(IOInterface $io, Config $config, $url, $deep = false)
    {
        return static::$supported;
    }
}
