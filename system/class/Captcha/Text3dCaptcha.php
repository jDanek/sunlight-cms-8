<?php

namespace Sunlight\Captcha;
use Sunlight\Util\Math;

/**
 * Simple axonometric 3D text CAPTCHA
 *
 * @author martin.hozik@cleverweb.cz
 */
class Text3dCaptcha
{
    /** @var float */
    protected $scale = 5.0;
    /** @var float */
    protected $projectionAngle = 7.6;
    /** @var int */
    protected $font = 5;
    /** @var int */
    protected $foregroundColor = 0;
    /** @var int */
    protected $horizontalPadding = 3;
    /** @var int */
    protected $verticalPadding = 1;
    /** @var int */
    protected $letterSpacing = 1;
    /** @var int */
    protected $backgroundColor = 0xffffff;
    /** @var int */
    protected $noise = 0x30;

    /**
     * @param float $scale
     */
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

    /**
     * @param int $foregroundColor
     */
    function setForegroundColor(int $foregroundColor): void
    {
        $this->foregroundColor = $foregroundColor;
    }

    /**
     * @param int $horizontalPadding
     */
    function setHorizontalPadding(int $horizontalPadding): void
    {
        $this->horizontalPadding = $horizontalPadding;
    }

    /**
     * @param int $verticalPadding
     */
    function setVerticalPadding(int $verticalPadding): void
    {
        $this->verticalPadding = $verticalPadding;
    }

    /**
     * @param int $letterSpacing
     */
    function setLetterSpacing(int $letterSpacing): void
    {
        $this->letterSpacing = $letterSpacing;
    }

    /**
     * @param int $backgroundColor
     */
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
     * Output text as PNG CAPTCHA image
     *
     * @param string $text
     * @return resource
     */
    function draw(string $text)
    {
        if ($text === '') {
            throw new \InvalidArgumentException('No text given');
        }

        $w = $this->computeTextWidth($text) + $this->horizontalPadding * 2;
        $h = imagefontheight($this->font) + $this->verticalPadding * 2;
        $pad = $this->scale * $h * cos($this->projectionAngle);

        $matrix = imagecreatetruecolor($w, $h);

        $this->drawText($matrix, $text, $this->horizontalPadding, $this->verticalPadding);
        $this->drawNoise($matrix, $this->noise);

        $captcha = imagecreatetruecolor($w * $this->scale + $pad, $h * sin($this->projectionAngle) * $this->scale);
        if (function_exists('imageantialias')) {
            imageantialias($captcha, true);
        }
        imagefill($captcha, 0, 0, $this->backgroundColor);

        for ($x = 1; $x < $w - 1; $x++)
            for ($y = 1; $y < $h - 1; $y++) {
                [$x1, $y1] = $this->to2d($x, $y, imagecolorat($matrix, $x, $y) / 0xFF);
                [$x2, $y2] = $this->to2d($x - 1, $y + 1, imagecolorat($matrix, $x - 1, $y + 1) / 0xFF);
                imageline($captcha, $x1 + $pad, $y1, $x2 + $pad, $y2, $this->foregroundColor);
            }

        imagedestroy($matrix);

        return $captcha;
    }

    protected function computeTextWidth(string $text): int
    {
        $numChars = strlen($text);

        return imagefontwidth($this->font) * $numChars + $this->letterSpacing * ($numChars - 1);
    }

    /**
     * @param int $x
     * @param int $y
     * @param int $z
     * @return array
     */
    protected function to2d(int $x, int $y, int $z): array
    {
        return [
            $x * $this->scale - $y * $this->scale * cos($this->projectionAngle),
            $y * $this->scale * sin($this->projectionAngle) - $z * $this->scale,
        ];
    }

    /**
     * @param resource $image
     * @param string   $text
     * @param int      $x
     * @param int      $y
     */
    protected function drawText($image, string $text, int $x, int $y): void
    {
        $fontWidth = imagefontwidth($this->font);

        for ($i = 0; isset($text[$i]); ++$i) {
            imagestring($image, $this->font, $x, $y, $text[$i], 0xFF);

            $x += $fontWidth + $this->letterSpacing;
        }
    }

    /**
     * @param resource $image
     * @param int      $intensity
     */
    protected function drawNoise($image, int $intensity): void
    {
        if ($intensity === 0) {
            return;
        }

        for ($x = 0; $x < imagesx($image); $x++) {
            for ($y = 0; $y < imagesy($image); $y++) {
                imagesetpixel($image, $x, $y, imagecolorat($image, $x, $y) + Math::randomInt(0, $intensity));
            }
        }
    }
}