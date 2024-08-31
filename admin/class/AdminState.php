<?php

namespace Sunlight\Admin;

class AdminState
{
    public array $modules;
    public string $currentModule;
    /** @var array<string, int>  */
    public array $menu;
    public bool $loginLayout = false;
    public bool $wysiwygAvailable = false;
    /** @var string[] */
    public array $bodyClasses = [];
    public bool $access;
    public ?string $redirectTo = null;
    public ?string $title = null;
    public ?array $assets = null;
    public bool $dark = false;
    public string $output = '';

    function redirect(string $url): void
    {
        $this->redirectTo = $url;
    }
}
