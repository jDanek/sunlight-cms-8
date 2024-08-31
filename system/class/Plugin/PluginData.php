<?php

namespace Sunlight\Plugin;

use Sunlight\Util\StringHelper;

class PluginData
{
    public string $camelCasedName;
    public string $dir;
    public ?string $status = null;
    public ?bool $installed = null;
    public bool $vendor = false;
    /** @var string[] */
    public array $errors = [];
    public ?array $options = null;

    function __construct(
        public string $type,
        public string $id,
        public string $name,
        public string $file,
        public string $webPath
    ) {
        $this->camelCasedName = StringHelper::toCamelCase($name);
        $this->dir = dirname($file);
    }

    function isOk(): bool
    {
        return $this->status === Plugin::STATUS_OK;
    }

    function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    function addError(string ...$errors): void
    {
        if ($errors) {
            array_push($this->errors, ...$errors);
        }
    }
}
