<?php

namespace Sunlight\Plugin;

use Sunlight\Util\StringHelper;

class PluginData
{
    public string $type;
    public string $id;
    public string $name;
    public string $camelCasedName;
    public string $dir;
    public string $file;
    public string $webPath;
    public ?string $status = null;
    public ?bool $installed = null;
    public bool $vendor = false;
    /** @var string[] */
    public array $errors = [];
    public ?array $options = null;

    function __construct(string $type, string $id, string $name, string $file, string $webPath)
    {
        $this->type = $type;
        $this->id = $id;
        $this->name = $name;
        $this->camelCasedName = StringHelper::toCamelCase($name);
        $this->dir = dirname($file);
        $this->file = $file;
        $this->webPath = $webPath;
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
