<?php

namespace Sunlight\Image;

use Sunlight\Util\Filesystem;

final class Image
{
    /** @var \GdImage|resource */
    public $resource;
    public int|false $width;
    public int|false $height;

    /**
     * @param \GdImage|resource $resource
     */
    function __construct($resource, ?int $width = null, ?int $height = null)
    {
        $this->resource = $resource;
        $this->width = $width ?? imagesx($resource);
        $this->height = $height ?? imagesy($resource);
    }

    /**
     * Create a blank image
     */
    static function blank(int $width, int $height): self
    {
        error_clear_last();
        $resource = @imagecreatetruecolor($width, $height);

        if ($resource === false) {
            throw new ImageException(ImageException::COULD_NOT_CREATE, null, error_get_last()['message'] ?? null);
        }

        return new self($resource, $width, $height);
    }

    /**
     * Enable transparency
     *
     * This clears the image contents.
     */
    function enableAlpha(): void
    {
        imagealphablending($this->resource, false);
        $transColor = imagecolorallocatealpha($this->resource, 0, 0, 0, 127);

        if ($transColor !== false) {
            imagefill($this->resource, 0, 0, $transColor);
            imagesavealpha($this->resource, true);
        }
    }

    /**
     * Save the image to a file
     *
     * @param array{
     *     jpg_quality?: int|null,
     *     png_quality?: int|null,
     *     png_filters?: int|null,
     *     webp_quality?: int|null,
     * } $options
     * @throws ImageException
     */
    function write(string $path, string $format, array $options = []): void
    {
        $tmpfile = Filesystem::createTmpFile();

        $this->generate($tmpfile->getPathname(), $format, $options);

        if (!$tmpfile->move($path)) {
            throw new ImageException(ImageException::MOVE_FAILED);
        }
    }

    /**
     * Output the image directly
     *
     * @param array{
     *     jpg_quality?: int|null,
     *     png_quality?: int|null,
     *     png_filters?: int|null,
     *     webp_quality?: int|null,
     * } $options
     * @throws ImageException
     */
    function output(string $format, array $options = []): void
    {
        $this->generate(null, $format, $options);
    }

    /**
     * @param string $format {@see ImageFormat}
     * @param array{
     *     jpg_quality?: int|null,
     *     png_quality?: int|null,
     *     png_filters?: int|null,
     *     webp_quality?: int|null,
     * } $options
     */
    private function generate(?string $filename, string $format, array $options): void
    {
        if (!ImageFormat::canWrite($format)) {
            throw new ImageException(ImageException::FORMAT_NOT_SUPPORTED);
        }

        error_clear_last();

        switch ($format) {
            case ImageFormat::JPG:
            case ImageFormat::JPEG:
                $result = @imagejpeg($this->resource, $filename, $options['jpg_quality'] ?? 80);
                break;

            case ImageFormat::PNG:
                $result = @imagepng($this->resource, $filename, $options['png_quality'] ?? 9, $options['png_filters'] ?? null);
                break;

            case ImageFormat::GIF:
                $result = @imagegif($this->resource, $filename);
                break;

            case ImageFormat::WEBP:
                $result = @imagewebp($this->resource, $filename, $options['webp_quality'] ?? 80);
                break;

            default:
                throw new ImageException(ImageException::FORMAT_NOT_SUPPORTED);
        }

        if (!$result) {
            throw new ImageException(ImageException::WRITE_FAILED, null, error_get_last()['message'] ?? null);
        }
    }
}
