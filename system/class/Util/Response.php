<?php

namespace Sunlight\Util;

use JetBrains\PhpStorm\NoReturn;
use Kuria\Debug\Output;
use Kuria\Url\Url;
use Sunlight\Core;

abstract class Response
{
    /**
     * Send redirection headers
     *
     * @param string $url absolute URL
     * @param bool $permanent create a permanent redirect 1/0
     */
    static function redirect(string $url, bool $permanent = false): void
    {
        header('HTTP/1.1 ' . ($permanent ? '301 Moved Permanently' : '302 Found'));
        header('Location: ' . $url);
    }

    /**
     * Redirect back to the previous page and exit
     *
     * @param string|null $url URL to return to, defaults to {@see Response::getReturnUrl()}
     * @return never-return
     */
    #[NoReturn]
    static function redirectBack(?string $url = null): void
    {
        if ($url === null) {
            $url = self::getReturnUrl();
        }

        if (!headers_sent()) {
            self::redirect($url);
        } else {
            ?>
            <meta http-equiv="refresh" content="1;url=<?= _e($url) ?>">
            <p><a href="<?= _e($url) ?>"><?= _lang('global.continue') ?></a></p>
            <?php
        }

        exit;
    }

    /**
     * Determine return URL
     *
     * This function will attempt to load it from (in order of priority):
     *
     * 1) $_GET['_return']
     * 2) referer header
     * 3) system's base URL
     */
    static function getReturnUrl(): string
    {
        $specifiedUrl = Request::get('_return', '');
        $baseUrl = Core::getBaseUrl();
        $returnUrl = clone $baseUrl;

        if ($specifiedUrl !== '') {
            if ($specifiedUrl[0] === '/') {
                $returnUrl->setPath($specifiedUrl);
            }  elseif ($specifiedUrl !== './') {
                $returnUrl->setPath($returnUrl->getPath() . '/' . $specifiedUrl);
            }
        } elseif (Request::hasHeader('referer')) {
            $returnUrl = Url::parse(Request::header('referer'));
        }

        // reject URLs with different hostname (prevent open redirection)
        if ($baseUrl->getHost() !== $returnUrl->getHost()) {
            $returnUrl = $baseUrl;
        }

        return $returnUrl->buildAbsolute();
    }

    /**
     * Send file download headers
     *
     * @param string $filename file name
     * @param int|null $filesize file size, if known
     */
    static function download(string $filename, ?int $filesize = null): void
    {
        header('Content-Type: application/octet-stream');
        header(sprintf('Content-Disposition: attachment; filename="%s"', $filename));

        if ($filesize !== null) {
            header(sprintf('Content-Length: %d', $filesize));
        }
    }

    /**
     * Send a local file with download headers and exit
     *
     * @param string $filepath path to the file
     * @param string|null $filename custom file name
     * @return never-return
     */
    #[NoReturn]
    static function downloadFile(string $filepath, ?string $filename = null): void
    {
        self::ensureHeadersNotSent();
        Filesystem::ensureFileExists($filepath);

        if ($filename === null) {
            $filename = basename($filepath);
        }

        Output::cleanBuffers();
        self::download($filename, filesize($filepath));

        $handle = fopen($filepath, 'rb');

        while (!feof($handle)) {
            echo fread($handle, 131072);
            flush();
        }

        fclose($handle);

        exit;
    }

    /**
     * Encode and send data as JSON and exit
     * 
     * @return never-return
     */
    #[NoReturn]
    static function json($data, int $flags = Json::DEFAULT, int $depth = Json::DEFAULT_DEPTH): void
    {
        header('Content-Type: application/json');
        echo Json::encode($data, $flags, $depth);

        exit;
    }

    /**
     * Make sure headers have not been sent yet
     *
     * @throws \RuntimeException if headers were already sent
     */
    static function ensureHeadersNotSent(): void
    {
        if (headers_sent($file, $line)) {
            throw new \RuntimeException(sprintf('Headers already sent (output started in "%s" on line %d)', $file, $line));
        }
    }
}
