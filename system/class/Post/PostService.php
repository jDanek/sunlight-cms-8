<?php

namespace Sunlight\Post;

use Sunlight\Captcha;
use Sunlight\Core;
use Sunlight\Database\Database as DB;
use Sunlight\Extend;
use Sunlight\GenericTemplates;
use Sunlight\Message;
use Sunlight\Paginator;
use Sunlight\PostForm;
use Sunlight\Router;
use Sunlight\Settings;
use Sunlight\Template;
use Sunlight\User;
use Sunlight\Util\Form;
use Sunlight\Util\Request;
use Sunlight\Util\StringHelper;
use Sunlight\Util\UrlHelper;

class PostService
{
    /**
     * Section comments
     *
     * $vars: (bool) locked
     */
    const RENDER_SECTION_COMMENTS = 1;

    /**
     * Article comments
     *
     * $vars: (bool) locked
     */
    const RENDER_ARTICLE_COMMENTS = 2;

    /**
     * Book posts
     *
     * $vars: [
     *      (int) posts per page,
     *      (bool) allow posting,
     *      (bool) locked,
     * ]
     */
    const RENDER_BOOK_POSTS = 3;

    /**
     * Forum topic list
     *
     * $vars: [
     *      (int) posts per page,
     *      (bool) allow posting,
     *      (bool) locked,
     *      (string) forum slug],
     * ]
     */
    const RENDER_FORUM_TOPIC_LIST = 5;

    /**
     * Forum topic
     *
     * $vars: [
     *      (int) posts per page,
     *      (bool) allow posting,
     *      (bool) locked,
     *      (int) topic id,
     * ]
     */
    const RENDER_FORUM_TOPIC = 6;

    /**
     * Private message list
     *
     * $vars: [
     *      (bool) locked,
     *      (int) unread count,
     * ]
     */
    const RENDER_PM_LIST = 7;

    /**
     * Plugin posts
     *
     * $vars: [
     *      (int) posts per page,
     *      (bool) allow posting,
     *      (bool) locked,
     *      (string) plugin flag,
     *      (bool) desc order,
     *      (?string) title,
     *      (?string) pager param name,
     * ]
     */
    const RENDER_PLUGIN_POSTS = 8;

