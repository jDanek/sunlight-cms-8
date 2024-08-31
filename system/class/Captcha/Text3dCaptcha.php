<?php

namespace Sunlight\Captcha;

use Sunlight\Image\Image;

/**
 * Simple axonometric 3D text CAPTCHA
 * 
 * Based on code by martin.hozik@cleverweb.cz
 */
class Text3dCaptcha
{
    private float $scale = 5.0;
    private float $projectionAngle = 7.6;
    private int $font = 5;
    private int $foregroundColor = 0;
    private int $horizontalPadding = 3;
    private int $verticalPadding = 1;
    private int $letterSpacing = 1;
    private int $backgroundColor = 0xffffff;
    private int $noise = 0x30;

    function setScale(float $scale): void
    {
        $this->scale = $scale;
    }

    /**
     * @param float $projectionAngle axonometric projection angle (rad)
     */
    function setProjectionAngle(float $projectionAngle): void
    {
        $this->projectionAngle = $projectionAngle;
    }

    /**
     * @param int $font font identifier {@see imageloadfont()}
     */
    function setFont(int $font): void
    {
        $this->font = $font;
    }

    function setForegroundColor(int $foregroundColor): void
    {
        $this->foregroundColor = $foregroundColor;
    }

    function setHorizontalPadding(int $horizontalPadding): void
    {
        $this->horizontalPadding = $horizontalPadding;
    }

    function setVerticalPadding(int $verticalPadding): void
    {
        $this->verticalPadding = $verticalPadding;
    }

    function setLetterSpacing(int $letterSpacing): void
    {
        $this->letterSpacing = $letterSpacing;
    }

    function setBackgroundColor(int $backgroundColor): void
    {
        $this->backgroundColor = $backgroundColor;
    }

    /**
     * @param int $noise noise intensity (0 - 255)
     */
    function setNoise(int $noise): void
    {
        $this->noise = $noise;
    }

    /**
     * Draw text as PNG CAPTCHA image
     */
    function draw(string $text): Image
    {
        if ($text === '') {
            throw new \InvalidArgumentException('No text given');
        }

        $w = $this->computeTextWidth($text) + $this->horizontalPadding * 2;
        $h = imagefontheight($this->font) + $this->verticalPadding * 2;
        $pad = (int) ($this->scale * $h * cos($this->projectionAngle));

        $matrix = Image::blank($w, $h);

        $this->drawText($matrix, $text, $this->horizontalPadding, $this->verticalPadding);
        $this->drawNoise($matrix, $this->noise);

        $captcha = Image::blank((int) ($w * $this->scale + $pad), (int) ($h * sin($this->projectionAngle) * $this->scale));

        if (function_exists('imageantialias')) {
            imageantialias($captcha->resource, true);
        }

        imagefill($captcha->resource, 0, 0, $this->backgroundColor);

        for ($x = 1; $x < $w - 1; $x++) {
            for ($y = 1; $y < $h - 1; $y++) {
                [$x1, $y1] = $this->to2d($x, $y, imagecolorat($matrix->resource, $x, $y) / 0xFF);
                [$x2, $y2] = $this->to2d($x - 1, $y + 1, imagecolorat($matrix->resource, $x - 1, $y + 1) / 0xFF);
                imageline($captcha->resource, $x1 + $pad, $y1, $x2 + $pad, $y2, $this->foregroundColor);
            }
        }

        return $captcha;
    }

    private function computeTextWidth(string $text): int
    {
        $numChars = strlen($text);

        return imagefontwidth($this->font) * $numChars + $this->letterSpacing * ($numChars - 1);
    }

    /**
     * @return int[]
     */
    private function to2d(float $x, float $y, float $z): array
    {
        return [
            (int) ($x * $this->scale - $y * $this->scale * cos($this->projectionAngle)),
            (int) ($y * $this->scale * sin($this->projectionAngle) - $z * $this->scale),
        ];
    }

    private function drawText(Image $image, string $text, int $x, int $y): void
    {
        $fontWidth = imagefontwidth($this->font);

        for ($i = 0; isset($text[$i]); ++$i) {
            imagestring($image->resource, $this->font, $x, $y, $text[$i], 0xFF);

            $x += $fontWidth + $this->letterSpacing;
        }
    }

    private function drawNoise(Image $image, int $intensity): void
    {
        if ($intensity === 0) {
            return;
        }

        for ($x = 0; $x < $image->width; $x++) {
            for ($y = 0; $y < $image->height; $y++) {
                imagesetpixel($image->resource, $x, $y, imagecolorat($image->resource, $x, $y) + random_int(0, $intensity));
            }
        }
    }
}
