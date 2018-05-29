<?php

use Sunlight\Core;

if (!defined('_root')) {
    exit;
}

// parametry
$type = _get('type');
$name = _get('name');
$action = _get('action');

if (!_xsrfCheck(true)) {
    $output .= _msg(_msg_err, _lang('global.badinput'));

    return;
}

// nacist plugin a akci
if (
    !Core::$pluginManager->isValidType($type)
    || ($plugin = Core::$pluginManager->find($type, $name, false)) === null
    || ($action = $plugin->getAction($action)) === null
) {
    $output .= _msg(_msg_err, _lang('global.badinput'));

    return;
}

// provest akci
$result = $action->run();

if ($result->isComplete()) {
    Core::$pluginManager->purgeCache();
}

// zobrazit vysledek
$output .= \Sunlight\Admin\Admin::backlink('index.php?p=plugins');
$output .= '<h1>' . _e($action->getTitle()) . ': ' . _e($plugin->getOption('name')) . "</h1>\n";
$output .= $result;