    /**
     * Render post list
     *
     * @param int $style rendering style (see CommentService::RENDER_* constants)
     * @param int $home home ID (depends on underlying post type)
     * @param mixed $vars rendering options
     * @param bool $force_locked force locked state 1/0
     * @param string|null $url custom URL or null (= automatic)
     */
    static function renderList(int $style, int $home, mixed $vars, bool $force_locked = false, ?string $url = null): string
    {
        global $_index;

        // defaults
        $desc = 'DESC ';
        $ordercol = 'id';
        $countcond = 'type=' . $style . ' AND xhome=-1 AND home=' . $home;
        $locked_textid = '';
        $auto_last = false;
        $pluginflag = null;
        $subject_enabled = false;
        $form_position = 0;
        $page_param = null;
        $is_topic_list = ($style == self::RENDER_FORUM_TOPIC_LIST);
        $replies_enabled = null;
        $unread_count = null;

        // url
        if (!isset($url)) {
            $url = $_index->url;
        }

        $url_html = _e($url);

        switch ($style) {
            case self::RENDER_SECTION_COMMENTS:
                $posttype = Post::SECTION_COMMENT;
                $xhome = -1;
                $subclass = 'comments';
                $title = _lang('posts.comments');
                $addlink = _lang('posts.addcomment');
                $nopostsmessage = _lang('posts.nocomments');
                $postsperpage = Settings::get('commentsperpage');
                $canpost = User::hasPrivilege('postcomments');
                $locked = (bool) $vars;
                $replynote = true;
                break;

            case self::RENDER_ARTICLE_COMMENTS:
                $posttype = Post::ARTICLE_COMMENT;
                $xhome = -1;
                $subclass = 'comments';
                $title = _lang('posts.comments');
                $addlink = _lang('posts.addcomment');
                $nopostsmessage = _lang('posts.nocomments');
                $postsperpage = Settings::get('commentsperpage');
                $canpost = User::hasPrivilege('postcomments');
                $locked = (bool) $vars;
                $replynote = true;
                break;

            case self::RENDER_BOOK_POSTS:
                $posttype = Post::BOOK_ENTRY;
                $xhome = -1;
                $subclass = 'book';
                $title = null;
                $addlink = _lang('posts.addpost');
                $nopostsmessage = _lang('posts.noposts');
                $postsperpage = $vars[0];
                $canpost = $vars[1];
                $locked = (bool) $vars[2];
                $replynote = true;
                break;

            case self::RENDER_FORUM_TOPIC_LIST:
                $posttype = Post::FORUM_TOPIC;
                $xhome = -1;
                $subclass = 'topic';
                $title = null;
                $addlink = _lang('posts.addtopic');
                $nopostsmessage = _lang('posts.notopics');
                $postsperpage = $vars[0];
                $canpost = $vars[1];
                $locked = (bool) $vars[2];
                $replynote = true;
                $ordercol = 'bumptime';
                $locked_textid = '3';
                $forum_slug = $vars[3];
                $subject_enabled = true;
                break;

            case self::RENDER_FORUM_TOPIC:
                $posttype = Post::FORUM_TOPIC;
                $xhome = $vars[3];
                $subclass = 'topic-replies';
                $title = null;
                $addlink = _lang('posts.addanswer');
                $nopostsmessage = _lang('posts.noanswers');
                $postsperpage = $vars[0];
                $canpost = $vars[1];
                $locked = (bool) $vars[2];
                $replynote = false;
                $desc = '';
                $countcond = 'type=' . Post::FORUM_TOPIC . ' AND xhome=' . $xhome . ' AND home=' . $home;
                $auto_last = isset($_GET['autolast']);
                $replies_enabled = false;
                break;

            case self::RENDER_PM_LIST:
                $posttype = Post::PRIVATE_MSG;
                $xhome = $home;
                $subclass = 'pm';
                $title = null;
                $addlink = _lang('posts.addanswer');
                $nopostsmessage = _lang('posts.noanswers');
                $postsperpage = Settings::get('messagesperpage');
                $canpost = true;
                $locked = (bool) $vars[0];
                $unread_count = (int) $vars[1];
                $replynote = false;
                $desc = '';
                $countcond = 'type=' . Post::PRIVATE_MSG . ' AND home=' . $home . ' AND xhome!=-1';
                $locked_textid = '4';
                $auto_last = true;
                $replies_enabled = false;
                break;

            case self::RENDER_PLUGIN_POSTS:
                $posttype = Post::PLUGIN;
                $xhome = -1;
                $subclass = 'plugin';
                $title = ($vars[5] ?? null);
                $addlink = _lang('posts.addpost');
                $nopostsmessage = _lang('posts.noposts');
                $postsperpage = $vars[0];
                $canpost = $vars[1];
                $locked = (bool) $vars[2];
                $replynote = true;
                $pluginflag = $vars[3];
                $countcond = 'type=' . Post::PLUGIN . ' AND flag=' . $pluginflag;

                if (!$vars[4]) {
                    $desc = '';
                }

                if (isset($vars[6])) {
                    $page_param = $vars[6];
                }
                break;
        }

        // force locked
        if ($force_locked) {
            $locked = true;
        }

        // enable replies?
        if ($replies_enabled === null && !$locked) {
            $replies_enabled = true;
        }

        // extend
        $extend_output = Extend::buffer('posts.output', [
            'type' => $style,
            'home' => $home,
            'xhome' => $xhome,
            'vars' => $vars,
            'post_type' => $posttype,
            'plugin_flag' => $pluginflag,
            'canpost' => &$canpost,
            'locked' => &$locked,
            'autolast' => &$auto_last,
            'posts_per_page' => &$postsperpage,
            'sql_desc' => &$desc,
            'sql_ordercol' => &$ordercol,
            'sql_countcond' => &$countcond,
            'form_position' => &$form_position,
        ]);

        if ($extend_output !== '') {
            return $extend_output;
        }

        // output
        $output = '
  <div id="posts" class="posts posts-' . $subclass . '">
  ';

        if ($title != null) {
            $output .= '<h2>' . $title . "</h2>\n";
        }

        $form_output = "<div class=\"posts-form\" id=\"post-form\">\n";

        // init pager
        $paging = Paginator::paginateTable(
            $url,
            $postsperpage,
            DB::table('post'),
            [
                'cond' => $countcond,
                'link_suffix' => '#posts',
                'param' => $page_param ?? 'page',
                'auto_last' => $auto_last,
            ]
        );

        // message
        if (isset($_GET['r'])) {
            switch (Request::get('r')) {
                case 0:
                    $form_output .= Message::error(_lang('posts.failed'));
                    break;
                case 1:
                    $form_output .= Message::ok(_lang((($style != 5) ? 'posts.added' : 'posts.topicadded')));
                    break;
                case 2:
                    $form_output .= Message::warning(_lang('error.antispam', ['%antispamtimeout%' => Settings::get('antispamtimeout')]));
                    break;
                case 3:
                    $form_output .= Message::warning(_lang('posts.guestnamedenied'));
                    break;
                case 4:
                    $form_output .= Message::warning(_lang('xsrf.msg'));
                    break;
            }
        }

        // render post form or link
        if (!$locked && (isset($_GET['addpost']) || isset($_GET['replyto']))) {
            // fetch reply to ID
            if ($xhome == -1) {
                if (isset($_GET['replyto']) && Request::get('replyto') != -1) {
                    $reply = (int) Request::get('replyto');

                    if ($replynote) {
                        $form_output .= '<p>' . _lang('posts.replynote') . ' (<a href="' . $url_html . '#posts">' . _lang('global.cancel') . '</a>).</p>';
                    }
                } else {
                    $reply = -1;
                }
            } else {
                $reply = $xhome;
            }

            // post form or login form
            if ($canpost) {
                $form_output .= self::renderForm([
                    'posttype' => $posttype,
                    'pluginflag' => $pluginflag,
                    'posttarget' => $home,
                    'xhome' => $reply,
                    'subject' => $subject_enabled,
                    'is_topic' => $style == self::RENDER_FORUM_TOPIC_LIST,
                    'url' => $url,
                ]);
            } else {
                $form_output .= '<p>' . _lang('posts.loginrequired') . "</p>\n";
                $form_output .= User::renderLoginForm();
            }
        } elseif (!$locked) {
            $form_output .= '<a class="button" href="' . _e(UrlHelper::appendParams($url, 'addpost&page=' . $paging['current'])) . '#post-form"><img class="icon" src="' . _e(Template::asset('images/icons/bubble.png')) . '" alt="post">' . $addlink . '</a>';
        } else {
            $form_output .= '<img src="' . _e(Template::asset('images/icons/lock.png')) . '" alt="stop" class="icon"><strong>' . _lang('posts.locked' . $locked_textid) . '</strong>';
        }

        $form_output .= "\n</div>\n";

        if ($form_position === 0) {
            $output .= $form_output;
            $form_output = null;
        }

        // list
        if (Paginator::atTop()) {
            $output .= $paging['paging'];
        }

        // base query
        $userQuery = User::createQuery('p.author');

        if ($is_topic_list) {
            $sql = 'SELECT p.id,p.author,p.guest,p.subject,p.time,p.ip,p.locked,p.bumptime,p.sticky,(SELECT COUNT(*) FROM ' . DB::table('post') . ' WHERE type=' . Post::FORUM_TOPIC . ' AND xhome=p.id) AS answer_count';
        } else {
            $sql = 'SELECT p.id,p.xhome,p.subject,p.text,p.author,p.guest,p.time,p.ip' . Extend::buffer('posts.columns');
        }

        $sql .= ',' . $userQuery['column_list'];
        $sql .= ' FROM ' . DB::table('post') . ' AS p';
        $sql .= ' ' . $userQuery['joins'];

        // conditions and sorting
        $sql .= ' WHERE p.type=' . $posttype . (isset($xhome) ? ' AND p.xhome=' . $xhome : '') . ' AND p.home=' . $home . (isset($pluginflag) ? ' AND p.flag=' . $pluginflag : '');
        $sql .= ' ORDER BY ' . ($is_topic_list ? 'p.sticky DESC,' : '') . $ordercol . ' ' . $desc . $paging['sql_limit'];

        // query
        $query = DB::query($sql);
        unset($sql);

        // load all items into an array
        $items = [];

        if ($is_topic_list) {
            $item_ids_with_answers = [];
        }

        while ($item = DB::row($query)) {
            $items[$item['id']] = $item;

            if ($is_topic_list && $item['answer_count'] != 0) $item_ids_with_answers[] = $item['id'];
        }

        // free query
        unset($query);

        if ($is_topic_list) {
            // last post (for topic lists)
            if (!empty($item_ids_with_answers)) {
                $topicextra = DB::query(
                    'SELECT p.id,p.xhome,p.author,p.guest,' . $userQuery['column_list']
                    . ' FROM ' . DB::table('post') . ' AS p'
                    . ' ' . $userQuery['joins']
                    . ' WHERE p.id IN (SELECT MAX(id) FROM ' . DB::table('post')
                        . ' WHERE type=' . Post::FORUM_TOPIC . ' AND home=' . $home . ' AND xhome IN(' . DB::arr($item_ids_with_answers) . ')'
                        . ' GROUP BY xhome)'
                );

                while ($item = DB::row($topicextra)) {
                    if (!isset($items[$item['xhome']])) {
                        if (Core::$debug) {
                            throw new \RuntimeException('Could not find parent post of reply #' . $item['id']);
                        }

                        continue;
                    }

                    $items[$item['xhome']]['_lastpost'] = $item;
                }
            }
        } elseif (!empty($items)) {
            // answers (to comments)
            $answers = DB::query(
                'SELECT p.id,p.xhome,p.text,p.author,p.guest,p.time,p.ip' . Extend::buffer('posts.columns') . ',' . $userQuery['column_list']
                . ' FROM ' . DB::table('post') . ' p'
                . ' ' . $userQuery['joins']
                . ' WHERE p.type=' . $posttype . ' AND p.home=' . $home . (isset($pluginflag) ? ' AND p.flag=' . $pluginflag : '') . ' AND p.xhome IN(' . DB::arr(array_keys($items)) . ')'
                . ' ORDER BY p.id'
            );

            while ($item = DB::row($answers)) {
                if (!isset($items[$item['xhome']])) {
                    continue;
                }

                if (!isset($items[$item['xhome']]['_answers'])) $items[$item['xhome']]['_answers'] = [];
                $items[$item['xhome']]['_answers'][] = $item;
            }

            unset($answers);
        }

        Extend::call('posts.items', [
            'type' => $style,
            'home' => $home,
            'xhome' => $xhome,
            'vars' => $vars,
            'post_type' => $posttype,
            'plugin_flag' => $pluginflag,
            'items' => &$items,
        ]);

        // list
        if (!empty($items)) {
            // list posts or topics
            if (!$is_topic_list) {
                $output .= "<div class=\"post-list\">\n";

                $extra_info = '';
                $item_offset = ($paging['current'] - 1) * $paging['per_page'];

                foreach ($items as $item) {
                    if ($unread_count !== null) {
                        if ($item_offset >= $paging['count'] - $unread_count) {
                            $extra_info = ', ' . _lang('posts.unread');
                        } else {
                            $extra_info = '';
                        }
                    }

                    $output .= self::renderPost($item, $userQuery, [
                        'current_url' => $url,
                        'current_page' => $paging['current'],
                        'allow_reply' => $replies_enabled,
                        'extra_info' => $extra_info,
                    ]);

                    // answers
                    if (isset($item['_answers'])) {
                        foreach ($item['_answers'] as $answer) {
                            $output .= self::renderPost($answer, $userQuery, [
                                'current_url' => $url,
                                'current_page' => $paging['current'],
                                'is_answer' => true,
                                'allow_reply' => false,
                            ]);
                        }
                    }

                    ++$item_offset;
                }

                $output .= "</div>\n";

                if (Paginator::atBottom()) {
                    $output .= $paging['paging'];
                }

                // form
                if ($form_position === 1) {
                    $output .= $form_output;
                    $form_output = null;
                }
            } else {
                // topic list table
                $hl = false;
                $output .= "\n<table class=\"topic-table\">\n<thead><tr><th colspan=\"2\">" . _lang('posts.topic') . '</th><th>' . _lang('global.answersnum') . '</th><th>' . _lang('global.lastanswer') . "</th></tr></thead>\n<tbody>\n";

                foreach ($items as $item) {
                    // fetch author
                    if ($item['author'] != -1) {
                        $author = Router::userFromQuery($userQuery, $item, ['max_len' => 16]);
                    } else {
                        $author = '<span class="post-author-guest" title="' . GenericTemplates::renderIp($item['ip']) . '">'
                            . StringHelper::ellipsis(self::renderGuestName($item['guest']), 16)
                            . '</span>';
                    }

                    // fetch last post author
                    if (isset($item['_lastpost'])) {
                        if ($item['_lastpost']['author'] != -1) $lastpost = Router::userFromQuery($userQuery, $item['_lastpost'], ['class' => 'post-author', 'max_len' => 16]);
                        else $lastpost = '<span class="post-author-guest">' .StringHelper::ellipsis(self::renderGuestName($item['_lastpost']['guest']), 16) . '</span>';
                    } else {
                        $lastpost = '-';
                    }

                    // choose icon
                    if ($item['sticky']) $icon = 'sticky';
                    elseif ($item['locked']) $icon = 'locked';
                    elseif ($item['answer_count'] == 0) $icon = 'new';
                    elseif ($item['answer_count'] < Settings::get('topic_hot_ratio')) $icon = 'normal';
                    else $icon = 'hot';

                    // mini pager
                    $tpages = '';
                    $tpages_num = ceil($item['answer_count'] / Settings::get('commentsperpage'));

                    if ($tpages_num == 0) $tpages_num = 1;

                    if ($tpages_num > 1) {
                        $tpages .= '<span class="topic-pages">';

                        for ($i = 1; $i <= 3 && $i <= $tpages_num; ++$i) {
                            $tpages .= '<a href="' . _e(Router::topic($item['id'], $forum_slug, ['query' => ['page' => $i], 'fragment' => 'posts'])) . '">' . _num($i) . '</a>';
                        }

                        if ($tpages_num > 3) $tpages .= '<a href="' . _e(Router::topic($item['id'], $forum_slug, ['query' => ['page' => $tpages_num]])) . '">' . _num($tpages_num) . ' &rarr;</a>';
                        $tpages .= '</span>';
                    }

                    // render row
                    $output .= '<tr class="topic-' . $icon . ($hl ? ' topic-hl' : '') . '">'
                        . '<td class="topic-icon-cell"><a href="' . _e(Router::topic($item['id'], $forum_slug)) . '"><img src="' . _e(Template::asset('images/icons/topic-' . $icon . '.png')) . '" alt="' . _lang('posts.topic.' . $icon) . '"></a></td>'
                        . '<td class="topic-main-cell"><a href="' . _e(Router::topic($item['id'], $forum_slug)) . '">' . $item['subject'] . '</a>' . $tpages . '<br>' . $author . ' <small class="post-info">(' . GenericTemplates::renderTime($item['time'], 'post') . ')</small></td>'
                        . '<td>' . _num($item['answer_count']) . '</td><td>' . $lastpost . (($item['answer_count'] != 0) ? '<br><small class="post-info">(' . GenericTemplates::renderTime($item['bumptime'], 'post') . ')</small>' : '') . '</td>'
                        . "</tr>\n";
                    $hl = !$hl;
                }

                $output .= "</tbody></table>\n\n";

                if (Paginator::atBottom()) {
                    $output .= $paging['paging'];
                }

                // form
                if ($form_position === 1) {
                    $output .= $form_output;
                    $form_output = null;
                }

                // latest answers
                $output .= "\n<div class=\"post-answer-list\">\n<h3>" . _lang('posts.forum.lastact') . "</h3>\n";
                $query = DB::query(
                    'SELECT topic.id AS topic_id,topic.subject AS topic_subject,p.author,p.guest,p.time,' . $userQuery['column_list']
                    . ' FROM ' . DB::table('post') . ' AS p'
                    . ' JOIN ' . DB::table('post') . ' AS topic ON(topic.type=' . Post::FORUM_TOPIC . ' AND topic.id=p.xhome)'
                    . ' ' . $userQuery['joins']
                    . ' WHERE p.type=' . Post::FORUM_TOPIC . ' AND p.home=' . $home . ' AND p.xhome!=-1'
                    . ' ORDER BY p.id DESC'
                    . ' LIMIT ' . Settings::get('extratopicslimit')
                );

                if (DB::size($query) != 0) {
                    $output .= "<table class=\"topic-latest\">\n";

                    while ($item = DB::row($query)) {
                        if ($item['author'] != -1) {
                            $author = Router::userFromQuery($userQuery, $item);
                        } else {
                            $author = '<span class="post-author-guest">' . self::renderGuestName($item['guest']) . '</span>';
                        }

                        $output .= '<tr>'
                            . '<td><a href="' . _e(Router::topic($item['topic_id'], $forum_slug)) . '">' . $item['topic_subject'] . '</a></td>'
                            . '<td>' . $author . '</td>'
                            . '<td>' . GenericTemplates::renderTime($item['time'], 'post') . '</td>'
                            . "</tr>\n";
                    }

                    $output .= "</table>\n\n";
                } else {
                    $output .= '<p>' . _lang('global.nokit') . '</p>';
                }

                $output .= "</div>\n";
            }
        } else {
            $output .= '<p>' . $nopostsmessage . '</p>';
        }

        if ($form_position === 1) {
            $output .= $form_output;
        }

        $output .= '</div>';

        return $output;
    }

