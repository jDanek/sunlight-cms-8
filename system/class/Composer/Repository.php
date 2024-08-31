<?php

namespace Sunlight\Composer;

use Sunlight\Util\Filesystem;
use Sunlight\Util\Json;

/**
 * Composer repository
 *
 * Provides access to a composer.json file and its installed packages.
 */
class Repository
{
    private string $composerJsonPath;
    private ?string $directory = null;
    private ?\stdClass $package = null;
    private ?string $vendorPath = null;
    /** @var \stdClass[]|null name-indexed */
    private ?array $installedPackages = null;
    private ?array $classMap = null;

    function __construct(string $composerJsonPath)
    {
        $this->composerJsonPath = $composerJsonPath;
    }

    function getComposerJsonPath(): string
    {
        return $this->composerJsonPath;
    }

    /**
     * Get data from composer.json
     */
    function getDefinition(): \stdClass
    {
        if ($this->package === null) {
            $this->package = Json::decode(file_get_contents($this->composerJsonPath), 0, false);
        }

        return $this->package;
    }

    /**
     * Get directory where composer.json is located
     */
    function getDirectory(): string
    {
        if ($this->directory === null) {
            $this->directory = dirname($this->composerJsonPath);
        }

        return $this->directory;
    }

    function getVendorPath(): string
    {
        if ($this->vendorPath === null) {
            $package = $this->getDefinition();

            if (isset($package->config->{'vendor-dir'})) {
                $vendorDir = $package->config->{'vendor-dir'};

                if (Filesystem::isAbsolutePath($vendorDir)) {
                    throw new \UnexpectedValueException('Absolute vendor dir is not supported');
                }
            } else {
                $vendorDir = 'vendor';
            }

            $this->vendorPath = Filesystem::normalizeWithBasePath($this->getDirectory(), $vendorDir);
        }

        return $this->vendorPath;
    }

    function getPackagePath(\stdClass $package): string
    {
        return $this->getVendorPath() . '/' . $package->name;
    }

    function getPackageComposerJsonPath(\stdClass $package): string
    {
        return $this->getPackagePath($package) . '/composer.json';
    }

    function getInstalledJsonPath(): string
    {
        return $this->getVendorPath() . '/composer/installed.json';
    }

    /**
     * @return \stdClass[] name-indexed
     */
    function getInstalledPackages(): array
    {
        if ($this->installedPackages === null) {
            $this->installedPackages = [];

            if (is_file($installedJson = $this->getInstalledJsonPath())) {
                $packages = Json::decode(file_get_contents($installedJson), 0, false);
                $packages = $packages->packages ?? $packages; // composer 2.0 has wrapper
                
                foreach ($packages as $package) {
                    $this->installedPackages[$package->name] = $package;
                }
            }
        }

        return $this->installedPackages;
    }

    function getClassMap(): array
    {
        if ($this->classMap === null) {
            $classMapPath = $this->getVendorPath() . '/composer/autoload_classmap.php';

            if (is_file($classMapPath)) {
                $this->classMap = require $classMapPath;
            } else {
                $this->classMap = [];
            }
        }

        return $this->classMap;
    }
}
