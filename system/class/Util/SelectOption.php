<?php

namespace Sunlight\Util;

use Sunlight\GenericTemplates;

class SelectOption
{
    /** @var array-key */
    public string|int $value;
    public string $label;
    /** @var array<string, scalar|null> */
    public array $attrs;
    public bool $doubleEncodeLabel;

    /**
     * @param array-key $value
     */
    function __construct(int|string $value, ?string $label = null, array $attrs = [], bool $doubleEncodeLabel = true)
    {
        $this->value = $value;
        $this->label = $label ?? $value;
        $this->attrs = $attrs;
        $this->doubleEncodeLabel = $doubleEncodeLabel;
    }

    function render(bool $selected = false): string
    {
        return '<option'
            . ($this->value !== null ? ' value="' . _e($this->value) . '"' : '')
            . ($selected ? ' selected' : '')
            . GenericTemplates::renderAttrs($this->attrs)
            . '>'
            . _e($this->label, $this->doubleEncodeLabel)
            . '</option>';
    }
}
