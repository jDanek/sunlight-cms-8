<?php

namespace Sunlight;

use Kuria\Cache\Cache;
use Kuria\Cache\Driver\Filesystem\Entry\EntryFactory;
use Kuria\Cache\Driver\Filesystem\FilesystemDriver;
use Kuria\Cache\Driver\Memory\MemoryDriver;
use Kuria\ClassLoader\ClassLoader;
use Kuria\Debug\Exception;
use Kuria\Error\ErrorHandler;
use Kuria\Error\Screen\WebErrorScreen;
use Kuria\Error\Screen\WebErrorScreenEvents;
use Kuria\Event\EventEmitter;
use Sunlight\Database\Database as DB;
use Sunlight\Exception\CoreException;
use Sunlight\Localization\LocalizationDictionary;
use Sunlight\Plugin\PluginManager;
use Sunlight\Util\DateTime;
use Sunlight\Util\Environment;
use Sunlight\Util\Filesystem;
use Sunlight\Util\Response;
use Sunlight\Util\Url;

/**
 * Main system singleton
 *
 * Manages core components and configuration.
 */
abstract class Core
{
    /** CMS version */
    const VERSION = '8.0.0';
    /** CMS distribution type */
    const DIST = 'GIT'; // GIT / STABLE / BETA
    /** Web environment (frontend - index.php) */
    const ENV_WEB = 'web';
    /** Administration environment (backend) */
    const ENV_ADMIN = 'admin';
    /** Script environment */
    const ENV_SCRIPT = 'script';

    /** @var float */
    static $start;
    /** @var string */
    static $appId;
    /** @var string */
    static $secret;
    /** @var string */
    static $url;
    /** @var string */
    static $fallbackLang;
    /** @var bool */
    static $sessionEnabled;
    /** @var bool */
    static $sessionRegenerate;
    /** @var string|null */
    static $sessionPreviousId;

    /** @var ClassLoader */
    static $classLoader;
    /** @var ErrorHandler */
    static $errorHandler;
    /** @var EventEmitter */
    static $eventEmitter;
    /** @var PluginManager */
    static $pluginManager;
    /** @var Cache */
    static $cache;
    /** @var LocalizationDictionary */
    static $lang;

    /** @var array|null */
    static $userData;
    /** @var array|null */
    static $groupData;

    /** @var array */
    static $dangerousServerSideExt = ['php', 'php3', 'php4', 'php5', 'phtml', 'shtml', 'asp', 'py', 'cgi', 'htaccess'];
    /** @var array */
    static $imageExt = ['png', 'jpeg', 'jpg', 'gif'];
    /** @var string */
    static $imageError;
    /** @var int */
    static $hcmUid = 0;
    /** @var array */
    static $settings = [];
    /** @var array id => seconds */
    static $cronIntervals = [];

    /** @var bool */
    protected static $ready = false;

    /**
     * Initialize the system
     *
     * Supported $options keys:
     * ------------------------
     * config_file          path to the configuration file, null (= default) or false (= skip)
     * minimal_mode         stop after initializing base components and environment (= no plugins, db, settings, session, etc.) 1/0
     * skip_components      do not initalize any components 1/0
     * session_enabled      initialize session 1/0
     * session_regenerate   force new session ID 1/0
     * allow_cron_auto      allow running cron tasks automatically 1/0
     * content_type         content type, FALSE = disabled (default is "text/html; charset=UTF-8")
     * env                  environment identifier, see Core::ENV_* constants
     *
     * @param string $root relative path to the system root directory (with a trailing slash)
     * @param array  $options
     */
    static function init(string $root, array $options = []): void
    {
        if (static::$ready) {
            throw new \LogicException('Cannot init multiple times');
        }

        static::$start = microtime(true);
        $initComponents = empty($options['skip_components']);

        // functions
        require __DIR__ . '/../functions.php';

        // base components
        if ($initComponents) {
            static::initBaseComponents();
        }

        // configuration
        static::initConfiguration($root, $options);

        // constants
        require __DIR__ . '/../constants.php';

        // components
        if ($initComponents) {
            static::initComponents($options);
        }

        // environment
        static::initEnvironment($options);

        // resolve URL
        static::$url = static::resolveUrl($options['url']);

        // stop when minimal mode is enabled
        if ($options['minimal_mode']) {
            static::$ready = true;

            return;
        }

        // first init phase
        static::initDatabase($options);
        static::initPlugins();
        static::initSettings();

        // check system phase
        static::checkSystemState();

        // second init phase
        static::initSession();
        static::initLocalization();

        // finalize
        static::$ready = true;
        Extend::call('core.ready');

        // cron tasks
        Extend::reg('cron.maintenance', [__CLASS__, 'doMaintenance']);
        if (_cron_auto && $options['allow_cron_auto']) {
            static::runCronTasks();
        }
    }

