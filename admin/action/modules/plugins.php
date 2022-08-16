<?php

use Sunlight\Core;
use Sunlight\Message;
use Sunlight\Plugin\InactivePlugin;
use Sunlight\Plugin\Plugin;
use Sunlight\Router;
use Sunlight\Xsrf;

defined('SL_ROOT') or exit;

// vycisteni cache
if (isset($_GET['clear'])) {
    Core::$pluginManager->clearCache();
    $_admin->redirect(Router::admin('plugins', ['query' => ['cleared' => 1]]));

    return;
}

if (isset($_GET['cleared'])) {
    $output .= Message::ok(_lang('global.done'));
}

// pomocne funkce
$renderPluginAuthor = function ($author, $url) {
    $output = '';

    if (!empty($url)) {
        $output .= '<a href="' . _e($url) . '" target="_blank">';
    }
    if (!empty($author)) {
        $output .= _e($author);
    } elseif (!empty($url)) {
        $output .= _e(parse_url($url, PHP_URL_HOST) ?? '');
    }
    if (!empty($url)) {
        $output .= '</a>';
    }

    if ($output !== '') {
        $output = '<li><strong>' . _lang('admin.plugins.author') . ":</strong> {$output}</li>\n";
    }

    return $output;
};

// tlacitka
$output .= '<p>
        <a class="button" href="' . _e(Router::admin('plugins-upload')) . '"><img src="' . _e(Router::path('admin/images/icons/plugin.png')) . '" alt="upload" class="icon">' . _lang('admin.plugins.upload') . '</a>
        <a class="button" href="' . _e(Router::admin('plugins', ['query' => ['clear' => 1]])) . '"><img src="' . _e(Router::path('admin/images/icons/refresh.png')) . '" alt="clear" class="icon">' . _lang('admin.plugins.clear_cache') . '</a>
        <a class="button right" href="https://sunlight-cms.cz/resource/get-plugins" target="_blank"><img src="' . _e(Router::path('admin/images/icons/show.png')) . '" alt="get" class="icon">' . _lang('admin.plugins.get') . '</a>
</p>
';

// seznam pluginu
foreach (Core::$pluginManager->getTypes() as $type) {
    $plugins = Core::$pluginManager->getPlugins()->getByType($type->getName());
    $inactivePlugins = Core::$pluginManager->getInactivePlugins()->getByType($type->getName());

    $output .= "<fieldset>\n";
    $output .= '<legend>' . _lang('admin.plugins.title.' . $type->getName()) . ' (' . (count($plugins) + count($inactivePlugins)) . ")</legend>\n";
    $output .= '<table class="list list-hover plugin-list">
<thead>
    <tr>
        <th>' . _lang('admin.plugins.plugin') . '</th>
        <th>' . _lang('admin.plugins.description') . '</th>
    </tr>
</thead>
<tbody>
';

    // vykreslit pluginy
    foreach (array_merge($plugins, $inactivePlugins) as $name => $plugin) {
        /* @var $plugin Plugin */

        $isInactive = $plugin instanceof InactivePlugin;

        // nacist data z pluginu
        $title = $plugin->getOption('name');
        $descr = $plugin->getOption('description');
        $version = $plugin->getOption('version');
        $author = $plugin->getOption('author');
        $url = $plugin->getOption('url');

        // urcit tridu radku
        if ($plugin->hasErrors()) {
            $rowClass = 'row-danger';
        } elseif ($plugin->needsInstallation()) {
            $rowClass = 'row-warning';
        } else {
            $rowClass = null;
        }

        // vykreslit radek
        $output .= '
    <tr' . ($rowClass !== null ? " class=\"{$rowClass}\"" : '') . '>
        <td>
            ' . ($isInactive
                ? '<h3><del>' . _e($title) . '</del> <small>(' . _lang('admin.plugins.status.' . $plugin->getStatus()) . ')</small></h3>'
                : '<h3>' . _e($title) . '</h3>') . '
            <p>
                ' . _buffer(function () use ($plugin) {
                    foreach ($plugin->getActionList() as $action => $label) {
                        echo '<a class="button" href="' . _e(Xsrf::addToUrl(Router::admin('plugins-action', ['query' => ['id' => $plugin->getId(), 'action' => $action]]))) . '">' . _e($label) . "</a>\n";
                    }
                }) . '
            </p>
        </td>
        <td>
            ' . (!empty($descr) ? '<p>' . nl2br(_e($descr), false) . "</p>\n" : '') . '
            <ul class="inline-list">
                <li><strong>' . _lang('admin.plugins.version') . ':</strong> ' . _e($version) . '</li>
                ' . $renderPluginAuthor($author, $url) . '
            </ul>
        </td>
    </tr>
';
    }

    $output .= '</tbody>
</table>
</fieldset>
';
}
