<?php

/*
 * This file is part of the Claroline Connect package.
 *
 * (c) Claroline Consortium <consortium@claroline.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Claroline\CoreBundle\Manager;

use Claroline\CoreBundle\Library\Composer\FileIO;
use Claroline\CoreBundle\Library\Maintenance\MaintenanceHandler;
use Claroline\CoreBundle\Command\PlatformUpdateCommand;
use Composer\Composer;
use JMS\DiExtraBundle\Annotation as DI;
use Composer\Package\CompletePackageInterface;
use Composer\IO\NullIO;
use Composer\Config;
use Composer\Installer;
use Composer\Factory;
use Composer\Util\RemoteFilesystem;
use Composer\Repository\InstalledFilesystemRepository;
use Composer\Repository\CompositeRepository;
use Composer\Json\JsonFile;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Input\ArgvInput;

/**
 * @DI\Service("claroline.manager.dependency_manager")
 */
class DependencyManager {

    private $vendorDir;
    private $repo;
    private $installedJson;
    private $logFile;
    private $lastTagsFile;
    private $iniFileManager;
    private $composerLogFile;
    private $cacheDir;
    private $env;
    private $updater;
    private $projectComposerJson;

    const CLAROLINE_CORE_TYPE = 'claroline-core';
    const CLAROLINE_PLUGIN_TYPE = 'claroline-plugin';

    /**
     * @DI\InjectParams({
     *      "vendorDir"       = @DI\Inject("%claroline.param.vendor_directory%"),
     *      "lastTagsFile"    = @DI\Inject("%claroline.packages_last_tags_file%"),
     *      "composerLogFile" = @DI\Inject("%claroline.composer_log_file%"),
     *      "iniFileManager"  = @DI\Inject("claroline.manager.ini_file_manager"),
     *      "cacheDir"        = @DI\Inject("%claroline.cache_dir%"),
     *      "env"             = @DI\Inject("%kernel.environment%"),
     *      "updater"         = @DI\Inject("claroline.command.update_command")
     * })
     */
    public function __construct(
        $vendorDir,
        $lastTagsFile,
        IniFileManager $iniFileManager,
        $composerLogFile,
        $cacheDir,
        $env,
        PlatformUpdateCommand $updater
    )
    {
        $this->vendorDir = $vendorDir;
        $ds = DIRECTORY_SEPARATOR;
        $this->installedJson = "{$vendorDir}{$ds}composer{$ds}installed.json";
        $this->logFile = "{$lastTagsFile}{$ds}..{$ds}composer.log";
        $this->projectComposerJson = "{$vendorDir}{$ds}..{$ds}composer.json";
        $installedFile = new JsonFile($this->installedJson);
        $this->repo = new InstalledFilesystemRepository($installedFile);
        $this->lastTagsFile = $lastTagsFile;
        $this->iniFileManager = $iniFileManager;
        $this->composerLogFile = $composerLogFile;
        $this->cacheDir = $cacheDir;
        $this->env = $env;
        $this->updater = $updater;
    }

    /**
     * Returns every packages installed by composer.
     *
     * @return CompletePackageInterface[]
     */
    public function getAllInstalled()
    {
        return $this->repo->getPackages();
    }

    /**
     * Returns installed packages by composer by type.
     *
     * @param $type
     *
     * @return CompletePackageInterface[]
     */
    public function getInstalledByType($type)
    {
        $packages = [];

        foreach ($this->getAllInstalled() as $package) {

            if ($package->getType() === $type) {
                $packages[] = $package;
            }
        }

        return $packages;
    }

    /**
     * Returns installed packages by composer by name.
     *
     * @param $name
     *
     * @return CompletePackageInterface
     */
    public function getInstalledByName($name)
    {
        foreach ($this->getAllInstalled() as $package) {
            if ($package->getName() === $name || $package->getPrettyName() === $name) {
               return $package;
            }
        }

        return null;
    }

    /**
     * Finds package by dist reference.
     *
     * @param string $distReference
     *
     * @return CompletePackageInterface|null
     */
    public function getByDistReference($distReference)
    {
        foreach ($this->getAllInstalled() as $package) {

            if ($package->getDistReference() === $distReference) {
                return $package;
            }
        }

        return null;
    }

    /**
     * Return each installed claroline plugins or core packages.
     *
     * @return array
     */
    public function getAllClaroPackages()
    {
        return array_merge(
            $this->getInstalledByType(self::CLAROLINE_CORE_TYPE),
            $this->getInstalledByType(self::CLAROLINE_PLUGIN_TYPE)
        );
    }

    /**
     * Returns a package tag list.
     *
     * @param CompletePackageInterface $package
     *
     * @return array
     */
    public function getPackageTags(CompletePackageInterface $package)
    {
        $repos = Factory::createDefaultRepositories();
        $compositeRepo = new CompositeRepository($repos);
        $pkgs = $compositeRepo->findPackages($package->getPrettyName());
        $tags = array();

        foreach ($pkgs as $pkg) {
            $tags[] = $pkg->getPrettyVersion();
        }

        return $tags;
    }

