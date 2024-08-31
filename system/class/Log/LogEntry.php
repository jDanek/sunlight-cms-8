<?php

namespace Sunlight\Log;

class LogEntry
{
    /** @var string|int entry ID */
    public string|int $id;
    public int $level;
    public string $category;
    /** @var string time when the entry was made (UNIX timestamp with microseconds) */
    public string $time;
    /** @var string the log message */
    public string $message;
    /** @var string|null current request method */
    public ?string $method = null;
    /** @var string|null current URL */
    public ?string $url = null;
    /** @var string|null client IP address */
    public ?string $ip = null;
    /** @var string|null user-agent string */
    public ?string $userAgent = null;
    /** @var int|null ID of logged-in user when the entry was logged */
    public ?int $userId = null;
    /** @var string|null JSON data or NULL */
    public ?string $context = null;

    function getDateTime(): \DateTime
    {
        return \DateTime::createFromFormat('U.u', $this->time)
            ->setTimezone(new \DateTimeZone(date_default_timezone_get()));
    }
}