    /**
     * Render post form
     *
     * $vars structure:
     * ----------------
     * - url          return URL
     * - posttype     post type (see Post::* constants)
     * - posttarget   id_home
     * - xhome        id_xhome
     * - subject      show subject field 1/0
     * - is_topic     the new post is a forum topic 1/0
     * - pluginflag   plugin flag (only for posttype == Comment::PLUGIN)
     *
     * @param array{
     *     url: string,
     *     posttype: int,
     *     posttarget: int,
     *     xhome: int,
     *     subject: bool,
     *     is_topic: bool,
     *     pluginflag?: string|null,
     * } $vars see description
     */
    static function renderForm(array $vars): string
    {
        $inputs = [];

        $captcha = Captcha::init();
        $output = GenericTemplates::jsLimitLength(Post::getMaxLength($vars['posttype']), 'postform', 'text');

        if (!User::isLoggedIn()) {
            $inputs[] = ['label' => _lang('posts.guestname'), 'content' => Form::input('text', 'guest', $_SESSION['post_form_guest'] ?? null, ['class' => 'inputsmall', 'maxlength' => 24])];
        }

        if ($vars['xhome'] == -1 && $vars['subject']) {
            $inputs[] = [
                'label' => _lang($vars['is_topic'] ? 'posts.topic' : 'posts.subject'),
                'content' => Form::input('text', 'subject', $_SESSION['post_form_subject'] ?? null, ['class' => 'input' . ($vars['is_topic'] ? 'medium' : 'small'), 'maxlength' => 48]),
            ];
        }

        $inputs[] = $captcha;
        $inputs[] = [
            'label' => _lang('posts.text'),
            'content' => Form::textarea('text', $_SESSION['post_form_text'] ?? null, ['class' => 'areamedium', 'rows' => 5, 'cols' => 33])
                . Form::input('hidden', '_posttype', $vars['posttype'])
                . Form::input('hidden', '_posttarget', $vars['posttarget'])
                . Form::input('hidden', '_xhome', $vars['xhome'])
                . (isset($vars['pluginflag']) ? Form::input('hidden', '_pluginflag', $vars['pluginflag']) : ''),
            'top' => true,
        ];
        $inputs[] = ['label' => '', 'content' => PostForm::renderControls('postform', 'text')];
        $inputs[] = Form::getSubmitRow(['append' => ' ' . PostForm::renderPreviewButton('postform', 'text')]);

        unset(
            $_SESSION['post_form_guest'],
            $_SESSION['post_form_subject'],
            $_SESSION['post_form_text']
        );

        // form
        $output .= Form::render(
            [
                'name' => 'postform',
                'action' => Router::path('system/script/post.php', ['query' => ['_return' => $vars['url']]]),
            ],
            $inputs
        );

        return $output;
    }