    /**
     * Initialize configuration
     *
     * @param string $root
     * @param array  &$options
     */
    protected static function initConfiguration(string $root, array &$options): void
    {
        // defaults
        $options += [
            'config_file' => null,
            'minimal_mode' => false,
            'session_enabled' => true,
            'session_regenerate' => false,
            'content_type' => null,
            'allow_cron_auto' => isset($options['env']) && $options['env'] !== static::ENV_SCRIPT,
            'env' => static::ENV_SCRIPT,
        ];

        // load config file
        $configFile = $options['config_file'] ?? $root . 'config.php';

        if ($configFile !== false) {
            $configFileOptions = @include $configFile;

            if ($configFileOptions === false) {
                static::systemFailure(
                    'Chybí soubor "config.php". Otevřete /install pro instalaci.',
                    'The "config.php" file is missing. Open /install to create it.'
                );
            }

            $options += $configFileOptions;
        }

        // config defaults
        $options += [
            'db.server' => null,
            'db.port' => null,
            'db.user' => null,
            'db.password' => null,
            'db.name' => null,
            'db.prefix' => null,
            'url' => '',
            'secret' => null,
            'app_id' => null,
            'fallback_lang' => 'en',
            'debug' => false,
            'cache' => true,
            'locale' => null,
            'timezone' => 'Europe/Prague',
            'geo.latitude' => 50.5,
            'geo.longitude' => 14.26,
            'geo.zenith' => 90.583333,
        ];

        // check required options
        if (!$options['minimal_mode']) {
            $requiredOptions = [
                'db.server',
                'db.name',
                'db.prefix',
                'secret',
                'app_id',
            ];

            foreach ($requiredOptions as $requiredOption) {
                if (empty($options[$requiredOption])) {
                    static::systemFailure(
                        "Konfigurační volba \"{$requiredOption}\" nesmí být prázdná.",
                        "The configuration option \"{$requiredOption}\" must not be empty."
                    );
                }
            }
        }

        // define variables
        static::$appId = $options['app_id'];
        static::$secret = $options['secret'];
        static::$fallbackLang = $options['fallback_lang'];
        static::$sessionEnabled = $options['session_enabled'];
        static::$sessionRegenerate = $options['session_regenerate'] || isset($_POST['_session_force_regenerate']);
        static::$imageError = $root . 'system/image_error.png';

        // define constants
        define('_root', $root);
        define('_env', $options['env']);
        define('_debug', (bool) $options['debug']);
        define('_dbprefix', $options['db.prefix'] . '_');
        define('_dbname', $options['db.name']);
        define('_upload_dir', _root . 'upload/');
        define('_geo_latitude', $options['geo.latitude']);
        define('_geo_longitude', $options['geo.longitude']);
        define('_geo_zenith', $options['geo.zenith']);
    }

    /**
     * @param string $url
     * @return string
     */
    protected static function resolveUrl(string $url): string
    {
        $baseUrl = Url::parse($url);
        $currentUrl = Url::current();

        // set missing base absolute URL components from the current URL
        if ($baseUrl->scheme === null) {
            $baseUrl->scheme = $currentUrl->scheme;
        }

        if ($baseUrl->host === null) {
            $baseUrl->host = $currentUrl->host;
        }

        if ($baseUrl->port === null) {
            $baseUrl->port = $currentUrl->port;
        }

        return $baseUrl->generateAbsolute();
    }

    /**
     * Init base components that don't depend on configuration
     */
    protected static function initBaseComponents(): void
    {
        // class loader
        if (static::$classLoader === null) {
            static::$classLoader = new ClassLoader();
        }

        // error handler
        static::$errorHandler = new ErrorHandler();
        static::$errorHandler->register();

        if (($exceptionHandler = static::$errorHandler->getErrorScreen()) instanceof WebErrorScreen) {
            static::configureWebExceptionHandler($exceptionHandler);
        }

        // event emitter
        static::$eventEmitter = new EventEmitter();
    }

