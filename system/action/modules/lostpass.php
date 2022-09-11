<?php

use Sunlight\Captcha;
use Sunlight\Core;
use Sunlight\Database\Database as DB;
use Sunlight\Email;
use Sunlight\GenericTemplates;
use Sunlight\IpLog;
use Sunlight\Message;
use Sunlight\Router;
use Sunlight\Settings;
use Sunlight\User;
use Sunlight\Util\Form;
use Sunlight\Util\Password;
use Sunlight\Util\Request;
use Sunlight\Util\StringGenerator;

defined('SL_ROOT') or exit;

if (!Settings::get('lostpass')) {
    $_index->notFound();
    return;
}

if (User::isLoggedIn()) {
    $_index->redirect(Router::module('login', ['absolute' => true]));
    return;
}

// output
$_index->title = _lang('mod.lostpass');

if (isset($_GET['user'], $_GET['hash'])) {
    // verify user and hash
    do {
        // check login attempt limit
        if (!IpLog::check(IpLog::FAILED_LOGIN_ATTEMPT)) {
            $output .= Message::error(_lang('login.attemptlimit', ['%max_attempts%' => Settings::get('maxloginattempts'), '%minutes%' => Settings::get('maxloginexpire') / 60]));
            break;
        }

        // user data
        $user = Request::get('user');
        $hash = Request::get('hash');
        $userdata = DB::queryRow('SELECT id,email,username,security_hash,security_hash_expires FROM ' . DB::table('user') . ' WHERE username=' . DB::val($user));
        if (
            $userdata === false
            || $hash !== $userdata['security_hash']
            || time() >= $userdata['security_hash_expires']
        ) {
            IpLog::update(IpLog::FAILED_LOGIN_ATTEMPT);
            $output .= Message::warning(_lang('mod.lostpass.badlink'));
            $output .= '<p><a href="' . _e(Router::module('lostpass')) . '">' . _lang('global.tryagain') . ' &gt;</a></p>';
            break;
        }

        // generate a new password and send it to user's e-mail
        $newpass = StringGenerator::generateString(12);
        $domain = Core::getBaseUrl()->getFullHost();

        if (!Email::send(
            $userdata['email'],
            _lang('mod.lostpass.mail.subject', ['%domain%' => $domain]),
            _lang('mod.lostpass.mail.text2', [
                '%domain%' => $domain,
                '%username%' =>  $userdata['username'],
                '%newpass%' => $newpass,
                '%date%' => GenericTemplates::renderTime(time()),
                '%ip%' => Core::getClientIp(),
            ])
        )) {
            $output .= Message::error(_lang('global.emailerror'));
            break;
        }

        // change password
        DB::update('user', 'id=' . DB::val($userdata['id']), [
            'password' => Password::create($newpass)->build(),
            'security_hash' => null,
            'security_hash_expires' => 0,
        ]);

        // all ok
        $output .= Message::ok(_lang('mod.lostpass.generated'));
    } while (false);
} else {
    // initiate reset attempt
    $output .= '<p class="bborder">' . _lang('mod.lostpass.p') . '</p>';

    // send e-mail
    $sent = false;

    if (isset($_POST['username'])) do {
        // check login attempt limit
        if (!IpLog::check(IpLog::PASSWORD_RESET_REQUESTED)) {
            $output .= Message::error(_lang('mod.lostpass.limit', ['%limit%' => Settings::get('lostpassexpire') / 60]));
            break;
        }

        // captcha
        if (!Captcha::check()) {
            $output .= Message::warning(_lang('captcha.failure2'));
            break;
        }

        // user data
        $username = Request::post('username');
        $email = Request::post('email');
        $userdata = DB::queryRow('SELECT id,email,username FROM ' . DB::table('user') . ' WHERE username=' . DB::val($username) . ' AND email=' . DB::val($email));
        if ($userdata === false) {
            $output .= Message::warning(_lang('mod.lostpass.notfound'));
            break;
        }

        // generate a hash
        $hash = StringGenerator::generateString(64);
        DB::update('user', 'id=' . DB::val($userdata['id']), [
            'security_hash' => $hash,
            'security_hash_expires' => time() + 3600,
        ]);

        // send e-mail
        $link = Router::module('lostpass', ['query' => ['id' => $username , 'hash' => $hash], 'absolute' => true]);
        $domain = Core::getBaseUrl()->getFullHost();

        if (!Email::send(
            $userdata['email'],
            _lang('mod.lostpass.mail.subject', ['%domain%' => $domain]),
            _lang('mod.lostpass.mail.text', [
                '%domain%' => $domain,
                '%username%' => $userdata['username'],
                '%link%' => $link,
                '%date%' => GenericTemplates::renderTime(time()),
                '%ip%' => Core::getClientIp(),
            ])
        )) {
            $output .= Message::error(_lang('global.emailerror'));
            break;
        }

        // all ok
        IpLog::update(IpLog::PASSWORD_RESET_REQUESTED);
        $output .= Message::ok(_lang('mod.lostpass.mailsent'));
        $sent = true;
    } while (false);

    // form
    if (!$sent) {
        $captcha = Captcha::init();

        $output .= Form::render(
            [
                'name' => 'lostpassform',
                'action' => Router::module('lostpass'),
            ],
            [
                ['label' => _lang('login.username'), 'content' => '<input type="text" class="inputsmall" maxlength="24"' . Form::restorePostValueAndName('username') . ' autocomplete="username">'],
                ['label' => _lang('global.email'), 'content' => '<input type="email" class="inputsmall" maxlength="191"' . Form::restorePostValueAndName('email', '@') . ' autocomplete="email">'],
                $captcha,
                Form::getSubmitRow(['text' => _lang('global.send')]),
            ]
        );
    }
}
