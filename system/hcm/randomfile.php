<?php

use Sunlight\Core;
use Sunlight\Picture;
use Sunlight\Router;

defined('_root') or exit;

return function ($cesta = "", $typ = 'text', $pocet = 1, $rozmery_nahledu = null) {
    $result = "";
    $cesta = _root . $cesta;
    if (mb_substr($cesta, -1, 1) != "/") {
        $cesta .= "/";
    }
    $pocet = (int) $pocet;

    if (file_exists($cesta) && is_dir($cesta)) {
        $handle = opendir($cesta);

        switch ($typ) {
            case 'image':
            case 2:
                $allowed_extensions = Core::$imageExt;
                $resize_opts = Picture::parseResizeOptions($rozmery_nahledu);
                break;
            case 'text':
            default:
                $allowed_extensions = ["txt", "htm", "html"];
                break;
        }

        $items = [];
        while (($item = readdir($handle)) !== false) {
            $ext = pathinfo($item);
            if (isset($ext['extension'])) {
                $ext = mb_strtolower($ext['extension']);
            } else {
                $ext = "";
            }
            if (is_dir($cesta . $item) || $item == "." || $item == ".." || !in_array($ext, $allowed_extensions)) {
                continue;
            }
            $items[] = $item;
        }

        if (count($items) != 0) {
            if ($pocet > count($items)) {
                $pocet = count($items);
            }
            $randitems = array_rand($items, $pocet);
            if (!is_array($randitems)) {
                $randitems = [$randitems];
            }

            foreach ($randitems as $item) {
                $item = $items[$item];
                switch ($typ) {
                    case 2:
                        $thumb = Picture::getThumbnail($cesta . $item, $resize_opts);
                        $result .= "<a href='" . _e(Router::file($cesta . $item)) . "' target='_blank' class='lightbox' data-gallery-group='lb_hcm" . Core::$hcmUid . "'><img src='" . _e(Router::file($thumb)) . "' alt='" . _e($item) . "'></a>\n";
                        break;
                    default:
                        $result .= file_get_contents($cesta . $item);
                        break;
                }
            }
        }

        closedir($handle);
    }

    return $result;
};