    /**
     * Initialize components
     *
     * @param array $options
     */
    protected static function initComponents(array $options): void
    {
        // class loader
        static::$classLoader->setDebug(_debug);

        // error handler
        static::$errorHandler->setDebug(_debug || 'cli' === PHP_SAPI);

        // cache
        if (static::$cache === null) {
            static::$cache = new Cache(
                $options['cache']
                    ? new FilesystemDriver(
                        _root . 'system/cache/core',
                        new EntryFactory(null, null, _root . 'system/tmp')
                    )
                    : new MemoryDriver()
            );
        }

        // plugin manager
        static::$pluginManager = new PluginManager(
            static::$cache->getNamespace('plugins.')
        );

        // localization
        static::$lang = new LocalizationDictionary();
    }

    /**
     * Initialize database
     *
     * @param array $options
     */
    protected static function initDatabase(array $options): void
    {
        $connectError = DB::connect($options['db.server'], $options['db.user'], $options['db.password'], $options['db.name'], $options['db.port']);

        if ($connectError !== null) {
            static::systemFailure(
                'Připojení k databázi se nezdařilo. Důvodem je pravděpodobně výpadek serveru nebo chybné přístupové údaje. Zkontrolujte přístupové údaje v souboru config.php.',
                'Could not connect to the database. This may have been caused by the database server being temporarily unavailable or an error in the configuration. Check your config.php file for errors.',
                null,
                $connectError
            );
        }
    }

