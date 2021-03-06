<?php

/*
 * This file is part of the Fxp Composer Asset Plugin package.
 *
 * (c) François Pluchino <francois.pluchino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Fxp\Composer\AssetPlugin\Repository;

use Composer\Config;
use Composer\DependencyResolver\Pool;
use Composer\Downloader\TransportException;
use Composer\EventDispatcher\EventDispatcher;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Composer\Repository\ComposerRepository;
use Composer\Repository\RepositoryManager;
use Fxp\Composer\AssetPlugin\Assets;
use Fxp\Composer\AssetPlugin\Type\AssetTypeInterface;

/**
 * Abstract assets repository.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 */
abstract class AbstractAssetsRepository extends ComposerRepository
{
    /**
     * @var AssetTypeInterface
     */
    protected $assetType;

    /**
     * @var RepositoryManager
     */
    protected $rm;

    /**
     * @var AssetVcsRepository[]
     */
    protected $repos;

    /**
     * @var bool
     */
    protected $searchable;

    /**
     * @var bool
     */
    protected $fallbackProviders;

    /**
     * Constructor.
     *
     * @param array           $repoConfig
     * @param IOInterface     $io
     * @param Config          $config
     * @param EventDispatcher $eventDispatcher
     */
    public function __construct(array $repoConfig, IOInterface $io, Config $config, EventDispatcher $eventDispatcher = null)
    {
        $repoConfig = array_merge($repoConfig, array(
            'url' => $this->getUrl(),
        ));

        parent::__construct($repoConfig, $io, $config, $eventDispatcher);

        $this->assetType = Assets::createType($this->getType());
        $this->lazyProvidersUrl = $this->getPackageUrl();
        $this->providersUrl = $this->lazyProvidersUrl;
        $this->searchUrl = $this->getSearchUrl();
        $this->hasProviders = true;
        $this->rm = $repoConfig['repository-manager'];
        $this->repos = array();
        $this->searchable = (bool) $this->getOption($repoConfig['asset-options'], 'searchable', true);
        $this->fallbackProviders = false;
    }

    /**
     * {@inheritDoc}
     */
    public function search($query, $mode = 0)
    {
        if (!$this->searchable) {
            return array();
        }

        $url = str_replace('%query%', urlencode($this->cleanPackageName($query)), $this->searchUrl);
        $hostname = (string) parse_url($url, PHP_URL_HOST) ?: $url;
        $json = (string) $this->rfs->getContents($hostname, $url, false);
        $data = JsonFile::parseJson($json, $url);
        $results = array();

        /* @var array $item */
        foreach ($data as $item) {
            $results[] = $this->createSearchItem($item);
        }

        return $results;
    }

    /**
     * {@inheritDoc}
     */
    public function whatProvides(Pool $pool, $name)
    {
        $assetPrefix = $this->assetType->getComposerVendorName() . '/';

        if (false === strpos($name, $assetPrefix)) {
            return array();
        }

        if (isset($this->providers[$name])) {
            return $this->providers[$name];
        }

        try {
            $repoName = $this->convertAliasName($name);
            $packageName = $this->cleanPackageName($repoName);
            $packageUrl = str_replace('%package%', $packageName, $this->lazyProvidersUrl);
            $cacheName = $packageName . '-' . sha1($packageName) . '-package.json';
            $data = $this->fetchFile($packageUrl, $cacheName);
            $repo = $this->createVcsRepositoryConfig($data, $packageName);

            Util::addRepository($this->rm, $this->repos, $repoName, $repo, $pool);

            $this->providers[$name] = array();

        } catch (TransportException $ex) {
            $this->fallbackWathProvides($pool, $name, $ex);
        }

        return $this->providers[$name];
    }

    /**
     * {@inheritDoc}
     */
    public function getMinimalPackages()
    {
        return array();
    }

    /**
     * {@inheritDoc}
     */
    protected function loadRootServerFile()
    {
        return array(
            'providers' => array(),
        );
    }

    /**
     * Cleans the package name, removing the Composer prefix if present.
     *
     * @param string $name
     *
     * @return string
     */
    protected function cleanPackageName($name)
    {
        $prefix = $this->assetType->getComposerVendorName() . '/';

        if (0 === strpos($name, $prefix)) {
            $name = substr($name, strlen($prefix));
        }

        return $name;
    }

    /**
     * Converts the alias of asset package name by the real asset package name.
     *
     * @param string $name
     *
     * @return string
     */
    protected function convertAliasName($name)
    {
        if (false !== strrpos($name, ']')) {
            $name = substr($name, 0, strrpos($name, '['));
        }

        return $name;
    }

    /**
     * Gets the option.
     *
     * @param array  $options The options
     * @param string $key     The key
     * @param mixed  $default The default value
     *
     * @return mixed The option value or default value if key is not found
     */
    protected function getOption(array $options, $key, $default = null)
    {
        if (array_key_exists($key, $options)) {
            return $options[$key];
        }

        return $default;
    }

    /**
     * Creates the search result item.
     *
     * @param array $item.
     *
     * @return array An array('name' => '...', 'description' => '...')
     */
    protected function createSearchItem(array $item)
    {
        return array(
            'name'        => $this->assetType->getComposerVendorName() . '/' . $item['name'],
            'description' => null,
        );
    }

    /**
     * Searchs if the registry has a package with the same name exists with a
     * different camelcase.
     *
     * @param Pool               $pool
     * @param string             $name
     * @param TransportException $ex
     */
    protected function fallbackWathProvides(Pool $pool, $name, TransportException $ex)
    {
        $providers = array();

        if (404 === $ex->getCode() && !$this->fallbackProviders) {
            $this->fallbackProviders = true;
            $repoName = $this->convertAliasName($name);
            $results = $this->search($repoName);

            foreach ($results as $item) {
                if ($name === strtolower($item['name'])) {
                    $providers = $this->whatProvides($pool, $item['name']);
                    break;
                }
            }
        }

        $this->fallbackProviders = false;
        $this->providers[$name] = $providers;
    }

    /**
     * Gets the asset type name.
     *
     * @return string
     */
    abstract protected function getType();

    /**
     * Gets the URL of repository.
     *
     * @return string
     */
    abstract protected function getUrl();

    /**
     * Gets the URL for get the package information.
     *
     * @return string
     */
    abstract protected function getPackageUrl();

    /**
     * Gets the URL for get the search result.
     *
     * @return string
     */
    abstract protected function getSearchUrl();

    /**
     * Creates a config of vcs repository.
     *
     * @param array  $data         The repository config
     * @param string $registryName The package name in asset registry
     *
     * @return array An array('type' => '...', 'url' => '...')
     */
    abstract protected function createVcsRepositoryConfig(array $data, $registryName = null);
}
