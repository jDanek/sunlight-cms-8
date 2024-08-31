<?php

namespace Sunlight\Util;

use Sunlight\GenericTemplates;

class SelectOption
{
    /**
     * @param array<string, scalar|null> $attrs
     */
    function __construct(
        public string|int|null $value,
        public ?string $label = null,
        public array $attrs = [],
        public bool $doubleEncodeLabel = true
    ) {
        $this->label = $label ?? $value;
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