    /**
     * Initialize settings
     */
    protected static function initSettings(): void
    {
        // fetch from database
        if (_env === static::ENV_ADMIN) {
            $preloadCond = 'admin=1';
        } else {
            $preloadCond = 'web=1';
        }

        $query = DB::query('SELECT var,val,constant FROM ' . _setting_table . ' WHERE preload=1 AND ' . $preloadCond, true);

        if (DB::error()) {
            static::systemFailure(
                'Připojení k databázi proběhlo úspěšně, ale dotaz na databázi selhal. Zkontrolujte, zda je databáze správně nainstalovaná.',
                'Successfully connected to the database, but the database query has failed. Make sure the database is installed correctly.',
                null,
                DB::error()
            );
        }

        $settings = DB::rows($query, 'var');
        DB::free($query);

        // event
        Extend::call('core.settings', ['settings' => &$settings]);

        // apply settings
        foreach ($settings as $setting) {
            if ($setting['constant']) {
                define("_{$setting['var']}", $setting['val']);
            } else {
                static::$settings[$setting['var']] = $setting['val'];
            }
        }

        // define maintenance cron interval
        static::$cronIntervals['maintenance'] = _maintenance_interval;

        // determine client IP address
        if (empty($_SERVER['REMOTE_ADDR'])) {
            $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        }
        if (_proxy_mode && isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        define('_user_ip', trim((($addr_comma = strrpos($ip, ',')) === false) ? $ip : substr($ip, $addr_comma + 1)));
    }

    /**
     * Check system state after initialization
     */
    protected static function checkSystemState(): void
    {
        // check database version
        if (!defined('_dbversion') || Core::VERSION !== _dbversion) {
            static::systemFailure(
                'Verze nainstalované databáze není kompatibilní s verzí systému.',
                'Database version is not compatible with the current system version.'
            );
        }

        // installation check
        if (_install_check) {
            $systemChecker = new SystemChecker();
            $systemChecker->check();

            if ($systemChecker->hasErrors()) {
                static::systemFailure(
                    'Při kontrole instalace byly detekovány následující problémy:',
                    'The installation check has detected the following problems:',
                    null,
                    $systemChecker->renderErrors()
                );
            }

            static::updateSetting('install_check', 0);
        }

        // verify current URL
        if (!Environment::isCli()) {
            $currentUrl = Url::current();
            $baseUrl = Url::base();

            if ($currentUrl->host !== $baseUrl->host || $currentUrl->scheme !== $baseUrl->scheme) {
                // invalid hostname or scheme
                $currentUrl->host = $baseUrl->host;
                $currentUrl->scheme = $baseUrl->scheme;
                Response::redirect($currentUrl->generateAbsolute());
                exit;
            }

            if ($currentUrl->scheme !== $baseUrl->scheme) {
                // invalid protocol
                $currentUrl->scheme = $baseUrl->scheme;
                Response::redirect($currentUrl->generateAbsolute());
                exit;
            }
        }
    }

    /**
     * Initialize environment
     *
     * @param array $options
     */
    protected static function initEnvironment(array $options): void
    {
        // ensure correct encoding for mb_*() functions
        mb_internal_encoding('UTF-8');

        // make sure $_SERVER['REQUEST_URI'] is defined
        if (!isset($_SERVER['REQUEST_URI'])) {
            $requestUri = $_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'];
            if (!empty($_SERVER['QUERY_STRING'])) {
                $requestUri .= '?' . $_SERVER['QUERY_STRING'];
            }
            $_SERVER['REQUEST_URI'] = $requestUri;
        }

        // set error_reporting
        $err_rep = E_ALL | E_STRICT;
        if (!_debug) {
            $err_rep &= ~(E_NOTICE | E_USER_NOTICE | E_DEPRECATED | E_STRICT);
        }
        error_reporting($err_rep);

        // set locale
        if (!empty($options['locale'])) {
            @setlocale(LC_TIME, $options['locale']);
        }
        if (!empty($options['timezone'])) {
            date_default_timezone_set($options['timezone']);
        }

        // send default headers
        if (!Environment::isCli()) {
            if ($options['content_type'] === null) {
                // vychozi hlavicky
                header('Content-Type: text/html; charset=UTF-8');
                header('Expires: ' . DateTime::formatForHttp(-604800, true));
            } elseif ($options['content_type'] !== false) {
                header('Content-Type: ' . $options['content_type']);
            }
        }
    }

    /**
     * Initialize plugins
     */
    protected static function initPlugins(): void
    {
        foreach (static::$pluginManager->getAllExtends() as $extendPlugin) {
            $extendPlugin->initialize();
        }

        Extend::call('plugins.ready');
    }

    /**
     * Initialize session
     */
    protected static function initSession(): void
    {
        // start session
        if (static::$sessionEnabled) {
            // cookie parameters
            $cookieParams = session_get_cookie_params();
            $cookieParams['httponly'] = 1;
            $cookieParams['secure'] = Url::current()->scheme === 'https' ? 1 : 0;
            session_set_cookie_params($cookieParams['lifetime'], $cookieParams['path'], $cookieParams['domain'], $cookieParams['secure'], $cookieParams['httponly']);

            // set session name and start it
            session_name(static::$appId . '_session');
            session_start();

            if (static::$sessionRegenerate) {
                static::$sessionPreviousId = session_id();
                session_regenerate_id(true);
            }
        } else {
            // no session
            $_SESSION = [];
        }

        // authorization process
        $authorized = false;
        $isPersistentLogin = false;
        $errorCode = null;

        if (static::$sessionEnabled) do {
            $userData = null;
            $loginDataExist = isset($_SESSION['user_id'], $_SESSION['user_auth']);

            // check persistent login cookie if there are no login data
            if (!$loginDataExist) {
                // check cookie existence
                $persistentCookieName = static::$appId . '_persistent_key';
                if (isset($_COOKIE[$persistentCookieName]) && is_string($_COOKIE[$persistentCookieName])) {
                    // cookie authorization process
                    do {
                        // parse cookie
                        $cookie = explode('$', $_COOKIE[$persistentCookieName], 2);
                        if (count($cookie) !== 2) {
                            // invalid cookie format
                            $errorCode = 1;
                            break;
                        }
                        $cookie = [
                            'id' => (int) $cookie[0],
                            'hash' => $cookie[1],
                        ];

                        // fetch user data
                        $userData = DB::queryRow('SELECT * FROM ' . _user_table . ' WHERE id=' . DB::val($cookie['id']));
                        if ($userData === false) {
                            // user not found
                            $errorCode = 2;
                            break;
                        }

                        // check failed login attempt limit
                        if (!IpLog::check(_iplog_failed_login_attempt)) {
                            // limit exceeded
                            $errorCode = 3;
                            break;
                        }

                        $validHash = User::getPersistentLoginHash($cookie['id'], User::getAuthHash($userData['password']), $userData['email']);
                        if ($validHash !== $cookie['hash']) {
                            // invalid hash
                            IpLog::update(_iplog_failed_login_attempt);
                            $errorCode = 4;
                            break;
                        }

                        // all is well! use cookie data to login the user
                        User::login($cookie['id'], $userData['password'], $userData['email']);
                        $loginDataExist = true;
                        $isPersistentLogin = true;
                    } while (false);

                    // check result
                    if ($errorCode !== null) {
                        // cookie authoriation has failed, remove the cookie
                        setcookie(static::$appId . '_persistent_key', '', (time() - 3600), '/');
                        break;
                    }
                }
            }

            // check whether login data exist
            if (!$loginDataExist) {
                // no login data - user is not logged in
                $errorCode = 5;
                break;
            }

            // fetch user data
            if (!$userData) {
                $userData = DB::queryRow('SELECT * FROM ' . _user_table . ' WHERE id=' . DB::val($_SESSION['user_id']));
                if ($userData === false) {
                    // user not found
                    $errorCode = 6;
                    break;
                }
            }

            // check user authentication hash
            if ($_SESSION['user_auth'] !== User::getAuthHash($userData['password'])) {
                // neplatny hash
                $errorCode = 7;
                break;
            }

            // check user account's status
            if ($userData['blocked']) {
                // account is blocked
                $errorCode = 8;
                break;
            }

            // fetch group data
            $groupData = DB::queryRow('SELECT * FROM ' . _user_group_table . ' WHERE id=' . DB::val($userData['group_id']));
            if ($groupData === false) {
                // group data not found
                $errorCode = 9;
                break;
            }

            // check group status
            if ($groupData['blocked']) {
                // group is blocked
                $errorCode = 10;
                break;
            }

            // all is well! user is authorized
            $authorized = true;
        } while (false);

        // process login
        if ($authorized) {
            // increase level for super users
            if ($userData['levelshift']) {
                $groupData['level'] += 1;
            }

            // record activity time (max once per 30 seconds)
            if (time() - $userData['activitytime'] > 30) {
                DB::update(_user_table, 'id=' . DB::val($userData['id']), [
                    'activitytime' => time(),
                    'ip' => _user_ip,
                ]);
            }

            // event
            Extend::call('user.auth.success', [
                'user' => &$userData,
                'group' => &$groupData,
                'persistent_session' => $isPersistentLogin,
            ]);

            // set variables
            static::$userData = $userData;
            static::$groupData = $groupData;
        } else {
            // anonymous user
            $userData = [
                'id' => -1,
                'username' => '',
                'publicname' => null,
                'email' => '',
                'levelshift' => false,
            ];

            // fetch anonymous group data
            $groupData = DB::queryRow('SELECT * FROM ' . _user_group_table . ' WHERE id=' . _group_guests);
            if ($groupData === false) {
                throw new \RuntimeException(sprintf('Anonymous user group was not found (id=%s)', _group_guests));
            }

            // event
            Extend::call('user.auth.failure', [
                'error_code' => $errorCode,
                'user' => &$userData,
                'group' => &$groupData,
            ]);
        }

        // define constants
        define('_logged_in', $authorized);
        define('_user_id', $userData['id']);
        define('_user_name', $userData['username']);
        define('_user_public_name', $userData[$userData['publicname'] !== null ? 'publicname' : 'username']);
        define('_user_email', $userData['email']);
        define('_user_group', $groupData['id']);

        foreach(User::listPrivileges() as $item) {
            define('_priv_' . $item, $groupData[$item]);
        }

        define('_priv_super_admin', $userData['levelshift'] && $groupData['id'] == _group_admin);
    }

    /**
     * Initialize localization
     */
    protected static function initLocalization(): void
    {
        // language choice
        if (_logged_in && _language_allowcustom && static::$userData['language'] !== '') {
            $language = static::$userData['language'];
            $usedLoginLanguage = true;
        } else {
            $language = static::$settings['language'];
            $usedLoginLanguage = false;
        }

        // load language plugin
        if (static::$pluginManager->has(PluginManager::LANGUAGE, $language)) {
            $languagePlugin = static::$pluginManager->getLanguage($language);
        } else {
            $languagePlugin = null;
        }

        if ($languagePlugin !== null) {
            // load localization entries from the plugin
            $entries = $languagePlugin->getLocalizationEntries();

            if ($entries !== false) {
                static::$lang->add($entries);
            }
        } else {
            // language plugin was not found
            if ($usedLoginLanguage) {
                DB::update(_user_table, 'id=' . _user_id, ['language' => '']);
            } else {
                static::updateSetting('language', static::$fallbackLang);
            }

            static::systemFailure(
                'Jazykový balíček "%s" nebyl nalezen.',
                'Language plugin "%s" was not found.',
                [$language]
            );
        }

        define('_language', $language);
    }

    /**
     * Run CRON tasks
     */
    static function runCronTasks(): void
    {
        $cronNow = time();
        $cronUpdate = false;
        $cronLockFileHandle = null;
        if (static::$settings['cron_times']) {
            $cronTimes = unserialize(static::$settings['cron_times']);
        } else {
            $cronTimes = false;
        }
        if ($cronTimes === false) {
            $cronTimes = [];
            $cronUpdate = true;
        }

        foreach (static::$cronIntervals as $cronIntervalName => $cronIntervalSeconds) {
            if (isset($cronTimes[$cronIntervalName])) {
                // last run time is known
                if ($cronNow - $cronTimes[$cronIntervalName] >= $cronIntervalSeconds) {
                    // check lock file
                    if ($cronLockFileHandle === null) {
                        $cronLockFile = _root . 'system/cron.lock';
                        $cronLockFileHandle = fopen($cronLockFile, 'r');
                        if (!flock($cronLockFileHandle, LOCK_EX | LOCK_NB)) {
                            // lock soubor je nepristupny
                            fclose($cronLockFileHandle);
                            $cronLockFileHandle = null;
                            $cronUpdate = false;
                            break;
                        }
                    }

                    // event
                    $cronEventArgs = [
                        'last' => $cronTimes[$cronIntervalName],
                        'name' => $cronIntervalName,
                        'seconds' => $cronIntervalSeconds,
                        'delay' => $cronNow - $cronTimes[$cronIntervalName],
                    ];
                    Extend::call('cron', $cronEventArgs);
                    Extend::call('cron.' . $cronIntervalName, $cronEventArgs);

                    // update last run time
                    $cronTimes[$cronIntervalName] = $cronNow;
                    $cronUpdate = true;
                }
            } else {
                // unknown last run time
                $cronTimes[$cronIntervalName] = $cronNow;
                $cronUpdate = true;
            }
        }

        // update run times
        if ($cronUpdate) {
            // remove unknown intervals
            foreach (array_keys($cronTimes) as $cronTimeKey) {
                if (!isset(static::$cronIntervals[$cronTimeKey])) {
                    unset($cronTimes[$cronTimeKey]);
                }
            }

            // save
            static::updateSetting('cron_times', serialize($cronTimes));
        }

        // free lock file
        if ($cronLockFileHandle !== null) {
            flock($cronLockFileHandle, LOCK_UN);
            fclose($cronLockFileHandle);
            $cronLockFileHandle = null;
        }
    }

    /**
     * @return bool
     */
    static function isReady(): bool
    {
        return static::$ready;
    }

    /**
     * Run system maintenance
     */
    static function doMaintenance(): void
    {
        // clean thumbnails
        Picture::cleanThumbnails(_thumb_cleanup_threshold);

        // remove old files in the temporary directory
        Filesystem::purgeDirectory(_root . 'system/tmp', [
            'keep_dir' => true,
            'files_only' => true,
            'file_callback' => function (\SplFileInfo $file) {
                // posledni zmena souboru byla pred vice nez 24h
                // a nejedna se o skryty soubor
                return
                    substr($file->getFilename(), 0, 1) !== '.'
                    && time() - $file->getMTime() > 86400;
            },
        ]);

        // check version
        VersionChecker::check();
    }

    /**
     * Load a single setting directive
     *
     * @param string $name
     * @param mixed  $default
     * @return mixed
     */
    static function loadSetting(string $name, $default = null)
    {
        $result = DB::queryRow('SELECT val FROM ' . _setting_table . ' WHERE var=' . DB::val($name));
        if ($result !== false) {
            return $result['val'];
        } else {
            return $default;
        }
    }

    /**
     * Load multiple setting directives
     *
     * @param string|string[] $names
     * @return array
     */
    static function loadSettings(array $names): array
    {
        $names = (array) $names;

        $settings = [];
        $query = DB::query('SELECT var,val FROM ' . _setting_table . ' WHERE var IN(' . DB::arr($names) . ')');
        while ($row = DB::row($query)) {
            $settings[$row['var']] = $row['val'];
        }

        return $settings;
    }

    /**
     * Load setting directives by type
     *
     * @param string|null $type
     * @return array
     */
    static function loadSettingsByType(?string $type): array
    {
        $settings = [];
        $query = DB::query('SELECT var,val FROM ' . _setting_table . ' WHERE type' . ($type === null ? ' IS NULL' : '=' . DB::val($type)));
        while ($row = DB::row($query)) {
            $settings[$row['var']] = $row['val'];
        }

        return $settings;
    }

    /**
     * Update setting directive value
     *
     * @param string $name
     * @param string $newValue
     */
    static function updateSetting(string $name, string $newValue): void
    {
        DB::update(_setting_table, 'var=' . DB::val($name), ['val' => (string) $newValue]);
    }

    /**
     * Get global JavaScript definitions
     *
     * @param array $customVariables asociativni pole s vlastnimi promennymi
     * @param bool  $scriptTags      obalit do <script> tagu 1/0
     * @return string
     */
    static function getJavascript(array $customVariables = [], bool $scriptTags = true): string
    {
        $output = '';

        // opening script tag
        if ($scriptTags) {
            $output .= "<script>";
        }

        // prepare variables
        $variables = [
            'basePath' => Url::base()->path . '/',
            'currentTemplate' => Template::getCurrent()->getId(),
            'labels' => [
                'alertConfirm' => _lang('javascript.alert.confirm'),
                'loading' => _lang('javascript.loading'),
            ],
            'settings' => [
                'atReplace' => _atreplace,
            ],
        ];
        if (!empty($customVariables)) {
            $variables = array_merge_recursive($variables, $customVariables);
        }
        Extend::call('core.javascript', ['variables' => &$variables]);

        // output variables
        $output .= 'var SunlightVars = ' . json_encode($variables) . ';';

        // closing script tags
        if ($scriptTags) {
            $output .= '</script>';
        }

        return $output;
    }

    /**
     * Throw a localized core exception
     *
     * @param string      $msgCs    zprava cesky
     * @param string      $msgEn    zprava anglicky
     * @param array|null  $msgArgs  argumenty sprintf() formatovani
     * @param string|null $msgExtra extra obsah pod zpravou (nelokalizovany)
     * @throws CoreException
     */
    static function systemFailure(string $msgCs, string $msgEn, ?array $msgArgs = null, ?string $msgExtra = null): void
    {
        $messages = [];

        if (static::$fallbackLang === 'cs' || empty(static::$fallbackLang)) {
            $messages[] = !empty($msgArgs) ? vsprintf($msgCs, $msgArgs) : $msgCs;
        }

        if (static::$fallbackLang !== 'cs' || empty(static::$fallbackLang)) {
            $messages[] = !empty($msgArgs) ? vsprintf($msgEn, $msgArgs) : $msgEn;
        }

        if (!empty($msgExtra)) {
            $messages[] = $msgExtra;
        }

        throw new CoreException(implode("\n\n", $messages));
    }

    /**
     * Render an exception
     *
     * @param \Throwable $e
     * @param bool $showTrace
     * @param bool $showPrevious
     * @return string
     */
    static function renderException($e, bool $showTrace = true, bool $showPrevious = true): string
    {
        return '<pre class="exception">' . _e(Exception::render($e, $showTrace, $showPrevious)) . "</pre>\n";
    }

    protected static function configureWebExceptionHandler(WebErrorScreen $errorScreen): void
    {
        $errorScreen->on(WebErrorScreenEvents::CSS, function () {
            echo <<<'CSS'
body {background-color: #ededed; color: #000000;}
a {color: #ff6600;}
.core-exception-info {opacity: 0.8;}
.website-link {display: block; margin: 1em 0; text-align: center; color: #000000; opacity: 0.5;}
CSS;
        });


        if (!static::$errorHandler->isDebugEnabled()) {
            $errorScreen->on(WebErrorScreenEvents::RENDER, function ($view) {
                $view['title'] = $view['heading'] = Core::$fallbackLang === 'cs'
                    ? 'Chyba serveru'
                    : 'Something went wrong';

                $view['text'] = Core::$fallbackLang === 'cs'
                    ? 'Omlouváme se, ale při zpracovávání Vašeho požadavku došlo k neočekávané chybě.'
                    : 'We are sorry, but an unexpected error has occurred while processing your request.';

                if ($view['exception'] instanceof CoreException) {
                    $view['extras'] .= '<div class="group core-exception-info"><div class="section">';
                    $view['extras'] .=  '<p class="message">' . nl2br(_e($view['exception']->getMessage()), false) . '</p>';
                    $view['extras'] .= '</div></div>';
                    $view['extras'] .= '<a class="website-link" href="https://sunlight-cms.cz/" target="_blank">SunLight CMS ' . Core::VERSION . '</a>';
                }
            });
        }
    }
}
