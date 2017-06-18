<?php

namespace Sunlight\Plugin;

use Sunlight\Core;

class TemplateService
{
    const UID_TEMPLATE = 0;
    const UID_TEMPLATE_LAYOUT = 1;
    const UID_TEMPLATE_LAYOUT_SLOT = 2;

    /**
     * This is a static class
     */
    private function __construct()
    {
    }

    /**
     * Check if a template exists
     *
     * @param string $idt
     * @return bool
     */
    public static function templateExists($idt)
    {
        return Core::$pluginManager->has(PluginManager::TEMPLATE, $idt);
    }

    /**
     * Get a template for the given template identifier
     *
     * @param string $id
     * @return TemplatePlugin
     */
    public static function getTemplate($id)
    {
        return Core::$pluginManager->getTemplate($id);
    }

    /**
     * Get default template
     *
     * @return TemplatePlugin
     */
    public static function getDefaultTemplate()
    {
        return static::getTemplate(_default_template);
    }

    /**
     * Compose unique template component identifier
     *
     * @param string|TemplatePlugin $template
     * @param string|null           $layout
     * @param string|null           $slot
     * @return string
     */
    public static function composeUid($template, $layout = null, $slot = null)
    {
        $uid = $template instanceof TemplatePlugin
            ? $template->getId()
            : $template;

        if (null !== $layout || null !== $slot) {
            $uid .= ':' . $layout;
        }
        if (null !== $slot) {
            $uid .= ':' . $slot;
        }

        return $uid;
    }

    /**
     * Parse the given unique template component identifier
     *
     * @param string $uid
     * @param int    $type see TemplateHelper::UID_* constants
     * @return string[] template, [layout], [slot]
     */
    public static function parseUid($uid, $type)
    {
        $expectedComponentCount = $type + 1;

        return explode(':', $uid, $expectedComponentCount) + array_fill(0, $expectedComponentCount, '');
    }

    /**
     * Verify that the given unique template component identifier is valid
     * and points to existing components
     *
     * @param string $uid
     * @param int    $type see TemplateHelper::UID_* constants
     * @return bool
     */
    public static function validateUid($uid, $type)
    {
        return null !== static::getComponentsByUid($uid, $type);
    }

    /**
     * Get components identified by the given unique template component identifier
     *
     * @param string $uid
     * @param int    $type see TemplateHelper::UID_* constants
     * @return array|null array or null if the given identifier is not valid
     */
    public static function getComponentsByUid($uid, $type)
    {
        return call_user_func_array(
            array(get_called_class(), 'getComponents'),
            static::parseUid($uid, $type)
        );
    }

    /**
     * Get template components
     *
     * Returns an array with the following keys or NULL if the given
     * combination does not exist.
     *
     *      template => (object) instance of TemplatePlugin
     *      layout   => (string) layout identifier (only if $layout is not NULL)
     *      slot     => (string) slot identifier (only if both $layout and $slot are not NULL)
     *
     * @param string      $template
     * @param string|null $layout
     * @param string|null $slot
     * @return array|null array or null if the given combination does not exist
     */
    public static function getComponents($template, $layout = null, $slot = null)
    {
        if (!static::templateExists($template)) {
            return null;
        }

        $template = static::getTemplate($template);

        $components = array(
            'template' => $template,
        );

        if (null !== $layout) {
            if (!$template->hasLayout($layout)) {
                return null;
            }

            $components['layout'] = $layout;
        }

        if (null !== $slot && null !== $layout) {
            if (!$template->hasSlot($layout, $slot)) {
                return null;
            }

            $components['slot'] = $slot;
        }

        return $components;
    }

    /**
     * Get label for the given components
     *
     * @param TemplatePlugin $template
     * @param string|null    $layout
     * @param string|null    $slot
     * @param bool           $includeTemplateName
     * @return string
     */
    public static function getComponentLabel(TemplatePlugin $template, $layout = null, $slot = null, $includeTemplateName = true)
    {
        $parts = array();

        if ($includeTemplateName) {
            $parts[] = $template->getOption('name');
        }
        if (null !== $layout || null !== $slot) {
            $parts[] = $template->getLayoutLabel($layout);
        }
        if (null !== $slot) {
            $parts[] = $template->getSlotLabel($layout, $slot);
        }

        return implode(' - ', $parts);
    }

    /**
     * Get label for the given component array
     *
     * @see TemplateService::getComponents()
     *
     * @param array $components
     * @param bool  $includeTemplateName
     * @return string
     */
    public static function getComponentLabelFromArray(array $components, $includeTemplateName = true)
    {
        return static::getComponentLabel(
            $components['template'],
            isset($components['layout']) ? $components['layout'] : null,
            isset($components['slot']) ? $components['slot'] : null,
            $includeTemplateName
        );
    }

    /**
     * Get label for the given unique template component identifier
     *
     * @param string|null $uid
     * @param int         $type see TemplateHelper::UID_* constants
     * @param bool        $includeTemplateName
     * @return string
     */
    public static function getComponentLabelByUid($uid, $type, $includeTemplateName = true)
    {
        if (null !== $uid) {
            $components = static::getComponentsByUid($uid, $type);
        } else {
            $components = array(
                'template' => static::getDefaultTemplate(),
            );

            if ($type >= static::UID_TEMPLATE_LAYOUT) {
                $components['layout'] = TemplatePlugin::DEFAULT_LAYOUT;
            }
            if ($type >= static::UID_TEMPLATE_LAYOUT_SLOT) {
                $components['slot'] = '';
            }
        }

        if (null !== $components) {
            return static::getComponentLabelFromArray($components, $includeTemplateName);
        }

        return $uid;
    }
}