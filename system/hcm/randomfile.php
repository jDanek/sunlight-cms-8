<?php

use Sunlight\Extend;
use Sunlight\Hcm;
use Sunlight\Image\ImageFormat;
use Sunlight\Image\ImageService;
use Sunlight\Image\ImageTransformer;
use Sunlight\Router;

return function ($dir = '', $type = 'text', $limit = 1, $thumbnail_size = null) {
    $result = '';
    $dir = SL_ROOT . $dir;
    if (mb_substr($dir, -1, 1) != '/') {
        $dir .= '/';
    }
    $limit = (int) $limit;

    if (file_exists($dir) && is_dir($dir)) {
        $handle = opendir($dir);

        switch ($type) {
            case 'image':
                $extension_filter = function ($ext) { return ImageFormat::isValidFormat($ext); };
                $resize_opts = ImageTransformer::parseResizeOptions($thumbnail_size);
                break;
            case 'text':
            default:
                $extension_filter = function ($ext) { return $ext === 'txt' || $ext === 'htm' || $ext === 'html'; };
                break;
        }

        $items = [];
        while (($item = readdir($handle)) !== false) {
            $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
            if (is_dir($dir . $item) || $item == '.' || $item == '..' || !$extension_filter($ext)) {
                continue;
            }
            $items[] = $item;
        }

        if (count($items) != 0) {
            if ($limit > count($items)) {
                $limit = count($items);
            }
            $randitems = array_rand($items, $limit);
            if (!is_array($randitems)) {
                $randitems = [$randitems];
            }

            foreach ($randitems as $item) {
                $item = $items[$item];
                switch ($type) {
                    case 'image':
                        $thumb = ImageService::getThumbnail('hcm.randomfile', $dir . $item, $resize_opts);
                        $result .= '<a href="' . _e(Router::file($dir . $item)) . '"'
                            . ' target="_blank"'
                            . Extend::buffer('image.lightbox', ['group' => 'hcm_rnd_' . Hcm::$uid])
                            . '>'
                            . '<img src="' . _e(Router::file($thumb)) . '" alt="' . _e($item) . '">'
                            . "</a>\n";
                        break;
                    default:
                        $result .= file_get_contents($dir . $item);
                        break;
                }
            }
        }

        closedir($handle);
    }

    return $result;
};