    /**
     * Render a single post
     */
    static function renderPost(
        array $post,
        array $userQuery,
        array $options
    ): string {
        $options += [
            'current_url' => '',
            'current_page' => 1,
            'is_answer' => false,
            'post_link' => true,
            'allow_reply' => true,
            'extra_actions' => [],
            'extra_info' => '',
        ];

        $postAccess = Post::checkAccess($userQuery, $post);

        // fetch author
        if ($post['author'] != -1) {
            $author = Router::userFromQuery($userQuery, $post, ['class' => 'post-author']);
        } else {
            $author = '<span class="post-author-guest" title="' . GenericTemplates::renderIp($post['ip']) . '">'
                . self::renderGuestName($post['guest'])
                . '</span>';
        }

        // action links
        $actlinks = [];

        if ($options['allow_reply']) $actlinks[] = '<a class="post-action-reply" href="' . _e(UrlHelper::appendParams($options['current_url'], 'replyto=' . $post['id'])) . '#posts">' . _lang('posts.reply') . '</a>';

        if ($postAccess) $actlinks[] = '<a class="post-action-edit" href="' . _e(Router::module('editpost', ['query' => ['id' => $post['id']]])) . '">' . _lang('global.edit') . '</a>';
        $actlinks = array_merge($actlinks, $options['extra_actions']);

        // avatar
        if (Settings::get('show_avatars')) {
            $avatar = User::renderAvatarFromQuery($userQuery, $post);
        } else {
            $avatar = null;
        }

        // post
        $output = Extend::buffer('posts.post', [
            'item' => &$post,
            'avatar' => &$avatar,
            'author' => &$author,
            'actlinks' => &$actlinks,
            'options' => $options,
        ]);

        if ($output === '') {
            $output .= '<div id="post-' . $post['id'] . '" class="post' . ($options['is_answer'] ? ' post-answer' : '') . (isset($avatar) ? ' post-withavatar' : '') . '">'
                . '<div class="post-head">'
                    . $author
                    . ' <span class="post-info">(<a href="' . _e(Router::postPermalink($post['id'])) . '">' . GenericTemplates::renderTime($post['time'], 'post') . '</a>' . $options['extra_info'] . ')</span>'
                    . ($actlinks ? ' <span class="post-actions">' . implode(' ', $actlinks) . '</span>' : '')
                . '</div>'
                . '<div class="post-body' . (isset($avatar) ? ' post-body-withavatar' : '') . '">'
                    . $avatar
                    . '<div class="post-body-text">'
                        . Post::render($post['text'])
                    . '</div>'
                . '</div>'
                . "</div>\n";
        }

        return $output;
    }

