<?php

use Sunlight\Core;
use Sunlight\Picture;
use Sunlight\Router;

defined('_root') or exit;

return function ($cesta = "", $rozmery = null, $titulek = null, $lightbox = null) {
    if($cesta === ""){
        return '{' . _lang('pic.load.2') . '}';
    }
    $cesta = _root . $cesta;

    $resize_opts = Picture::parseResizeOptions($rozmery ?? "?x128");
    if (isset($titulek) && $titulek != "") {
        $titulek = _e($titulek);
    }
    if (!isset($lightbox)) {
        $lightbox = Core::$hcmUid;
    }

    $thumb = Picture::getThumbnail($cesta, $resize_opts);

    return "<a href='" . _e(Router::file($cesta)) . "' target='_blank' class='lightbox' data-gallery-group='lb_hcm" . _e($lightbox) . "'" . (($titulek != "") ? ' title=\'' . _e($titulek) . '\'' : '') . "><img src='" . _e(Router::file($thumb)) . "' alt='" . _e($titulek ?: basename($cesta)) . "'></a>\n";
};
