<?php

if (!defined('_root')) {
    exit;
}

function _HCM_sbox($id = null)
{
    // priprava
    $result = "";
    $id = (int) $id;

    // nacteni dat shoutboxu
    $sboxdata = DB::query("SELECT * FROM " . _sboxes_table . " WHERE id=" . $id);
    if (DB::size($sboxdata) != 0) {
        $sboxdata = DB::row($sboxdata);
        $rcontinue = true;
    } else {
        $rcontinue = false;
    }

    // sestaveni kodu
    if ($rcontinue) {

        $result = "
    <div id='hcm_sbox_" . Sunlight\Core::$hcmUid . "' class='sbox'>
    <div class='sbox-content'>
    " . (($sboxdata['title'] != "") ? "<div class='sbox-title'>" . $sboxdata['title'] . "</div>" : '') . "<div class='sbox-item'" . (($sboxdata['title'] == "") ? " style='border-top:none;'" : '') . ">";

        // formular na pridani
        if ($sboxdata['locked'] != 1 && _publicAccess($sboxdata['public'])) {

            // priprava bunek
            if (!_login) {
                $inputs[] = array('label' => $GLOBALS['_lang']['posts.guestname'], 'content' => "<input type='text' name='guest' class='sbox-input' maxlength='22'>");
            }
            $inputs[] = array('label' => $GLOBALS['_lang']['posts.text'], 'content' => "<input type='text' name='text' class='sbox-input' maxlength='255'><input type='hidden' name='_posttype' value='4'><input type='hidden' name='_posttarget' value='" . $id . "'>");

            $result .= _formOutput(
                array(
                    'name' => 'hcm_sboxform_' . Sunlight\Core::$hcmUid,
                    'action' => _link('system/script/post.php?_return=' . rawurlencode($GLOBALS['_index']['url']) . "#hcm_sbox_" . Sunlight\Core::$hcmUid),
                ),
                $inputs
            );

        } else {
            if ($sboxdata['locked'] != 1) {
                $result .= $GLOBALS['_lang']['posts.loginrequired'];
            } else {
                $result .= "<img src='" . _templateImage("icons/lock.png") . "' alt='locked' class='icon'>" . $GLOBALS['_lang']['posts.locked2'];
            }
        }

        $result .= "\n</div>\n<div class='sbox-posts'>";
        // vypis prispevku
        $userQuery = _userQuery('p.author');
        $sposts = DB::query("SELECT p.id,p.text,p.author,p.guest,p.time,p.ip," . $userQuery['column_list'] . " FROM " . _posts_table . " p " . $userQuery['joins'] . " WHERE p.home=" . $id . " AND p.type=" . _post_shoutbox_entry . " ORDER BY p.id DESC");
        if (DB::size($sposts) != 0) {
            while ($spost = DB::row($sposts)) {

                // nacteni autora
                if ($spost['author'] != -1) {
                    $author = _linkUserFromQuery($userQuery, $spost, array('class' => 'post_author', 'max_len' => 16, 'title' => _formatTime($spost['time'], 'post')));
                } else {
                    $author = "<span class='post-author-guest' title='" . _formatTime($spost['time'], 'post') . ", ip=" . _showIP($spost['ip']) . "'>" . $spost['guest'] . "</span>";
                }

                // odkaz na spravu
                if (_postAccess($userQuery, $spost)) {
                    $alink = " <a href='" . _linkModule('editpost', 'id=' . $spost['id']) . "'><img src='" . _templateImage("icons/edit.png") . "' alt='edit' class='icon'></a>";
                } else {
                    $alink = "";
                }

                // kod polozky
                $result .= "<div class='sbox-item'>" . $author . ':' . $alink . " " . _parsePost($spost['text'], true, false, false) . "</div>\n";

            }
        } else {
            $result .= "\n<div class='sbox-item'>" . $GLOBALS['_lang']['posts.noposts'] . "</div>\n";
        }

        $result .= "
  </div>
  </div>
  </div>
  ";

    }

    return $result;
}
