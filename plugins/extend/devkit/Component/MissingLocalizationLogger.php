<?php

namespace SunlightExtend\Devkit\Component;

use Sunlight\Localization\LocalizationDictionary;

class MissingLocalizationLogger
{
    /** @var \SplObjectStorage dict => array(key1 => count1, ...) */
    private $missingEntries;

    public function __construct()
    {
        $this->missingEntries = new \SplObjectStorage();
    }

    /**
     * @param LocalizationDictionary $dict
     * @param string                 $key
     */
    public function log(LocalizationDictionary $dict, string $key): void
    {
        if (!isset($this->missingEntries[$dict])) {
            $this->missingEntries[$dict] = [];
        }

        $missingEntries = $this->missingEntries[$dict];

        if (!isset($missingEntries[$key])) {
            $missingEntries[$key] = 0;
        }

        ++$missingEntries[$key];

        $this->missingEntries[$dict] = $missingEntries;
    }

    /**
     * @return \SplObjectStorage
     */
    public function getMissingEntries(): \SplObjectStorage
    {
        return $this->missingEntries;
    }
}
