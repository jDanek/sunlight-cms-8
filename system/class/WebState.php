<?php

namespace Sunlight;

use Sunlight\Plugin\TemplatePlugin;
use Sunlight\Plugin\TemplateService;

class WebState
{
    /** Page */
    const PAGE = 0;
    /** Plugin output */
    const PLUGIN = 1;
    /** Module */
    const MODULE = 2;
    /** Redirection */
    const REDIR = 3;
    /** 404 */
    const NOT_FOUND = 4;
    /** 401 */
    const UNAUTHORIZED = 5;

    /** @var int|null output type (see WebState::* constants) */
    public ?int $type = null;
    /** @var int|null numeric identifier */
    public ?int $id = null;
    /** @var string|null string identifier */
    public ?string $slug = null;
    /** @var string|null part of the string identifier parsed as a segment */
    public ?string $segment = null;
    /** @var string content URL */
    public string $url;
    /** @var string|null HTML title (<title>) */
    public ?string $title = null;
    /** @var string|null meta description */
    public ?string $description = null;
    /** @var string|null top level heading (<h1>) */
    public ?string $heading = null;
    /** @var bool top level heading toggle */
    public bool $headingEnabled = true;
    /** @var string|null back link URL */
    public ?string $backlink = null;
    /** @var array<array{title: string, url: string}> */
    public array $crumbs = [];
    /** @var string|null redirection target */
    public ?string $redirectTo = null;
    /** @var bool permanent redirection 1/0 */
    public bool $redirectToPermanent = false;
    public TemplatePlugin $template;
    public string $templateLayout;
    public ?array $templateBoxes = null;
    public ?string $templatePath = null;
    /** @var string[] classes to put on <body> */
    public array $bodyClasses = [];
    /** @var string the content */
    public string $output = '';

    /**
     * Try to set current template using the template and layout identifier
     */
    function changeTemplate(string $idt): bool
    {
        $components = TemplateService::getComponentsByUid($idt, TemplateService::UID_TEMPLATE_LAYOUT);

        if ($components !== null) {
            $this->template = $components['template'];
            $this->templateLayout = $components['layout'];

            Extend::call('tpl.change', ['index' => $this]);

            return true;
        }

        return false;
    }

    /**
     * Set output to redirection
     */
    function redirect(string $url, bool $permanent = false): void
    {
        $this->type = self::REDIR;
        $this->redirectTo = $url;
        $this->redirectToPermanent = $permanent;
    }

    /**
     * Set output to a 404 page
     */
    function notFound(): void
    {
        $this->type = self::NOT_FOUND;
    }

    /**
     * Set output to a 403 page
     */
    function unauthorized(): void
    {
        $this->type = self::UNAUTHORIZED;
    }
}