    /**
     * Get current post URL
     *
     * Note: This method will perform additional database queries to determine post page. Do not use in a loop.
     * @see Router::postPermalink() to get a permanent URL that is not resource-intensive to generate
     *
     * @param array $post post data - {@see Post::createFilter()
     */
    static function getCurrentPostUrl(array $post): string
    {
        $url = self::getPostHomeUrl($post);

        switch ($post['type']) {
            case Post::SECTION_COMMENT:
                $page = Paginator::getItemPage(Settings::get('commentsperpage'), DB::table('post'), 'id>' . $post['id'] . ' AND type=' . Post::SECTION_COMMENT . ' AND xhome=-1 AND home=' . $post['home']);

                return UrlHelper::appendParams($url, 'page=' . $page . '#post-' . $post['id']);

            case Post::ARTICLE_COMMENT:
                $page = Paginator::getItemPage(Settings::get('commentsperpage'), DB::table('post'), 'id>' . $post['id'] . ' AND type=' . Post::ARTICLE_COMMENT . ' AND xhome=-1 AND home=' . $post['home']);

                return UrlHelper::appendParams($url, 'page=' . $page) . '#post-' . $post['id'];

            case Post::BOOK_ENTRY:
                $postsperpage = DB::queryRow('SELECT var2 FROM ' . DB::table('page') . ' WHERE id=' . $post['home']);

                if ($postsperpage['var2'] === null) {
                    $postsperpage['var2'] = Settings::get('commentsperpage');
                }

                $page = Paginator::getItemPage($postsperpage['var2'], DB::table('post'), 'id>' . $post['id'] . ' AND type=' . Post::BOOK_ENTRY . ' AND xhome=-1 AND home=' . $post['home']);

                return UrlHelper::appendParams($url, 'page=' . $page) . '#post-' . $post['id'];

            case Post::FORUM_TOPIC:
                if ($post['xhome'] == -1) {
                    return Router::topic($post['id'], $post['page_slug']);
                }

                $page = Paginator::getItemPage(Settings::get('commentsperpage'), DB::table('post'), 'id<' . $post['id'] . ' AND type=' . Post::FORUM_TOPIC . ' AND xhome=' . $post['xhome'] . ' AND home=' . $post['home']);

                return UrlHelper::appendParams($url, 'page=' . $page) . '#post-' . $post['id'];

            case Post::PRIVATE_MSG:
                $url = Router::module('messages', ['query' => ['a' => 'list', 'read' => $post['home']]]);

                if ($post['xhome'] != -1) {
                    $page = Paginator::getItemPage(Settings::get('messagesperpage'), DB::table('post'), 'id<' . $post['id'] . ' AND type=' . Post::PRIVATE_MSG . ' AND home=' . $post['home']);
                    $url = UrlHelper::appendParams($url, 'page=' . $page) . '#post-' . $post['id'];
                }

                return $url;

            case Post::PLUGIN:
                Extend::call("posts.{$post['flag']}.url", [
                    'post' => $post,
                    'url' => &$url,
                ]);

                return $url;

            default:
                return $url;
        }
    }

