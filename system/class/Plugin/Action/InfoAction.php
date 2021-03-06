<?php

namespace Sunlight\Plugin\Action;

use Kuria\Debug\Dumper;
use Sunlight\Action\ActionResult;
use Sunlight\Message;

/**
 * Show information about a plugin
 */
class InfoAction extends PluginAction
{
    function getTitle(): string
    {
        return _lang('admin.plugins.action.do.info');
    }

    protected function execute(): ActionResult
    {
        $plugin = $this->plugin;

        return ActionResult::output(_buffer(function () use ($plugin) { ?>
<table class="list valign-top">
    <tr>
        <th><?php echo _lang('global.id') ?></th>
        <td><?php echo _e($plugin->getId()) ?></td>
    </tr>

    <tr>
        <th><?php echo _lang('global.type') ?></th>
        <td><?php echo _e($plugin->getType()) ?></td>
    </tr>

    <tr>
        <th><?php echo _lang('global.dir') ?></th>
        <td><?php echo _e($plugin->getDirectory()) ?></td>
    </tr>

    <tr>
        <th><?php echo _lang('admin.plugins.implementation') ?></th>
        <td><?php echo _e(get_class($plugin)) ?></td>
    </tr>

    <tr>
        <th><?php echo _lang('admin.plugins.status') ?></th>
        <td>
            <?php echo _lang('admin.plugins.status.' . $plugin->getStatus()) ?>
        </td>
    </tr>

    <?php if ($plugin->hasErrors()): ?>
    <tr>
        <th><?php echo _lang('admin.plugins.errors') ?></th>
        <td class="text-danger">
            <?php echo Message::renderList($plugin->getErrors()) ?>
            <?php echo Message::renderList($plugin->getDefinitionErrors(), null, true) ?>
        </td>
    </tr>
    <?php endif ?>

    <tr>
        <th><?php echo _lang('admin.plugins.data') ?></th>
        <td>
            <pre><?php echo _e(file_get_contents($plugin->getFile())) ?></pre>
        </td>
    </tr>

    <tr>
        <th><?php echo _lang('admin.content.form.settings') ?></th>
        <td>
            <pre><?php echo _e(Dumper::dump($plugin->getOptions(), 4)) ?></pre>
        </td>
    </tr>

    <tr>
        <th><?php echo _lang('admin.plugins.object') ?></th>
        <td>
            <pre><?php echo _e(Dumper::dump($plugin)) ?></pre>
        </td>
    </tr>
</table>
<?php
        }));
    }
}
