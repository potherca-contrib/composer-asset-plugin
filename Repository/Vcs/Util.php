<?php

/*
 * This file is part of the Fxp Composer Asset Plugin package.
 *
 * (c) François Pluchino <francois.pluchino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Fxp\Composer\AssetPlugin\Repository\Vcs;

use Composer\Cache;
use Composer\Json\JsonFile;

/**
 * Helper for VCS driver.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 */
class Util
{
    /**
     * Check if the identifier is an SHA.
     *
     * @param string $identifier The identifier
     *
     * @return bool
     */
    public static function isSha($identifier)
    {
        return (bool) preg_match('{[a-f0-9]{40}}i', $identifier);
    }

    /**
     * @param array  $cacheCode  The cache code
     * @param Cache  $cache      The cache filesystem
     * @param string $type       The asset type
     * @param string $identifier The identifier
     *
     * @return array|null
     */
    public static function readCache(array $cacheCode, Cache $cache, $type, $identifier)
    {
        if (array_key_exists($identifier, $cacheCode)) {
            return $cacheCode[$identifier];
        }

        if (self::isSha($identifier)) {
            $res = $cache->read($type . '-' . $identifier);

            if ($res) {
                return JsonFile::parseJson($res);
            }
        }

        return null;
    }

    /**
     * @param Cache  $cache      The cache
     * @param string $type       The asset type
     * @param string $identifier The identifier
     * @param array  $composer   The data composer
     */
    public static function writeCache(Cache $cache, $type, $identifier, array $composer)
    {
        if (self::isSha($identifier)) {
            $cache->write($type . '-' . $identifier, json_encode($composer));
        }
    }
}
