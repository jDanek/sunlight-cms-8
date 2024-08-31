<?php

namespace Sunlight\Action;

use Sunlight\GenericTemplates;
use Sunlight\Message;

abstract class Action
{
    private bool $catchExceptions = false;
    private bool $renderExceptions = false;

    /**
     * Set whether exceptions should be catched
     */
    function setCatchExceptions(bool $catchExceptions): void
    {
        $this->catchExceptions = $catchExceptions;
    }

    /**
     * Set whether exceptions should be rendered
     */
    function setRenderExceptions(bool $renderExceptions): void
    {
        $this->renderExceptions = $renderExceptions;
    }

    /**
     * Run the action
     */
    function run(): ActionResult
    {
        try {
            $result = $this->execute();
        } catch (\Throwable $e) {
            if ($this->catchExceptions) {
                $result = ActionResult::failure(Message::error(_lang('global.error')));

                if ($this->renderExceptions) {
                    $result->setOutput(GenericTemplates::renderException($e));
                }
            } else {
                throw $e;
            }
        }

        return $result;
    }

    /**
     * Execute the action
     */
    abstract protected function execute(): ActionResult;
}
