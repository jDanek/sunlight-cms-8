<?php

namespace Sunlight\Composer;

class ConstraintMap
{
    /** @var array package => string[] */
    private array $constraintMap;
    /** @var array package => array(array(Repository[, package]), ...] corresponding to $constraintMap */
    private array $sourceMap;

    function __construct(Repository $repository)
    {
        $definition = $repository->getDefinition();

        if (!empty($definition->require)) {
            foreach ($repository->getDefinition()->require as $requiredPackage => $constraints) {
                $this->constraintMap[$requiredPackage][] = $constraints;
                $this->sourceMap[$requiredPackage][] = [$repository];
            }
        }

        foreach ($repository->getInstalledPackages() as $package) {
            if (empty($package->require)) {
                continue;
            }

            foreach ($package->require as $requiredPackage => $constraints) {
                $this->constraintMap[$requiredPackage][] = $constraints;
                $this->sourceMap[$requiredPackage][] = [$repository, $package];
            }
        }
    }

    /**
     * Add all constraints from another map
     * @param ConstraintMap $constraintMap
     */
    function add(self $constraintMap): void
    {
        $this->constraintMap = array_merge_recursive($this->constraintMap, $constraintMap->constraintMap);
        $this->sourceMap = array_merge_recursive($this->sourceMap, $constraintMap->sourceMap);
    }

    /**
     * See if a package is known
     */
    function has(string $packageName): bool
    {
        return isset($this->constraintMap[$packageName]);
    }

    /**
     * Get all constraints imposed on a package with the given name
     *
     * @throws \OutOfBoundsException if no such package is known
     * @return string[]
     */
    function getConstraints(string $packageName): array
    {
        if (!isset($this->constraintMap[$packageName])) {
            throw new \OutOfBoundsException(sprintf('Package "%s" is not known', $packageName));
        }

        return $this->constraintMap[$packageName];
    }

    /**
     * Get sources for the given package name
     *
     * Return value format:
     *
     *      array(
     *          array(
     *              'repository' => Repository
     *              'package' => \stdClass or NULL
     *              'constraints' => string
     *          )
     *          ...
     *      )
     *
     * @throws \OutOfBoundsException if no such package is known
     * @return array[]
     */
    function getSources(string $packageName): array
    {
        $sources = [];

        if (!isset($this->sourceMap[$packageName])) {
            throw new \OutOfBoundsException(sprintf('Package "%s" is not known', $packageName));
        }

        foreach ($this->sourceMap[$packageName] as $index => $source) {
            $sources[] = [
                'repository' => $source[0],
                'package' => $source[1] ?? null,
                'constraints' => $this->constraintMap[$packageName][$index],
            ];
        }

        return $sources;
    }
}
