<?php

/*
 * This file is part of the Fxp Composer Asset Plugin package.
 *
 * (c) François Pluchino <francois.pluchino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Fxp\Composer\AssetPlugin;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Repository\RepositoryInterface;
use Composer\Repository\RepositoryManager;
use Fxp\Composer\AssetPlugin\Event\VcsRepositoryEvent;
use Fxp\Composer\AssetPlugin\Installer\AssetInstaller;
use Fxp\Composer\AssetPlugin\Installer\BowerInstaller;
use Fxp\Composer\AssetPlugin\Repository\Util;

/**
 * Composer plugin.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 */
class FxpAssetPlugin implements PluginInterface, EventSubscriberInterface
{
    /**
     * @var Composer
     */
    protected $composer;

    /**
     * @var RepositoryInterface[]
     */
    protected $repos = array();

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            AssetEvents::ADD_VCS_REPOSITORIES => array(
                array('onAddVcsRepositories', 0),
            ),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $extra = $composer->getPackage()->getExtra();
        $rm = $composer->getRepositoryManager();

        $this->addRegistryRepositories($rm, $extra);
        $this->setVcsTypeRepositories($rm);

        if (isset($extra['asset-repositories']) && is_array($extra['asset-repositories'])) {
            $this->addRepositories($rm, $extra['asset-repositories']);
        }

        $this->addInstallers($composer, $io);
    }

    /**
     * Adds vcs repositories in manager from asset dependencies with url version.
     *
     * @param VcsRepositoryEvent $event
     */
    public function onAddVcsRepositories(VcsRepositoryEvent $event)
    {
        if (null !== $this->composer) {
            $rm = $this->composer->getRepositoryManager();
            $this->addRepositories($rm, $event->getRepositories());
        }
    }

    /**
     * Adds asset registry repositories.
     *
     * @param RepositoryManager $rm
     * @param array             $extra
     */
    protected function addRegistryRepositories(RepositoryManager $rm, array $extra)
    {
        $opts = array_key_exists('asset-registry-options', $extra)
            ? $extra['asset-registry-options']
            : array();

        foreach (Assets::getRegistries() as $assetType => $registryClass) {
            $config = array(
                'repository-manager' => $rm,
                'asset-options'      => $this->crateAssetOptions($opts, $assetType),
            );

            $rm->setRepositoryClass($assetType, $registryClass);
            $rm->addRepository($rm->createRepository($assetType, $config));
        }
    }

    /**
     * Sets vcs type repositories.
     *
     * @param RepositoryManager $rm
     */
    protected function setVcsTypeRepositories(RepositoryManager $rm)
    {
        foreach (Assets::getTypes() as $assetType) {
            foreach (Assets::getVcsRepositoryDrivers() as $driverType => $repositoryClass) {
                $rm->setRepositoryClass($assetType . '-' . $driverType, $repositoryClass);
            }
        }
    }

    /**
     * Adds asset vcs repositories.
     *
     * @param RepositoryManager $rm
     * @param array             $repositories
     *
     * @throws \UnexpectedValueException When config of repository is not an array
     * @throws \UnexpectedValueException When the config of repository has not a type defined
     * @throws \UnexpectedValueException When the config of repository has an invalid type
     */
    protected function addRepositories(RepositoryManager $rm, array $repositories)
    {
        foreach ($repositories as $index => $repo) {
            if (!is_array($repo)) {
                throw new \UnexpectedValueException('Repository '.$index.' ('.json_encode($repo).') should be an array, '.gettype($repo).' given');
            }
            if (!isset($repo['type'])) {
                throw new \UnexpectedValueException('Repository '.$index.' ('.json_encode($repo).') must have a type defined');
            }
            if (false === strpos($repo['type'], '-')) {
                throw new \UnexpectedValueException('Repository '.$index.' ('.json_encode($repo).') must have a type defined in this way: "%asset-type%-%type%"');
            }
            if (!isset($repo['url'])) {
                throw new \UnexpectedValueException('Repository '.$index.' ('.json_encode($repo).') must have a url defined');
            }
            $name = is_int($index) ? preg_replace('{^https?://}i', '', $repo['url']) : $index;
            Util::addRepository($rm, $this->repos, $name, $repo);
        }
    }

    /**
     * Creates the asset options.
     *
     * @param array  $extra     The composer extra section of asset options
     * @param string $assetType The asset type
     *
     * @return array The asset registry options
     */
    protected function crateAssetOptions(array $extra, $assetType)
    {
        $options = array();

        foreach ($extra as $key => $value) {
            if (0 === strpos($key, $assetType . '-')) {
                $key = substr($key, strlen($assetType) + 1);
                $options[$key] = $value;
            }
        }

        return $options;
    }

    /**
     * Adds asset installers.
     *
     * @param Composer    $composer
     * @param IOInterface $io
     */
    protected function addInstallers(Composer $composer, IOInterface $io)
    {
        $im = $composer->getInstallationManager();

        $im->addInstaller(new BowerInstaller($io, $composer, Assets::createType('bower')));
        $im->addInstaller(new AssetInstaller($io, $composer, Assets::createType('npm')));
    }
}