    /**
     * Returns the last tag of a package.
     *
     * @param CompletePackageInterface $package
     *
     * @return string
     */
    public function getLastTag(CompletePackageInterface $package)
    {
        $tags = $this->getPackageTags($package);
        $lastTag = 0;
        $toReturn = 0;

        foreach ($tags as $tag => $commit) {

            //remove the "v" from the version tags. It's hard to compare otherwise.
            $tag = str_replace('v', '', $tag);

            if (version_compare($lastTag, $tag, '<')) {
                $lastTag = $tag;
            }
        }

        return $lastTag;
    }

    /**
     * Update the cache file containing the list of last available tags.
     *
     * @return array
     */
    public function updateAllPackages()
    {
        $packages = $this->getAllClaroPackages();

        foreach ($packages as $package) {
            $this->updatePackage($package);
        }

        return $tagList;
    }

    /**
     * Update a single package in the cache file.
     *
     * @param CompletePackageInterface $package
     */
    public function updatePackage(CompletePackageInterface $package)
    {
        $tag = $this->getLastTag($package);
        $this->iniFileManager->updateKey($package->getPrettyName(), $tag, $this->lastTagsFile);

        return $tag;
    }

    /**
     * Returns if the tag cache exists.
     *
     * @return bool
     */
    public function cacheExists()
    {
        return file_exists($this->lastTagsFile);
    }

    /**
     * Returns the tag cache.
     */
    private function removeCache()
    {
        if ($this->cacheExists()) {
            unlink($this->lastTagsFile);
        }
    }

    /**
     * Returns a list of upgradeable packages.
     *
     * @return array
     */
    public function getUpgradeablePackages()
    {
        //if the cache does not exists, wait for a refresh
        if (!$this->cacheExists()) {
            return array();
        }

        $tags = parse_ini_file($this->lastTagsFile);
        $packages = $this->getAllClaroPackages();
        $toUpgrade = [];

        foreach ($packages as $package) {
            foreach ($tags as $prettyName => $tag) {
                if ($package->getPrettyName() === $prettyName) {
                    $toUpgrade[] = array('tag' => $tag, 'package' => $package);
                }
            }
        }

        return $toUpgrade;
    }

    /**
     * Upgrade claroline packages.
     */
    public function upgrade()
    {
        MaintenanceHandler::enableMaintenance();

        //get the list of upgradable packages from the cache
        $pkgList = $this->getUpgradableFromCache();
        $this->updateRequirements('>=', $pkgList);

        $ds = DIRECTORY_SEPARATOR;
        $factory = new Factory();
        $io = new FileIO($this->composerLogFile);
        putenv("COMPOSER_HOME={$this->vendorDir}{$ds}composer");
        $composer = $factory->createComposer($io, "{$this->vendorDir}{$ds}..{$ds}composer.json", false);
        $install = Installer::create($io, $composer);
        $dryRun = $this->env === 'dev' ? true: false;
        $preferSource = $this->env === 'dev' ? true: false;
        $preferDist = $this->env === 'dev' ? false: true;
        $devMode = $this->env === 'dev' ? true: false;

        $install->setDryRun($dryRun)
            ->setVerbose(true)
            ->setPreferSource($preferSource)
            ->setPreferDist($preferDist)
            ->setDevMode($devMode)
            ->setRunScripts(true)
            ->setOptimizeAutoloader(true)
            ->setUpdate(true);

        $install->run();

        //this should disable the maintenance mode aswell
        $this->updater->run(new ArgvInput(array()), new StreamOutput(fopen($this->composerLogFile, 'a')));

        //remove the old cache file
        $this->iniFileManager->remove($this->lastTagsFile);
    }

    /**
     * Update the main composer.json
     *
     * @param string $operator a comparison operator ('>=', '=', '>')
     * @param array $toUpdate an array of prettyName of packages
     */
    public function updateRequirements($operator, array $toUpdate)
    {
        $data = json_decode(file_get_contents($this->projectComposerJson));
        $new = clone $data;

        foreach ($data->require as $prettyName => $version) {
            foreach ($this->getAllInstalled() as $package) {
                foreach ($toUpdate as $ppn) {
                    if ($package->getPrettyName() === $prettyName && $prettyName == $ppn) {
                        $new->require->$prettyName = $operator . $package->getPrettyVersion();
                    }
                }
            }
        };

        file_put_contents($this->projectComposerJson . '.old', json_encode($data, JSON_PRETTY_PRINT));
        file_put_contents($this->projectComposerJson, json_encode($new, JSON_PRETTY_PRINT));
    }

    /**
     * Get the a list of upgradable packages from the cache.
     *
     * @return array
     */
    public function getUpgradableFromCache()
    {
        $datas = $this->iniFileManager->getValues($this->lastTagsFile);
        $installed = $this->getAllClaroPackages();
        $toUpdate = [];

        foreach ($datas as $prettyName => $version) {
            foreach ($installed as $pkg) {
                if ($pkg->getPrettyName() === $prettyName && version_compare($pkg->getPrettyVersion(), $version, '<')) {
                    $toUpdate[] = $pkg->getPrettyName();
                }
            }
        }

        return $toUpdate;
    }
}