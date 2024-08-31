<?php

namespace Sunlight\Util;

use Sunlight\Settings;

abstract class Filesystem
{
    private const UNSAFE_EXT_REGEX = '{php\d*?|[ps]html|asp|py|cgi}Ai';

    /** @var array<string, true>|null */
    private static ?array $allowedExtMap = null;

    /**
     * Create a temporary file in system/tmp/
     */
    static function createTmpFile(): TemporaryFile
    {
        return new TemporaryFile(null, SL_ROOT . 'system/tmp');
    }

    /**
     * Verify that the file name in a path is not a server-side script or a hidden file
     */
    static function isSafeFile(string $filepath): bool
    {
        // check extra whitespace
        if ($filepath !== trim($filepath)) {
            return false;
        }

        $filename = basename($filepath);

        // check empty filename
        if ($filename === '') {
            return false;
        }

        // check hidden files
        if ($filename[0] === '.') {
            return false;
        }

        // check all extensions since some webservers will evaluate files such as "example.php.html"
        $parts = explode('.', $filename);

        for ($i = 1; isset($parts[$i]); ++$i) {
            if (preg_match(self::UNSAFE_EXT_REGEX, $parts[$i])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Verify that a the file name in a path has an allowed extension
     */
    static function isAllowedExtension(string $filepath): bool
    {
        if (self::$allowedExtMap === null) {
            self::$allowedExtMap = array_fill_keys(
                explode(',', Settings::get('allowed_file_ext')),
                true
            );
        }

        return isset(self::$allowedExtMap[pathinfo($filepath, PATHINFO_EXTENSION)]);
    }

    /**
     * Make sure that a file exists
     *
     * @throws \RuntimeException if it doesn't
     */
    static function ensureFileExists(string $filepath): void
    {
        if (!is_file($filepath)) {
            throw new \RuntimeException(sprintf('File "%s" does not exist', $filepath));
        }
    }

    /**
     * Make sure a directory exists, optionally creating it
     * 
     * @throws \RuntimeException on failure
     */
    static function ensureDirectoryExists(string $path, bool $create, int $mode = 0777, bool $replaceFile = false): void
    {
        if (is_dir($path)) {
            return;
        }

        if (!$create) {
            throw new \RuntimeException(sprintf('Directory "%s" does not exist', $path));
        }

        $path = rtrim($path, '\\/');

        if (is_file($path)) {
            if ($replaceFile) {
                unlink($path);
            } else {
                throw new \RuntimeException(sprintf('Cannot create directory at "%s" - a file already exists', $path));
            }
        }

        mkdir($path, $mode, true);
    }

    /**
     * Normalize a path
     */
    static function normalizePath(string $path): string
    {
        return strtr($path, '\\', '/');
    }

    /**
     * Normalize a path and add a base, if the path is not absolute
     */
    static function normalizeWithBasePath(string $basePath, string $path): string
    {
        $basePath = self::normalizePath($basePath);
        $path = self::normalizePath($path);

        return
            $path === ''
                ? $basePath
                : (self::isAbsolutePath($path)
                    ? $path
                    : $basePath . '/' . $path
                );
    }

    /**
     * See if a path is absolute
     */
    static function isAbsolutePath(string $path): bool
    {
        return
            isset($path[0]) && ($path[0] === '/' || $path[0] === '\\')
            || isset($path[1]) && $path[1] === ':';
    }

    /**
     * Evaluate relative parts of a path
     *
     * The returned path will have a trailing slash if $isFile = FALSE.
     *
     * The returned path may have a leading slash if $allowLeadingSlash = TRUE.
     *
     * @param string $path the path
     * @param bool $isFile it is a file path 1/0
     * @param bool $allowLeadingSlash allow slash at the beginning of the resulting path
     */
    static function parsePath(string $path, bool $isFile = false, bool $allowLeadingSlash = false): string
    {
        $segments = explode('/', self::normalizePath($path));
        $parentJumps = 0;

        for ($i = count($segments) - 1; $i >= 0; --$i) {
            $isCurrent = $segments[$i] === '.';
            $isParent = $segments[$i] === '..';
            $isEmpty = $segments[$i] === '';
            $isDot = $isCurrent || $isParent;

            if ($isParent) {
                ++$parentJumps;
            }

            if ($isDot || $isEmpty && (!$allowLeadingSlash || $i > 0) || $parentJumps > 0) {
                unset($segments[$i]);

                if ($parentJumps > 0 && !$isDot && !$isEmpty) {
                    --$parentJumps;
                }
            }
        }

        $result = '';

        if ($parentJumps > 0) {
            $result .= str_repeat('../', $parentJumps);
        }

        $result .= implode('/', $segments);

        if (!$isFile && !empty($segments)) {
            $result .= '/';
        }

        if ($result === '') {
            $result = './';
        }

        return $result;
    }

    /**
     * Try to resolve a path with an optional base directory
     *
     * @return string|null the resolved path or NULL if not valid
     */
    static function resolvePath(string $path, bool $isFile, ?string $requiredBaseDir = null): ?string
    {
        $path = self::parsePath($path, $isFile, true);

        if ($requiredBaseDir !== null) {
            $requiredBaseDir = self::parsePath($requiredBaseDir, false, true);

            if (
                strncmp($requiredBaseDir, $path, strlen($requiredBaseDir)) !== 0
                || substr_count($path, '..') > substr_count($requiredBaseDir, '..')
            ) {
                return null; // don't allow a path outside the required base directory
            }
        }

        return $path;
    }

    /**
     * Create directory iterator
     */
    static function createIterator(string $path): \FilesystemIterator
    {
        return new \FilesystemIterator(
            $path,
            \FilesystemIterator::CURRENT_AS_FILEINFO
            | \FilesystemIterator::SKIP_DOTS
            | \FilesystemIterator::UNIX_PATHS
        );
    }
    
    /**
     * Create recursive directory iterator
     */
    static function createRecursiveIterator(string $path, int $flags = \RecursiveIteratorIterator::SELF_FIRST): \RecursiveIteratorIterator
    {
        $directoryIterator = new \RecursiveDirectoryIterator(
            $path,
            \FilesystemIterator::CURRENT_AS_FILEINFO
            | \FilesystemIterator::SKIP_DOTS
            | \FilesystemIterator::UNIX_PATHS
        );

        return new \RecursiveIteratorIterator(
            $directoryIterator,
            $flags
        );
    }

    /**
     * Check whether a directory is empty
     */
    static function isDirectoryEmpty(string $path): bool
    {
        $isEmpty = true;
        
        $handle = opendir($path);

        while (($item = readdir($handle)) !== false) {
            if ($item !== '.' && $item !== '..') {
                $isEmpty = false;
                break;
            }
        }

        closedir($handle);

        return $isEmpty;
    }

    /**
     * Recursively verify privileges for the given directory and all its contents
     *
     * @param string $path path to the directory
     * @param bool $checkWrite test write access as well 1/0 (false = test only read access)
     * @param array|null $failedPaths an array variable to put failed paths to (null = do not track)
     */
    static function checkDirectory(string $path, bool $checkWrite = true, ?array &$failedPaths = null): bool
    {
        $iterator = self::createRecursiveIterator($path);

        if ($failedPaths !== null) {
            $failedPaths = [];
        }

        foreach ($iterator as $item) {
            /* @var $item \SplFileInfo */
            if (!$item->isReadable() || ($checkWrite && !$item->isWritable())) {
                if ($failedPaths !== null) {
                    $failedPaths[] = $item->getPathname();
                } else {
                    return false;
                }
            }
        }

        return empty($failedPaths);
    }

    /**
     * Recursively calculate size of a directory
     */
    static function getDirectorySize(string $path): int
    {
        $totalSize = 0;

        foreach (self::createRecursiveIterator($path) as $item) {
            /* @var $item \SplFileInfo */
            if ($item->isFile()) {
                $totalSize += $item->getSize();
            }
        }

        return $totalSize;
    }

    /**
     * Recursively remove a directory
     * 
     * @param string $path path to the directory
     * @param-out string|null $failedPath variable that will contain a path that could not be removed
     */
    static function removeDirectory(string $path, ?string &$failedPath = null): bool
    {
        return self::purgeDirectory($path, false, null, $failedPath);
    }

    /**
     * Recursively empty a directory
     * 
     * @param string $path path to the directory
     * @param callable(\SplFileInfo):bool|null $filter decide which items directly inside the $path to remove
     * @param-out string|null $failedPath variable that will contain a path that could not be removed
     */
    static function emptyDirectory(string $path, ?callable $filter = null, ?string &$failedPath = null): bool
    {
        foreach (self::createIterator($path) as $item) {
            if ($filter !== null && !$filter($item)) {
                continue;
            }

            if ($item->isDir()) {
                if (!self::purgeDirectory($item->getPathname(), false, null, $failedPath)) {
                    return false;
                }
            } elseif (!@unlink($item->getPathname())) {
                $failedPath = $item->getPathname();

                return false;
            }
        }

        return true;
    }

    /**
     * Recursively purge a directory
     *
     * @param string $path path to the directory
     * @param bool $keepRoot do not remove the root directory 1/0
     * @param callable(\SplFileInfo, string):bool|null $filter callback to decide whether to remove an item or not
     * @param-out string|null $failedPath variable that will contain a path that could not be removed
     */
    static function purgeDirectory(string $path, bool $keepRoot, ?callable $filter = null, ?string &$failedPath = null): bool
    {
        $iterator = self::createRecursiveIterator($path, \RecursiveIteratorIterator::CHILD_FIRST);
        $keptDirMap = [];

        // iterate and remove children
        foreach ($iterator as $item) {
            if ($filter !== null && !$filter($item, $path)) {
                $keptDirMap[$item->getPath() . '/'] = true;

                continue;
            }

            $itemPath = $item->getPathname();

            if ($item->isDir()) {
                foreach ($keptDirMap as $keptDir => $_) {
                    if (strncmp($itemPath . '/', $keptDir, strlen($itemPath) + 1) === 0) {
                        continue 2;
                    }
                }

                if (!@rmdir($itemPath)) {
                    $failedPath = $itemPath;

                    return false;
                }
            } elseif (!@unlink($itemPath)) {
                $failedPath = $itemPath;

                return false;
            }
        }

        // remove root directory
        if (!$keepRoot && empty($keptDirs)) {
            if (!@rmdir($path)) {
                $failedPath = $itemPath;

                return false;
            }
        }

        return true;
    }

    /**
     * Deny access to a directory using a .htaccess file
     */
    static function denyAccessToDirectory(string $path): void
    {
        file_put_contents($path . '/.htaccess', <<<HTACCESS
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
    Order deny,allow
    Deny from all
</IfModule>

HTACCESS
        );
    }
}
