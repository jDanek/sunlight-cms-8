<?php

namespace Sunlight\Plugin;

use Sunlight\Callback\CallbackHandler;
use Sunlight\Database\Database as DB;
use Sunlight\Extend;
use Sunlight\Localization\LocalizationDictionary;
use Sunlight\Localization\LocalizationDirectory;
use Sunlight\Settings;
use Sunlight\User;

class TemplatePlugin extends Plugin
{
    const DEFAULT_LAYOUT = 'default';

    protected LocalizationDictionary $lang;

    function __construct(PluginData $data, PluginManager $manager)
    {
        parent::__construct($data, $manager);

        $this->lang = new LocalizationDirectory($this->data->options['lang_dir']);
    }

    function isEssential(): bool
    {
        return $this->data->name === Settings::get('default_template');
    }

    /**
     * Notify the template plugin that it is going to be used to render a front end page
     */
    function begin(string $layout): void
    {
        // register events once the template is being used
        foreach ($this->data->options['events'] as $subscriber) {
            Extend::reg(
                $subscriber['event'],
                CallbackHandler::fromArray($subscriber, $this),
                $subscriber['priority']
            );
        }
    }

    /**
     * Get the localization dictionary
     */
    function getLang(): LocalizationDictionary
    {
        return $this->lang;
    }
    
    /**
     * Get template file path for the given layout
     */
    function getTemplate(string $layout = self::DEFAULT_LAYOUT): string
    {
        if (!isset($this->data->options['layouts'][$layout])) {
            $layout = self::DEFAULT_LAYOUT;
        }

        return $this->data->options['layouts'][$layout]['template'];
    }

    /**
     * See if the given layout exists
     *
     * @param string $layout layout name
     */
    function hasLayout(string $layout): bool
    {
        return isset($this->data->options['layouts'][$layout]);
    }

    /**
     * Get list of template layout identifiers
     *
     * @return string[]
     */
    function getLayouts(): array
    {
        return array_keys($this->data->options['layouts']);
    }

    /**
     * Get label for the given layout
     *
     * @param string $layout layout name
     */
    function getLayoutLabel(string $layout): string
    {
        return $this->lang->get("{$layout}.label");
    }

    /**
     * See if the given slot exists
     */
    function hasSlot(string $layout, string $slot): bool
    {
        return in_array($slot, $this->getSlots($layout), true);
    }

    /**
     * Get list of slot identifiers for the given layout
     *
     * @param string $layout layout name
     * @return string[]
     */
    function getSlots(string $layout): array
    {
        if (isset($this->data->options['layouts'][$layout])) {
            return $this->data->options['layouts'][$layout]['slots'];
        }

        return [];
    }

    /**
     * Get label for the given layout and slot
     */
    function getSlotLabel(string $layout, string $slot): string
    {
        return $this->lang->get("{$layout}.slot.{$slot}");
    }

    /**
     * Get boxes for the given layout
     */
    function getBoxes(string $layout = self::DEFAULT_LAYOUT): array
    {
        if (!isset($this->data->options['layouts'][$layout])) {
            $layout = self::DEFAULT_LAYOUT;
        }

        $boxes = [];
        $query = DB::query(
            'SELECT id,title,content,slot,page_ids,page_children,class'
            . ' FROM ' . DB::table('box')
            . ' WHERE template=' . DB::val($this->getName()) . ' AND layout=' . DB::val($layout) . ' AND visible=1' . (!User::isLoggedIn() ? ' AND public=1' : '') . ' AND level <= ' . User::getLevel()
            . ' ORDER BY ord'
        );

        while ($box = DB::row($query)) {
            $boxes[$box['slot']][$box['id']] = $box;
        }

        return $boxes;
    }
}