    /**
     * Get home URL for the given post
     *
     * @param array $post post data - {@see Post::createFilter()
     */
    static function getPostHomeUrl(array $post): string
    {
        switch ($post['type']) {
            case Post::SECTION_COMMENT:
            case Post::BOOK_ENTRY:
                return Router::page($post['home'], $post['page_slug']);

            case Post::FORUM_TOPIC:
                return $post['xhome'] == -1
                    ? Router::page($post['home'], $post['page_slug'])
                    : Router::topic($post['xhome'], $post['page_slug']);

            case Post::ARTICLE_COMMENT:
                return Router::article(null, $post['art_slug'], $post['cat_slug']);

            case Post::PRIVATE_MSG:
                return $post['xhome'] == -1
                    ? Router::module('messages')
                    : Router::module('messages', ['query' => ['a' => 'list', 'read' => $post['xhome']]]);

            case Post::PLUGIN:
                $url = '';

                Extend::call("posts.{$post['flag']}.home_url", [
                    'post' => $post,
                    'url' => &$url,
                ]);

                return $url;

            default:
                return '#';
        }
    }

    /**
     * Get title for the given post
     *
     * @param array $post post data - {@see Post::createFilter()
     */
    static function getPostTitle(array $post): string
    {
        switch ($post['type']) {
            case Post::SECTION_COMMENT:
            case Post::BOOK_ENTRY:
                return $post['page_title'];

            case Post::ARTICLE_COMMENT:
                return $post['art_title'];

            case Post::FORUM_TOPIC:
            case Post::PRIVATE_MSG:
                return ($post['xhome'] == -1)
                    ? $post['subject']
                    : $post['xhome_subject'];

            case Post::PLUGIN:
                $title = '';

                Extend::call("posts.{$post['flag']}.title", [
                    'post' => $post,
                    'title' => &$title,
                ]);

                return $title;

            default:
                return '?';
        }
    }

    /**
     * Remove specific plugin posts
     *
     * @param int $flag plugin post flag
     * @param int|null $home specific home ID or null (all)
     * @param bool $get_count do not remove, return count only 1/0
     */
    static function deleteByPluginFlag(int $flag, ?int $home, bool $get_count = true): ?int
    {
        // condition
        $cond = 'type=' . Post::PLUGIN . ' AND flag=' . $flag;

        if (isset($home)) {
            $cond .= ' AND home=' . $home;
        }

        // delete or count
        if ($get_count) {
            return DB::count('post', $cond);
        }

        DB::delete('post', $cond);

        return null;
    }

    static function normalizeGuestName(string $guest): string
    {
        return StringHelper::cut(
            StringHelper::slugify($guest, ['lower' => false]),
            24
        );
    }

    static function renderGuestName(string $guest): string
    {
        if ($guest === '') {
            return _lang('posts.anonym');
        }

        return $guest;
    }
}
