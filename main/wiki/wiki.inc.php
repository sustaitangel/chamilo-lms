<?php
/* For licensing terms, see /license.txt */
/**
 * Functions library for the wiki tool
 * @author Juan Carlos Raña <herodoto@telefonica.net>
 * @author Patrick Cool <patrick.cool@UGent.be>, Ghent University, Belgium
 * @author Julio Montoya <gugli100@gmail.com> using the pdf.lib.php library
 * @package chamilo.wiki
 */

class Wiki
{
    public $tbl_wiki;
    public $tbl_wiki_discuss;
    public $tbl_wiki_mailcue;
    public $tbl_wiki_conf;
    public $session_id = null;
    public $course_id = null;
    public $condition_session = null;
    public $group_id;
    public $assig_user_id;
    public $groupfilter = 'group_id=0';
    public $courseInfo;
    public $charset;
    public $page;

    public function __construct()
    {
        // Database table definition
        $this->tbl_wiki           = Database::get_course_table(TABLE_WIKI);
        $this->tbl_wiki_discuss   = Database::get_course_table(TABLE_WIKI_DISCUSS);
        $this->tbl_wiki_mailcue   = Database::get_course_table(TABLE_WIKI_MAILCUE);
        $this->tbl_wiki_conf      = Database::get_course_table(TABLE_WIKI_CONF);

        $this->session_id = api_get_session_id();
        $this->condition_session = api_get_session_condition($this->session_id);
        $this->course_id = api_get_course_int_id();
        $this->group_id = api_get_group_id();
        if (!empty($this->group_id)) {
            $this->groupfilter = ' group_id="'.$this->group_id.'"';
        }
        $this->courseInfo = api_get_course_info();
    }

    /**
     * Check whether this title is already used
     * @param string $paramwk
     * @return bool  False if title is already taken
     * @author Patrick Cool <patrick.cool@ugent.be>, Ghent University
     **/
    function checktitle($paramwk)
    {
        $tbl_wiki = $this->tbl_wiki;
        $condition_session = $this->condition_session;
        $course_id = $this->course_id;
        $groupfilter = $this->groupfilter;

        $sql = 'SELECT * FROM '.$tbl_wiki.'
                WHERE
                    c_id = '.$course_id.' AND
                    reflink="'.Database::escape_string($paramwk).'" AND
                    '.$groupfilter.$condition_session.'';
        $result=Database::query($sql);
        $numberofresults=Database::num_rows($result);
        // the value has not been found and is this available
        if ($numberofresults==0) {
            return true;
        } else {
            // the value has been found
            return false;
        }
    }

    /**
     * check wikilinks that has a page
     * @author Juan Carlos Raña <herodoto@telefonica.net>
     **/
    function links_to($input)
    {
        $input_array = preg_split("/(\[\[|\]\])/",$input,-1, PREG_SPLIT_DELIM_CAPTURE);
        $all_links = array();

        foreach ($input_array as $key=>$value) {
            if (isset($input_array[$key-1]) && $input_array[$key-1] == '[[' &&
                isset($input_array[$key+1]) && $input_array[$key+1] == ']]'
            ) {
                if (api_strpos($value, "|") !== false) {
                    $full_link_array=explode("|", $value);
                    $link=trim($full_link_array[0]);
                    $title=trim($full_link_array[1]);
                } else {
                    $link=trim($value);
                    $title=trim($value);
                }
                unset($input_array[$key-1]);
                unset($input_array[$key+1]);
                //replace blank spaces by _ within the links. But to remove links at the end add a blank space
                $all_links[]= Database::escape_string(str_replace(' ','_',$link)).' ';
            }
        }
        $output = implode($all_links);

        return $output;
    }

    /**
     * detect and add style to external links
     * @author Juan Carlos Raña Trabado
     **/
    function detect_external_link($input)
    {
        $exlink='href=';
        $exlinkStyle='class="wiki_link_ext" href=';
        $output=str_replace($exlink, $exlinkStyle, $input);
        return $output;
    }

    /**
     * detect and add style to anchor links
     * @author Juan Carlos Raña Trabado
     **/
    function detect_anchor_link($input)
    {
        $anchorlink = 'href="#';
        $anchorlinkStyle='class="wiki_anchor_link" href="#';
        $output = str_replace($anchorlink, $anchorlinkStyle, $input);

        return $output;
    }

    /**
     * detect and add style to mail links
     * author Juan Carlos Raña Trabado
     **/
    function detect_mail_link($input)
    {
        $maillink='href="mailto';
        $maillinkStyle='class="wiki_mail_link" href="mailto';
        $output=str_replace($maillink, $maillinkStyle, $input);
        return $output;
    }

    /**
     * detect and add style to ftp links
     * @author Juan Carlos Raña Trabado
    **/
    function detect_ftp_link($input)
    {
        $ftplink='href="ftp';
        $ftplinkStyle='class="wiki_ftp_link" href="ftp';
        $output=str_replace($ftplink, $ftplinkStyle, $input);
        return $output;
    }

    /**
     * detect and add style to news links
     * @author Juan Carlos Raña Trabado
     **/
    function detect_news_link($input)
    {
        $newslink='href="news';
        $newslinkStyle='class="wiki_news_link" href="news';
        $output=str_replace($newslink, $newslinkStyle, $input);
        return $output;
    }

    /**
     * detect and add style to irc links
     * @author Juan Carlos Raña Trabado
     **/
    function detect_irc_link($input)
    {
        $irclink='href="irc';
        $irclinkStyle='class="wiki_irc_link" href="irc';
        $output=str_replace($irclink, $irclinkStyle, $input);
        return $output;
    }
    /**
     * This function allows users to have [link to a title]-style links like in most regular wikis.
     * It is true that the adding of links is probably the most anoying part of Wiki for the people
     * who know something about the wiki syntax.
     * @author Patrick Cool <patrick.cool@ugent.be>, Ghent University
     * Improvements [[]] and [[ | ]]by Juan Carlos Raña
     * Improvements internal wiki style and mark group by Juan Carlos Raña
     **/
    function make_wiki_link_clickable($input)
    {
        $groupId = api_get_group_id();
        //now doubles brackets
        $input_array = preg_split("/(\[\[|\]\])/",$input,-1, PREG_SPLIT_DELIM_CAPTURE);

        foreach ($input_array as $key => $value) {
            //now doubles brackets
            if (isset($input_array[$key-1]) && $input_array[$key-1] == '[[' AND
                $input_array[$key+1] == ']]'
            ) {
                // now full wikilink
                if (api_strpos($value, "|") !== false){
                    $full_link_array=explode("|", $value);
                    $link=trim(strip_tags($full_link_array[0]));
                    $title=trim($full_link_array[1]);
                } else {
                    $link=trim(strip_tags($value));
                    $title=trim($value);
                }

                //if wikilink is homepage
                if ($link=='index') {
                    $title=get_lang('DefaultTitle');
                }
                if ($link==get_lang('DefaultTitle')){
                    $link='index';
                }

                // note: checkreflink checks if the link is still free. If it is not used then it returns true, if it is used, then it returns false. Now the title may be different
                if (self::checktitle(strtolower(str_replace(' ','_',$link)))) {
                    $link = api_html_entity_decode($link);
                    $input_array[$key]='<a href="'.api_get_path(WEB_PATH).'main/wiki/index.php?'.api_get_cidreq().'&action=addnew&amp;title='.Security::remove_XSS($link).'&group_id='.$groupId.'" class="new_wiki_link">'.$title.'</a>';
                } else {
                    $input_array[$key]='<a href="'.api_get_path(WEB_PATH).'main/wiki/index.php?'.api_get_cidreq().'&action=showpage&amp;title='.urlencode(strtolower(str_replace(' ','_',$link))).'&group_id='.$groupId.'" class="wiki_link">'.$title.'</a>';
                }
                unset($input_array[$key-1]);
                unset($input_array[$key+1]);
            }
        }
        $output=implode('',$input_array);
        return $output;
    }

    /**
    * This function saves a change in a wiki page
    * @author Patrick Cool <patrick.cool@ugent.be>, Ghent University
    * @return language string saying that the changes are stored
    **/
    function save_wiki()
    {
        $tbl_wiki = $this->tbl_wiki;
        $tbl_wiki_conf = $this->tbl_wiki_conf;
        $_course = $this->courseInfo;
        $_clean = array(
            'task' => null,
            'feedback1' => null,
            'feedback2' => null,
            'feedback3' => null,
            'fprogress1' => null,
            'fprogress2' => null,
            'fprogress3' => null,
            'max_text' => null,
            'max_version' => null,
            'delayedsubmit' => null
        );

        // NOTE: visibility, visibility_disc and ratinglock_disc changes are not made here, but through the interce buttons

        // cleaning the variables
        $_clean['page_id']		= Database::escape_string($_POST['page_id']);
        $_clean['reflink']		= Database::escape_string(trim($_POST['reflink']));
        $_clean['title']		= Database::escape_string(trim($_POST['title']));
        $_clean['content']		= Database::escape_string($_POST['content']);
        if (api_get_setting('htmlpurifier_wiki') == 'true'){
            $purifier = new HTMLPurifier();
            $_clean['content'] = $purifier->purify($_clean['content']);
        }
        $_clean['user_id']		= api_get_user_id();
        $_clean['assignment']	= Database::escape_string($_POST['assignment']);
        $_clean['comment']		= Database::escape_string($_POST['comment']);
        $_clean['progress']		= Database::escape_string($_POST['progress']);
        $_clean['version']		= intval($_POST['version']) + 1 ;
        $_clean['linksto'] 		= self::links_to($_clean['content']); //and check links content

        $dtime = date( "Y-m-d H:i:s" );
        $session_id = api_get_session_id();
        $groupId = api_get_group_id();

        //cleaning config variables
        if (!empty($_POST['task'])) {
            $_clean['task'] = Database::escape_string($_POST['task']);
        }

        if (!empty($_POST['feedback1']) || !empty($_POST['feedback2']) || !empty($_POST['feedback3'])) {
            $_clean['feedback1']=Database::escape_string($_POST['feedback1']);
            $_clean['feedback2']=Database::escape_string($_POST['feedback2']);
            $_clean['feedback3']=Database::escape_string($_POST['feedback3']);
            $_clean['fprogress1']=Database::escape_string($_POST['fprogress1']);
            $_clean['fprogress2']=Database::escape_string($_POST['fprogress2']);
            $_clean['fprogress3']=Database::escape_string($_POST['fprogress3']);
        }

        if (isset($_POST['initstartdate']) && $_POST['initstartdate'] == 1) {
            $_clean['startdate_assig']=Database::escape_string(self::get_date_from_select('startdate_assig'));
        } else {
            $_clean['startdate_assig']=Database::escape_string($_POST['startdate_assig']);
        }

        if (isset($_POST['initenddate']) && $_POST['initenddate']==1) {
            $_clean['enddate_assig'] = Database::escape_string(self::get_date_from_select('enddate_assig'));
        } else {
            $_clean['enddate_assig'] = Database::escape_string($_POST['enddate_assig']);
        }

        if (isset($_POST['delayedsubmit'])) {
            $_clean['delayedsubmit']=Database::escape_string($_POST['delayedsubmit']);
        }

        if (!empty($_POST['max_text']) || !empty($_POST['max_version'])) {
            $_clean['max_text']	=Database::escape_string($_POST['max_text']);
            $_clean['max_version']=Database::escape_string($_POST['max_version']);
        }

        $course_id = api_get_course_int_id();
        $sql = "INSERT INTO ".$tbl_wiki." (c_id, page_id, reflink, title, content, user_id, group_id, dtime, assignment, comment, progress, version, linksto, user_ip, session_id)
                VALUES ($course_id, '".$_clean['page_id']."','".$_clean['reflink']."','".$_clean['title']."','".$_clean['content']."','".$_clean['user_id']."','".$groupId."','".$dtime."','".$_clean['assignment']."','".$_clean['comment']."','".$_clean['progress']."','".$_clean['version']."','".$_clean['linksto']."','".Database::escape_string($_SERVER['REMOTE_ADDR'])."', '".Database::escape_string($session_id)."')";
        Database::query($sql);
        $Id 	= Database::insert_id();

        if ($Id > 0) {
            //insert into item_property
            api_item_property_update(api_get_course_info(), TOOL_WIKI, $Id, 'WikiAdded', api_get_user_id(), $groupId);
        }

        if ($_clean['page_id']	==0) {
            $sql='UPDATE '.$tbl_wiki.' SET page_id="'.$Id.'" WHERE c_id = '.$course_id.' AND id="'.$Id.'"';
            Database::query($sql);
        }

        //update wiki config
        if ($_clean['reflink']=='index' && $_clean['version']==1) {
            $sql="INSERT INTO ".$tbl_wiki_conf." (c_id, page_id, task, feedback1, feedback2, feedback3, fprogress1, fprogress2, fprogress3, max_text, max_version, startdate_assig, enddate_assig, delayedsubmit)
                  VALUES ($course_id, '".$Id."','".$_clean['task']."','".$_clean['feedback1']."','".$_clean['feedback2']."','".$_clean['feedback3']."','".$_clean['fprogress1']."','".$_clean['fprogress2']."','".$_clean['fprogress3']."','".$_clean['max_text']."','".$_clean['max_version']."','".$_clean['startdate_assig']."','".$_clean['enddate_assig']."','".$_clean['delayedsubmit']."')";
        } else {
            $sql='UPDATE'.$tbl_wiki_conf.' SET task="'.$_clean['task'].'", feedback1="'.$_clean['feedback1'].'", feedback2="'.$_clean['feedback2'].'", feedback3="'.$_clean['feedback3'].'",  fprogress1="'.$_clean['fprogress1'].'",  fprogress2="'.$_clean['fprogress2'].'",  fprogress3="'.$_clean['fprogress3'].'", max_text="'.$_clean['max_text'].'", max_version="'.$_clean['max_version'].'", startdate_assig="'.$_clean['startdate_assig'].'", enddate_assig="'.$_clean['enddate_assig'].'", delayedsubmit="'.$_clean['delayedsubmit'].'"
                  WHERE page_id="'.$_clean['page_id'].'" AND c_id = '.$course_id;
        }
        Database::query($sql);
        api_item_property_update($_course, 'wiki', $Id, 'WikiAdded', api_get_user_id(), $groupId);
        self::check_emailcue($_clean['reflink'], 'P', $dtime, $_clean['user_id']);
        return get_lang('ChangesStored');
    }

    /**
    * This function restore a wikipage
    * @author Juan Carlos Raña <herodoto@telefonica.net>
    * @return string Message of success (to be printed on screen)
    **/
    function restore_wikipage($r_page_id, $r_reflink, $r_title, $r_content, $r_group_id, $r_assignment, $r_progress, $c_version, $r_version, $r_linksto)
    {
        $tbl_wiki = $this->tbl_wiki;
        $_course = $this->courseInfo;

        $r_user_id= api_get_user_id();
        $r_dtime = date( "Y-m-d H:i:s" );
        $r_version = $r_version+1;
        $r_comment = get_lang('RestoredFromVersion').': '.$c_version;
        $session_id = api_get_session_id();
        $course_id = api_get_course_int_id();

        $sql="INSERT INTO ".$tbl_wiki." (c_id, page_id, reflink, title, content, user_id, group_id, dtime, assignment, comment, progress, version, linksto, user_ip, session_id) VALUES
        ($course_id, '".$r_page_id."','".$r_reflink."','".$r_title."','".$r_content."','".$r_user_id."','".$r_group_id."','".$r_dtime."','".$r_assignment."','".$r_comment."','".$r_progress."','".$r_version."','".$r_linksto."','".Database::escape_string($_SERVER['REMOTE_ADDR'])."','".Database::escape_string($session_id)."')";

        Database::query($sql);
        $Id = Database::insert_id();
        api_item_property_update($_course, 'wiki', $Id, 'WikiAdded', api_get_user_id(), $r_group_id);
        self::check_emailcue($r_reflink, 'P', $r_dtime, $r_user_id);

        return get_lang('PageRestored');
    }

    /**
    * This function delete a wiki
    * @author Juan Carlos Raña <herodoto@telefonica.net>
    * @return   string  Message of success (to be printed)
    **/
    function delete_wiki()
    {
        $tbl_wiki = $this->tbl_wiki;
        $tbl_wiki_discuss = $this->tbl_wiki_discuss;
        $tbl_wiki_mailcue = $this->tbl_wiki_mailcue;
        $tbl_wiki_conf = $this->tbl_wiki_conf;
        $session_id = $this->session_id;
        $condition_session = $this->condition_session;
        $group_id = $this->group_id;
        $groupfilter = $this->groupfilter;
        $course_id = $this->course_id;

        //identify the first id by group = identify wiki
        $sql = 'SELECT * FROM '.$tbl_wiki.'
                WHERE  c_id = '.$course_id.' AND '.$groupfilter.$condition_session.'
                ORDER BY id DESC';
        $allpages = Database::query($sql);
        while ($row = Database::fetch_array($allpages)) {
            $id 		= $row['id'];
            $group_id	= $row['group_id'];
            $session_id = $row['session_id'];
            //$page_id	= $row['page_id'];
            Database::query('DELETE FROM '.$tbl_wiki_conf.' 	WHERE page_id="'.$id.'" AND c_id = '.$course_id);
            Database::query('DELETE FROM '.$tbl_wiki_discuss.'	WHERE publication_id="'.$id.'" AND c_id = '.$course_id);
        }

        Database::query('DELETE FROM '.$tbl_wiki_mailcue.' WHERE session_id="'.$session_id.'" AND group_id="'.$group_id.'" AND c_id = '.$course_id);
        Database::query('DELETE FROM '.$tbl_wiki.' WHERE session_id="'.$session_id.'" AND group_id="'.$group_id.'" AND c_id = '.$course_id);
        return get_lang('WikiDeleted');
    }


    /**
    * This function saves a new wiki page.
    * @author Patrick Cool <patrick.cool@ugent.be>, Ghent University
    * @todo consider merging this with the function save_wiki into one single function.
    * @return string Message of success
    **/
    function save_new_wiki()
    {
        $tbl_wiki = $this->tbl_wiki;
        $tbl_wiki_conf = $this->tbl_wiki_conf;
        $charset = $this->charset;
        $assig_user_id = $this->assig_user_id;

        $_clean = array();

        // cleaning the variables
        $_clean['assignment'] = null;
        if (isset($_POST['assignment'])) {
            $_clean['assignment'] = Database::escape_string($_POST['assignment']);
        }

        // session_id
        $session_id = api_get_session_id();
        // Unlike ordinary pages of pages of assignments. Allow create a ordinary page although there is a assignment with the same name
        if ($_clean['assignment']==2 || $_clean['assignment']==1) {
            $page = str_replace(' ','_',$_POST['title']."_uass".$assig_user_id);
        } else {
             $page = str_replace(' ','_',$_POST['title']);
        }
        $_clean['reflink'] = Database::escape_string(strip_tags(api_htmlentities($page)));
        $_clean['title']   = Database::escape_string(strip_tags(trim($_POST['title'])));
        $_clean['content'] = Database::escape_string($_POST['content']);

        if (api_get_setting('htmlpurifier_wiki') == 'true'){
            $purifier = new HTMLPurifier();
            $_clean['content'] = $purifier->purify($_clean['content']);
        }

        //re-check after strip_tags if the title is empty
        if(empty($_clean['title']) || empty($_clean['reflink'])) {
            return false;
        }

        if ($_clean['assignment']==2)  {//config by default for individual assignment (students)
            //Identifies the user as a creator, not the teacher who created
            $_clean['user_id']=(int)Database::escape_string($assig_user_id);
            $_clean['visibility']=0;
            $_clean['visibility_disc']=0;
            $_clean['ratinglock_disc']=0;
        } else {
            $_clean['user_id']=api_get_user_id();
            $_clean['visibility']=1;
            $_clean['visibility_disc']=1;
            $_clean['ratinglock_disc']=1;
        }

        $_clean['comment']=Database::escape_string($_POST['comment']);
        $_clean['progress']=Database::escape_string($_POST['progress']);
        $_clean['version']=1;

        $groupId = api_get_group_id();

        $_clean['linksto'] = self::links_to($_clean['content']);	//check wikilinks

        //cleaning config variables
        $_clean['task']= Database::escape_string($_POST['task']);
        $_clean['feedback1']=Database::escape_string($_POST['feedback1']);
        $_clean['feedback2']=Database::escape_string($_POST['feedback2']);
        $_clean['feedback3']=Database::escape_string($_POST['feedback3']);
        $_clean['fprogress1']=Database::escape_string($_POST['fprogress1']);
        $_clean['fprogress2']=Database::escape_string($_POST['fprogress2']);
        $_clean['fprogress3']=Database::escape_string($_POST['fprogress3']);

        if (isset($_POST['initstartdate']) && $_POST['initstartdate'] == 1) {
            $_clean['startdate_assig'] = Database::escape_string(self::get_date_from_select('startdate_assig'));
        } else {
            $_clean['startdate_assig'] = Database::escape_string($_POST['startdate_assig']);
        }

        if (isset($_POST['initenddate']) && $_POST['initenddate']==1) {
            $_clean['enddate_assig']=Database::escape_string(self::get_date_from_select('enddate_assig'));
        } else {
            $_clean['enddate_assig']=Database::escape_string($_POST['enddate_assig']);
        }

        $_clean['delayedsubmit']=Database::escape_string($_POST['delayedsubmit']);
        $_clean['max_text']=Database::escape_string($_POST['max_text']);
        $_clean['max_version']=Database::escape_string($_POST['max_version']);

        $course_id = api_get_course_int_id();

        //filter no _uass
        if (api_eregi('_uass', $_POST['title']) || (api_strtoupper(trim($_POST['title'])) == 'INDEX' || api_strtoupper(trim(api_htmlentities($_POST['title'], ENT_QUOTES, $charset))) == api_strtoupper(api_htmlentities(get_lang('DefaultTitle'), ENT_QUOTES, $charset)))) {
            $message= get_lang('GoAndEditMainPage');
            Display::display_warning_message($message,false);
        } else {
            $var=$_clean['reflink'];
            $group_id=Security::remove_XSS($_GET['group_id']);
            if(!self::checktitle($var)) {
               return get_lang('WikiPageTitleExist').'<a href="index.php?action=edit&amp;title='.$var.'&group_id='.$group_id.'">'.$_POST['title'].'</a>';
            } else {
                $dtime = date( "Y-m-d H:i:s" );
                $sql = "INSERT INTO ".$tbl_wiki." (c_id, reflink, title, content, user_id, group_id, dtime, visibility, visibility_disc, ratinglock_disc, assignment, comment, progress, version, linksto, user_ip, session_id) VALUES
                        ($course_id, '".$_clean['reflink']."','".$_clean['title']."','".$_clean['content']."','".$_clean['user_id']."','".$groupId."','".$dtime."','".$_clean['visibility']."','".$_clean['visibility_disc']."','".$_clean['ratinglock_disc']."','".$_clean['assignment']."','".$_clean['comment']."','".$_clean['progress']."','".$_clean['version']."','".$_clean['linksto']."','".Database::escape_string($_SERVER['REMOTE_ADDR'])."', '".Database::escape_string($session_id)."')";
                $result = Database::query($sql);
                $Id = Database::insert_id();
                if ($Id > 0) {
                    //insert into item_property
                    api_item_property_update(api_get_course_info(), TOOL_WIKI, $Id, 'WikiAdded', api_get_user_id(), $groupId);
                }

               $sql='UPDATE '.$tbl_wiki.' SET page_id="'.$Id.'" WHERE c_id = '.$course_id.' AND id="'.$Id.'"';
               Database::query($sql);

                //insert wiki config
               $sql="INSERT INTO ".$tbl_wiki_conf." (c_id, page_id, task, feedback1, feedback2, feedback3, fprogress1, fprogress2, fprogress3, max_text, max_version, startdate_assig, enddate_assig, delayedsubmit) VALUES
                    ($course_id, '".$Id."','".$_clean['task']."','".$_clean['feedback1']."','".$_clean['feedback2']."','".$_clean['feedback3']."','".$_clean['fprogress1']."','".$_clean['fprogress2']."','".$_clean['fprogress3']."','".$_clean['max_text']."','".$_clean['max_version']."','".$_clean['startdate_assig']."','".$_clean['enddate_assig']."','".$_clean['delayedsubmit']."')";
               Database::query($sql);
               self::check_emailcue(0, 'A');

               return get_lang('NewWikiSaved');
            }
        }
    }

    /**
     * This function displays the form for adding a new wiki page.
     * @author Patrick Cool <patrick.cool@ugent.be>, Ghent University
     * @return html code
     **/
    function display_new_wiki_form()
    {
        $page = $this->page;
    ?>
    <script>
    function CheckSend()
    {
        if (document.form1.title.value == "") {
            alert("<?php echo get_lang('NoWikiPageTitle');?>");
            document.form1.title.focus();
            return false;
        }
        return true;
    }
    function setFocus(){
        $("#wiki_title").focus();
    }
    $(document).ready(function () {
        setFocus();
    });
    </script>
    <?php
        $title = isset($_GET['title']) ? Security::remove_XSS($_GET['title']) : null;
        //form
        echo '<form name="form1" method="post" onsubmit="return CheckSend()" action="'.api_get_self().'?'.api_get_cidreq().'&action=showpage&amp;title='.api_htmlentities(urlencode(strtolower(str_replace(' ','_',$page)))).'&group_id='.api_htmlentities($_GET['group_id']).'">';
        echo '<div id="wikititle" style="min-height:30px;">';
        echo '<div style="width:70%;float:left;">
                <span class="form_required">*</span> '.get_lang('Title').': <input type="text" id="wiki_title" name="title" value="'.$title.'" size="40"></div>';

        if(api_is_allowed_to_edit(false,true) || api_is_platform_admin()) {
            echo'<a href="javascript://" onclick="advanced_parameters()" ><span id="plus_minus" style="float:right">&nbsp;'.Display::return_icon('div_show.gif',get_lang('Show'),array('style'=>'vertical-align:middle')).'&nbsp;'.get_lang('AdvancedParameters').'</span></a>';
            echo '<div id="options" style="display:none; margin: 20px;" >';

            //task
            echo '<div>&nbsp;</div>';
            echo '<div style= "border : 1px dotted; padding:4px; margin-top:20px;">';
            echo '<input type="checkbox" value="1" name="checktask" onclick="javascript: if(this.checked){document.getElementById(\'option4\').style.display=\'block\';}else{document.getElementById(\'option4\').style.display=\'none\';}"/>&nbsp;
            '.Display::return_icon('wiki_task.png', get_lang('DefineTask'),'',ICON_SIZE_SMALL).' '.get_lang('DescriptionOfTheTask').'';
            echo '&nbsp;&nbsp;&nbsp;<span id="msg_error4" style="display:none;color:red"></span>';
            echo '<div id="option4" style="padding:4px; margin:5px; border:1px dotted; display:none;">';

            echo '<table border="0" style="font-weight:normal">';
            echo '<tr>';
            echo '<td>'.get_lang('DescriptionOfTheTask').'</td>';
            echo '</tr>';
            echo '<tr>';
            echo '<td>'.api_disp_html_area('task', '', '', '', null, array('ToolbarSet' => 'wiki_task', 'Width' => '580', 'Height' => '200')).'</td>';
            echo '</tr>';
            echo '</table>';
            echo '</div>';

            //feedback

            echo '<div>&nbsp;</div><input type="checkbox" value="1" name="checkfeedback" onclick="javascript: if(this.checked){document.getElementById(\'option2\').style.display=\'block\';}else{document.getElementById(\'option2\').style.display=\'none\';}"/>&nbsp;'.get_lang('AddFeedback').'';
            echo '&nbsp;&nbsp;&nbsp;<span id="msg_error2" style="display:none;color:red"></span>';
            echo '<div id="option2" style="padding:4px; margin:5px; border:1px dotted; display:none;">';

            echo '<table border="0" style="font-weight:normal" align="center">';
            echo '<tr>';
            echo '<td colspan="2">'.get_lang('Feedback1').'</td>';
            echo '<td colspan="2">'.get_lang('Feedback2').'</td>';
            echo '<td colspan="2">'.get_lang('Feedback3').'</td>';
              echo '</tr>';
             echo '<tr>';
            echo '<td colspan="2"><textarea name="feedback1" cols="21" rows="4"></textarea></td>';
            echo '<td colspan="2"><textarea name="feedback2" cols="21" rows="4"></textarea></td>';
            echo '<td colspan="2"><textarea name="feedback3" cols="21" rows="4"></textarea></td>';
             echo '</tr>';
            echo '<tr>';
            echo '<td>'.get_lang('FProgress').':</td>';
            echo '<td><select name="fprogress1">
               <option value="0" selected>0</option>
               <option value="10">10</option>
               <option value="20">20</option>
               <option value="30">30</option>
               <option value="40">40</option>
               <option value="50">50</option>
               <option value="60">60</option>
               <option value="70">70</option>
               <option value="80">80</option>
               <option value="90">90</option>
               <option value="100">100</option>
               </select> %</td>';
            echo '<td>'.get_lang('FProgress').':</td>';
            echo '<td><select name="fprogress2">
               <option value="0" selected>0</option>
               <option value="10">10</option>
               <option value="20">20</option>
               <option value="30">30</option>
               <option value="40">40</option>
               <option value="50">50</option>
               <option value="60">60</option>
               <option value="70">70</option>
               <option value="80">80</option>
               <option value="90">90</option>
               <option value="100">100</option>
               </select> %</td>';
            echo '<td>'.get_lang('FProgress').':</td>';
            echo '<td><select name="fprogress3">
               <option value="0" selected>0</option>
               <option value="10">10</option>
               <option value="20">20</option>
               <option value="30">30</option>
               <option value="40">40</option>
               <option value="50">50</option>
               <option value="60">60</option>
               <option value="70">70</option>
               <option value="80">80</option>
               <option value="90">90</option>
               <option value="100">100</option>
               </select> %</td>';
              echo '</tr>';
            echo '</table>';
            echo '</div>';

            //time limit
            echo  '<div>&nbsp;</div><input type="checkbox" value="1" name="checktimelimit" onclick="javascript: if(this.checked){document.getElementById(\'option1\').style.display=\'block\';}else{document.getElementById(\'option1\').style.display=\'none\';}"/>&nbsp;'.get_lang('PutATimeLimit').'';
            echo  '&nbsp;&nbsp;&nbsp;<span id="msg_error1" style="display:none;color:red"></span>';
            echo  '<div id="option1" style="padding:4px; margin:5px; border:1px dotted; display:none;">';
            echo '<table width="100%" border="0" style="font-weight:normal">';
              echo '<tr>';
            echo '<td align="right">'.get_lang("StartDate").':</td>';
            echo '<td>';
            echo self::draw_date_picker('startdate_assig').' <input type="checkbox" name="initstartdate" value="1"> '.get_lang('Yes').'/'.get_lang('No').'';
            echo '</td>';
            echo '</tr>';
            echo '<tr>';
            echo '<td align="right">'.get_lang("EndDate").':</td>';
            echo '<td>';
            echo self::draw_date_picker('enddate_assig').' <input type="checkbox" name="initenddate" value="1"> '.get_lang('Yes').'/'.get_lang('No').'';
            echo '</td>';
            echo '</tr>';
            echo '<tr>';
            echo '<td align="right">'.get_lang('AllowLaterSends').':</td>';
            echo '<td><input type="checkbox" name="delayedsubmit" value="1"></td>';
            echo '</tr>';
            echo'</table>';
            echo '</div>';

            //other limit
            echo '<div>&nbsp;</div><input type="checkbox" value="1" name="checkotherlimit" onclick="javascript: if(this.checked){document.getElementById(\'option3\').style.display=\'block\';}else{document.getElementById(\'option3\').style.display=\'none\';}"/>&nbsp;'.get_lang('OtherSettings').'';
            echo '&nbsp;&nbsp;&nbsp;<span id="msg_error3" style="display:none;color:red"></span>';
            echo '<div id="option3" style="padding:4px; margin:5px; border:1px dotted; display:none;">';
            echo '<div style="font-weight:normal"; align="center">'.get_lang('NMaxWords').':&nbsp;<input type="text" name="max_text" size="3">&nbsp;&nbsp;'.get_lang('NMaxVersion').':&nbsp;<input type="text" name="max_version" size="3"></div>';
            echo '</div>';
            echo '</div>';
            //to define as an individual assignment
            echo '<div style= "border : 1px dotted; padding:4px; margin-top:20px;"><img src="../img/icons/22/wiki_assignment.png" title="'.get_lang('CreateAssignmentPage').'" alt="'.get_lang('CreateAssignmentPage').'"/>&nbsp;'.get_lang('DefineAssignmentPage').': <input type="checkbox" name="assignment" value="1"></div>'; // 1= teacher 2 =student
            //
            echo'</div>';

        }
        echo '</div>';
        echo '<div id="wikicontent">';
        api_disp_html_area('content', '', '', '', null, api_is_allowed_to_edit(null,true)
            ? array('ToolbarSet' => 'Wiki', 'Width' => '100%', 'Height' => '400')
            : array('ToolbarSet' => 'WikiStudent', 'Width' => '100%', 'Height' => '400', 'UserStatus' => 'student')
        );
        echo '<br/>';
        echo '<br/>';
        echo get_lang('Comments').':&nbsp;&nbsp;<input type="text" name="comment" size="40"><br /><br />';
        echo get_lang('Progress').':&nbsp;&nbsp;<select name="progress" id="progress">
           <option value="0" selected>0</option>
           <option value="10">10</option>
           <option value="20">20</option>
           <option value="30">30</option>
           <option value="40">40</option>
           <option value="50">50</option>
           <option value="60">60</option>
           <option value="70">70</option>
           <option value="80">80</option>
           <option value="90">90</option>
           <option value="100">100</option>
           </select> %';
        echo '<br/><br/>';
        echo '<input type="hidden" name="wpost_id" value="'.md5(uniqid(rand(), true)).'">';//prevent double post
        echo '<button class="save" type="submit" name="SaveWikiNew">'.get_lang('Save').'</button>';//for button icon. Don't change name (see fckeditor/editor/plugins/customizations/fckplugin_compressed.js and fckplugin.js
        echo '</div>';
        echo '</form>';
    }

    /**
     * This function displays a wiki entry
     * @author Patrick Cool <patrick.cool@ugent.be>, Ghent University
     * @author Juan Carlos Raña Trabado
     * @return html code
     **/
    function display_wiki_entry($newtitle)
    {
        $tbl_wiki = $this->tbl_wiki;
        $tbl_wiki_conf = $this->tbl_wiki_conf;
        $condition_session = $this->condition_session;
        $groupfilter = $this->groupfilter;
        $page = $this->page;

        $session_id = api_get_session_id();
        $course_id = api_get_course_int_id();

        if ($newtitle) {
            $pageMIX = $newtitle; //display the page after it is created
        } else {
            $pageMIX = $page;//display current page
        }

        $filter = null;
        if (isset($_GET['view']) && $_GET['view']) {
            $_clean['view']=(int)Database::escape_string($_GET['view']);
            $filter =' AND w.id="'.$_clean['view'].'"';
        }

        //first, check page visibility in the first page version
        $sql = 'SELECT * FROM '.$tbl_wiki.'
                WHERE
                    c_id = '.$course_id.' AND
                    reflink="'.Database::escape_string($pageMIX).'" AND
                   '.$groupfilter.$condition_session.'
              ORDER BY id ASC';
        $result=Database::query($sql);
        $row=Database::fetch_array($result);
        $KeyVisibility=$row['visibility'];

        // second, show the last version
        $sql = 'SELECT * FROM '.$tbl_wiki.' w , '.$tbl_wiki_conf.' wc
                WHERE
                    wc.c_id 	  = '.$course_id.' AND
                    w.c_id 		  = '.$course_id.' AND
                    wc.page_id	  = w.page_id AND
                    w.reflink	  = "'.Database::escape_string($pageMIX).'" AND
                    w.session_id  = '.$session_id.' AND
                    w.'.$groupfilter.'  '.$filter.'
                ORDER BY id DESC';

        $result = Database::query($sql);
        $row    = Database::fetch_array($result); // we do not need a while loop since we are always displaying the last version

        //log users access to wiki (page_id)
        if (!empty($row['page_id'])) {
            event_system(LOG_WIKI_ACCESS, LOG_WIKI_PAGE_ID, $row['page_id']);
        }
        //update visits
        if ($row['id']) {
            $sql='UPDATE '.$tbl_wiki.' SET hits=(hits+1) WHERE c_id = '.$course_id.' AND id='.$row['id'].'';
            Database::query($sql);
        }

        // if both are empty and we are displaying the index page then we display the default text.
        if ($row['content']=='' AND $row['title']=='' AND $page=='index') {
            if (api_is_allowed_to_edit(false,true) || api_is_platform_admin() || GroupManager :: is_user_in_group(api_get_user_id(), api_get_group_id())) {
                //Table structure for better export to pdf
                $default_table_for_content_Start='<table align="center" border="0"><tr><td align="center">';
                $default_table_for_content_End='</td></tr></table>';

                $content=$default_table_for_content_Start.sprintf(get_lang('DefaultContent'),api_get_path(WEB_IMG_PATH)).$default_table_for_content_End;
                $title=get_lang('DefaultTitle');
            } else {
                return Display::display_normal_message(get_lang('WikiStandBy'));
            }
        } else {
            $content = Security::remove_XSS(api_html_entity_decode($row['content']), COURSEMANAGERLOWSECURITY);
            $title= api_html_entity_decode($row['title']);
        }

        //assignment mode: identify page type
        $icon_assignment = null;
        if ($row['assignment']==1) {
            $icon_assignment = Display::return_icon('wiki_assignment.png', get_lang('AssignmentDescExtra'),'',ICON_SIZE_SMALL);
        } elseif($row['assignment']==2) {
            $icon_assignment = Display::return_icon('wiki_work.png', get_lang('AssignmentWork'),'',ICON_SIZE_SMALL);
        }

        //task mode
        $icon_task = null;
        if (!empty($row['task'])) {
            $icon_task=Display::return_icon('wiki_task.png', get_lang('StandardTask'),'',ICON_SIZE_SMALL);
        }

        //Show page. Show page to all users if isn't hide page. Mode assignments: if student is the author, can view
        if ($KeyVisibility=="1" || api_is_allowed_to_edit(false,true) || api_is_platform_admin() || ($row['assignment']==2 && $KeyVisibility=="0" && (api_get_user_id()==$row['user_id']))) {
            echo '<div id="wikititle">';

            // page action: protecting (locking) the page
            if(api_is_allowed_to_edit(false,true) || api_is_platform_admin()) {
                if (self::check_protect_page()==1) {
                    $protect_page= Display::return_icon('lock.png', get_lang('PageLockedExtra'),'',ICON_SIZE_SMALL);
                    $lock_unlock_protect='unlock';
                } else {
                    $protect_page= Display::return_icon('unlock.png', get_lang('PageUnlockedExtra'),'',ICON_SIZE_SMALL);
                    $lock_unlock_protect='lock';
                }
            }
            if ($row['id']) {
                echo '<span style="float:right;">';
                echo '<a href="index.php?action=showpage&amp;actionpage='.$lock_unlock_protect.'&amp;title='.api_htmlentities(urlencode($page)).'">'.$protect_page.'</a>';
                echo '</span>';
            }

            //page action: visibility
            if (api_is_allowed_to_edit(false,true) || api_is_platform_admin()) {
                if (self::check_visibility_page()==1) {
                    // TODO: FIX  This hides the icon eye closed to users of work they can see yours
                    //if(($row['assignment']==2 && $KeyVisibility=="0" && (api_get_user_id()==$row['user_id']))==false)
                    //{
                    //
                    // }
                    $visibility_page= Display::return_icon('visible.png', get_lang('ShowPageExtra'),'',ICON_SIZE_SMALL);
                    $lock_unlock_visibility='invisible';

                } else {
                    $visibility_page= Display::return_icon('invisible.png', get_lang('HidePageExtra'),'',ICON_SIZE_SMALL);
                    $lock_unlock_visibility='visible';
                }
            }

            if ($row['id']) {
                echo '<span style="float:right;">';
                echo '<a href="index.php?action=showpage&amp;actionpage='.$lock_unlock_visibility.'&amp;title='.api_htmlentities(urlencode($page)).'">'.$visibility_page.'</a>';
                echo '</span>';
            }

            //page action: notification
            if (api_is_allowed_to_session_edit()) {
                if (self::check_notify_page($page)==1) {
                    $notify_page= Display::return_icon('messagebox_info.png', get_lang('NotifyByEmail'),'',ICON_SIZE_SMALL);
                    $lock_unlock_notify_page='unlocknotify';
                } else {
                    $notify_page= Display::return_icon('mail.png', get_lang('CancelNotifyByEmail'),'',ICON_SIZE_SMALL);
                    $lock_unlock_notify_page='locknotify';
                }
            }

            echo '<span style="float:right;">';
            echo '<a href="index.php?action=showpage&amp;actionpage='.$lock_unlock_notify_page.'&amp;title='.api_htmlentities(urlencode($page)).'">'.$notify_page.'</a>';
            echo '</span>';

             //ONly available if row['id'] is set
            if ($row['id']) {
                //page action: export to pdf
                echo '<span style="float:right;padding-top:5px;">';
                echo '<form name="form_export2PDF" method="post" action="index.php">';
                echo '<input type="hidden" name="action" value="export_to_pdf">';
                echo '<input type="hidden" name="wiki_id" value="'.$row['id'].'">';
                echo '<input type="image" src="../img/icons/22/pdf.png" border ="0" title="'.get_lang('ExportToPDF').'" alt="'.get_lang('ExportToPDF').'" style=" width:22px; border:none; margin-top: -9px">';
                echo '</form>';
                echo '</span>';

                //page action: copy last version to doc area
                if (api_is_allowed_to_edit(false,true) || api_is_platform_admin()) {
                    echo '<span style="float:right;">';
                    echo '<form name="form_export2DOC" method="post" action="index.php" >';
                    echo '<input type=hidden name="export2DOC" value="export2doc">';
                    echo '<input type=hidden name="doc_id" value="'.$row['id'].'">';
                    echo '<input type="image" src="../img/icons/22/export_to_documents.png" border ="0" title="'.get_lang('ExportToDocArea').'" alt="'.get_lang('ExportToDocArea').'" style=" width:22px; border:none; margin-top: -6px">';
                    echo '</form>';
                    echo '</span>';
                }
            }
            //export to print
            ?>
            <script>
            function goprint() {
                var a = window.open('','','width=800,height=600');
                a.document.open("text/html");
                a.document.write(document.getElementById('wikicontent').innerHTML);
                a.document.close();
                a.print();
            }
            </script>
            <?php
            echo '<span style="float:right; cursor: pointer;">';
            echo Display::return_icon('printer.png', get_lang('Print'),array('onclick' => "javascript: goprint();"),ICON_SIZE_SMALL);
            echo '</span>';

            if (empty($title)) {
                $title=get_lang('DefaultTitle');
            }

            if (self::wiki_exist($title)) {
                echo $icon_assignment.'&nbsp;'.$icon_task.'&nbsp;'.api_htmlentities($title);
            } else {
                echo api_htmlentities($title);
            }

            echo '</div>';
            echo '<div id="wikicontent">'. self::make_wiki_link_clickable(
                self::detect_external_link(
                    self::detect_anchor_link(
                        self::detect_mail_link(
                            self::detect_ftp_link(
                                self::detect_irc_link(
                                    self::detect_news_link($content)
                                )
                            )
                        )
                    )
                )
            ).'</div>';
            echo '<div id="wikifooter">'.get_lang('Progress').': '.$row['progress'].'%&nbsp;&nbsp;&nbsp;'.get_lang('Rating').': '.$row['score'].'&nbsp;&nbsp;&nbsp;'.get_lang('Words').': '.self::word_count($content).'</div>';
        } //end filter visibility
    }

    /**
     * This function counted the words in a document. Thanks Adeel Khan
     * @param   string  Document's text
     * @return  int     Number of words
     */
    function word_count($document)
    {
        $search = array(
        '@<script[^>]*?>.*?</script>@si',
        '@<style[^>]*?>.*?</style>@siU',
        '@<div id="player.[^>]*?>.*?</div>@',
        '@<![\s\S]*?--[ \t\n\r]*>@'
        );

        $document = preg_replace($search, '', $document);

          # strip all html tags
          $wc = strip_tags($document);
          $wc = html_entity_decode($wc, ENT_NOQUOTES, 'UTF-8');// TODO:test also old html_entity_decode(utf8_encode($wc))

          # remove 'words' that don't consist of alphanumerical characters or punctuation. And fix accents and some letters
          $pattern = "#[^(\w|\d|\'|\"|\.|\!|\?|;|,|\\|\/|\-|:|\&|@|á|é|í|ó|ú|à|è|ì|ò|ù|ä|ë|ï|ö|ü|Á|É|Í|Ó|Ú|À|È|Ò|Ù|Ä|Ë|Ï|Ö|Ü|â|ê|î|ô|û|Â|Ê|Î|Ô|Û|ñ|Ñ|ç|Ç)]+#";
          $wc = trim(preg_replace($pattern, " ", $wc));

          # remove one-letter 'words' that consist only of punctuation
          $wc = trim(preg_replace("#\s*[(\'|\"|\.|\!|\?|;|,|\\|\/|\-|:|\&|@)]\s*#", " ", $wc));

          # remove superfluous whitespace
          $wc = preg_replace("/\s\s+/", " ", $wc);

          # split string into an array of words
          $wc = explode(" ", $wc);

          # remove empty elements
          $wc = array_filter($wc);

          # return the number of words
          return count($wc);
    }

    /**
     * This function checks if wiki title exist
     */
    function wiki_exist($title)
    {
        $tbl_wiki = $this->tbl_wiki;
        $groupfilter = $this->groupfilter;
        $condition_session = $this->condition_session;

        $course_id = api_get_course_int_id();

        $sql='SELECT id FROM '.$tbl_wiki.'
              WHERE
                c_id = '.$course_id.' AND
                title="'.Database::escape_string($title).'" AND
                '.$groupfilter.$condition_session.'
              ORDER BY id ASC';
        $result=Database::query($sql);
        $cant=Database::num_rows($result);
        if ($cant>0) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Checks if this navigation tab has to be set to active
     * @author Patrick Cool <patrick.cool@ugent.be>, Ghent University
     * @return html code
     */
    function is_active_navigation_tab($paramwk)
    {
        if (isset($_GET['action']) && $_GET['action'] == $paramwk) {
            return ' class="active"';
        }
    }

    /**
     * Lock add pages
     * @author Juan Carlos Raña <herodoto@telefonica.net>
     * return current database status of protect page and change it if get action
     */
    function check_addnewpagelock()
    {
        $tbl_wiki = $this->tbl_wiki;
        $condition_session = $this->condition_session;
        $groupfilter = $this->groupfilter;
        $course_id = api_get_course_int_id();

        $sql='SELECT * FROM '.$tbl_wiki.' WHERE c_id = '.$course_id.' AND '.$groupfilter.$condition_session.' ORDER BY id ASC';
        $result=Database::query($sql);
        $row=Database::fetch_array($result);
        $status_addlock=$row['addlock'];
        //change status
        if (api_is_allowed_to_edit(false,true) || api_is_platform_admin()) {
            if (isset($_GET['actionpage']) && $_GET['actionpage'] =='lockaddnew' && $status_addlock==1) {
                $status_addlock=0;
            }
            if (isset($_GET['actionpage']) && $_GET['actionpage'] =='unlockaddnew' && $status_addlock==0) {
                $status_addlock=1;
            }

            Database::query('UPDATE '.$tbl_wiki.' SET addlock="'.Database::escape_string($status_addlock).'" WHERE c_id = '.$course_id.' AND '.$groupfilter.$condition_session.'');

            $sql='SELECT * FROM '.$tbl_wiki.' WHERE c_id = '.$course_id.' AND '.$groupfilter.$condition_session.' ORDER BY id ASC';
            $result=Database::query($sql);
            $row=Database::fetch_array($result);
        }
        return $row['addlock'];

    }

    /**
     * Protect page
     * @author Juan Carlos Raña <herodoto@telefonica.net>
     * return current database status of protect page and change it if get action
     */
    function check_protect_page()
    {
        $tbl_wiki = $this->tbl_wiki;
        $condition_session = $this->condition_session;
        $groupfilter = $this->groupfilter;
        $page = $this->page;

        $course_id = api_get_course_int_id();
        $sql='SELECT * FROM '.$tbl_wiki.'
              WHERE
                c_id = '.$course_id.' AND
                reflink="'.Database::escape_string($page).'" AND
                '.$groupfilter.$condition_session.'
              ORDER BY id ASC';

        $result=Database::query($sql);
        $row=Database::fetch_array($result);
        $status_editlock=$row['editlock'];
        $id = $row['id'];

        ///change status
        if (api_is_allowed_to_edit(false,true) || api_is_platform_admin()) {
            if (isset($_GET['actionpage']) && $_GET['actionpage']=='lock' && $status_editlock==0) {
                $status_editlock=1;
            }
            if (isset($_GET['actionpage']) && $_GET['actionpage']=='unlock' && $status_editlock==1) {
                $status_editlock=0;
            }
            $sql='UPDATE '.$tbl_wiki.' SET editlock="'.Database::escape_string($status_editlock).'" WHERE c_id = '.$course_id.' AND id="'.$id.'"';
            Database::query($sql);
            $sql='SELECT * FROM '.$tbl_wiki.'
                  WHERE
                    c_id = '.$course_id.' AND
                    reflink="'.Database::escape_string($page).'" AND
                    '.$groupfilter.$condition_session.'
                  ORDER BY id ASC';
            $result=Database::query($sql);
            $row=Database::fetch_array($result);
        }
        //show status
        return $row['editlock'];
    }

    /**
     * Visibility page
     * @author Juan Carlos Raña <herodoto@telefonica.net>
     * return current database status of visibility and change it if get action
     */
    function check_visibility_page()
    {
        $tbl_wiki = $this->tbl_wiki;
        $page = $this->page;
        $condition_session = $this->condition_session;
        $groupfilter = $this->groupfilter;
        $course_id = api_get_course_int_id();

        $sql='SELECT * FROM '.$tbl_wiki.'
             WHERE c_id = '.$course_id.' AND reflink="'.Database::escape_string($page).'" AND '.$groupfilter.$condition_session.'
             ORDER BY id ASC';
        $result=Database::query($sql);
        $row=Database::fetch_array($result);
        $status_visibility=$row['visibility'];
        //change status
        if (api_is_allowed_to_edit(false,true) || api_is_platform_admin()) {
            if (isset($_GET['actionpage']) && $_GET['actionpage']=='visible' && $status_visibility==0) {
                $status_visibility=1;

            }
            if (isset($_GET['actionpage']) && $_GET['actionpage']=='invisible' && $status_visibility==1) {
                $status_visibility=0;
            }

            $sql='UPDATE '.$tbl_wiki.' SET visibility="'.Database::escape_string($status_visibility).'"
                 WHERE c_id = '.$course_id.' AND reflink="'.Database::escape_string($page).'" AND '.$groupfilter.$condition_session;
            Database::query($sql);

            //Although the value now is assigned to all (not only the first), these three lines remain necessary. They do that by changing the page state is made when you press the button and not have to wait to change his page
            $sql = 'SELECT * FROM '.$tbl_wiki.'
                    WHERE c_id = '.$course_id.' AND reflink="'.Database::escape_string($page).'" AND '.$groupfilter.$condition_session.'
                    ORDER BY id ASC';
            $result=Database::query($sql);
            $row = Database::fetch_array($result);
        }

        if (empty($row['id'])) {
            $row['visibility']= 1;
        }

        //show status
        return $row['visibility'];
    }

    /**
     * Visibility discussion
     * @author Juan Carlos Raña <herodoto@telefonica.net>
     * @return int current database status of discuss visibility and change it if get action page
     */
    function check_visibility_discuss()
    {
        $tbl_wiki = $this->tbl_wiki;
        $page = $this->page;
        $condition_session = $this->condition_session;
        $groupfilter = $this->groupfilter;

        $course_id = api_get_course_int_id();

        $sql = 'SELECT * FROM '.$tbl_wiki.'
                WHERE c_id = '.$course_id.' AND reflink="'.Database::escape_string($page).'" AND '.$groupfilter.$condition_session.'
                ORDER BY id ASC';
        $result=Database::query($sql);
        $row=Database::fetch_array($result);

        $status_visibility_disc=$row['visibility_disc'];

        //change status
        if (api_is_allowed_to_edit(false,true) || api_is_platform_admin()) {
            if (isset($_GET['actionpage']) && $_GET['actionpage'] =='showdisc' && $status_visibility_disc==0) {
                $status_visibility_disc=1;
            }
            if (isset($_GET['actionpage']) && $_GET['actionpage'] =='hidedisc' && $status_visibility_disc==1) {
                $status_visibility_disc=0;
            }

            $sql = 'UPDATE '.$tbl_wiki.' SET visibility_disc="'.Database::escape_string($status_visibility_disc).'"
                    WHERE c_id = '.$course_id.' AND reflink="'.Database::escape_string($page).'" AND '.$groupfilter.$condition_session;
            Database::query($sql);

           //Although the value now is assigned to all (not only the first), these three lines remain necessary. They do that by changing the page state is made when you press the button and not have to wait to change his page
            $sql = 'SELECT * FROM '.$tbl_wiki.'
                    WHERE
                        c_id = '.$course_id.' AND
                        reflink="'.Database::escape_string($page).'" AND
                        '.$groupfilter.$condition_session.'
                    ORDER BY id ASC';
            $result=Database::query($sql);
            $row=Database::fetch_array($result);
        }
         return $row['visibility_disc'];
    }

     /**
     * Lock add discussion
     * @author Juan Carlos Raña <herodoto@telefonica.net>
     * @return int current database status of lock dicuss and change if get action
     */
    function check_addlock_discuss()
    {
        $tbl_wiki = $this->tbl_wiki;
        $page = $this->page;
        $condition_session = $this->condition_session;
        $groupfilter = $this->groupfilter;
        $course_id = api_get_course_int_id();

        $sql = 'SELECT * FROM '.$tbl_wiki.'
                WHERE c_id = '.$course_id.' AND reflink="'.Database::escape_string($page).'" AND '.$groupfilter.$condition_session.'
                ORDER BY id ASC';
        $result=Database::query($sql);
        $row=Database::fetch_array($result);

        $status_addlock_disc=$row['addlock_disc'];

        //change status
        if (api_is_allowed_to_edit() || api_is_platform_admin()) {
            if (isset($_GET['actionpage']) && $_GET['actionpage'] =='lockdisc' && $status_addlock_disc==0) {
                $status_addlock_disc=1;
            }
            if (isset($_GET['actionpage']) && $_GET['actionpage'] =='unlockdisc' && $status_addlock_disc==1) {
                $status_addlock_disc=0;
            }

            $sql='UPDATE '.$tbl_wiki.' SET addlock_disc="'.Database::escape_string($status_addlock_disc).'"
                  WHERE c_id = '.$course_id.' AND reflink="'.Database::escape_string($page).'" AND '.$groupfilter.$condition_session;
            Database::query($sql);

              //Although the value now is assigned to all (not only the first), these three lines remain necessary. They do that by changing the page state is made when you press the button and not have to wait to change his page
            $sql = 'SELECT * FROM '.$tbl_wiki.'
                    WHERE c_id = '.$course_id.' AND reflink="'.Database::escape_string($page).'" AND '.$groupfilter.$condition_session.'
                    ORDER BY id ASC';
            $result=Database::query($sql);
            $row=Database::fetch_array($result);
        }

        return $row['addlock_disc'];

    }

    /**
     * Lock rating discussion
     * @author Juan Carlos Raña <herodoto@telefonica.net>
     * @return  int  current database status of rating discuss and change it if get action
     */
    function check_ratinglock_discuss()
    {
        $tbl_wiki = $this->tbl_wiki;
        $page = $this->page;
        $condition_session = $this->condition_session;
        $groupfilter = $this->groupfilter;
        $course_id = api_get_course_int_id();

        $sql='SELECT * FROM '.$tbl_wiki.'
              WHERE  c_id = '.$course_id.' AND reflink="'.Database::escape_string($page).'" AND '.$groupfilter.$condition_session.' ORDER BY id ASC';
        $result=Database::query($sql);
        $row=Database::fetch_array($result);

        $status_ratinglock_disc=$row['ratinglock_disc'];

        //change status
        if (api_is_allowed_to_edit(false,true) || api_is_platform_admin()) {
            if (isset($_GET['actionpage']) && $_GET['actionpage'] =='lockrating' && $status_ratinglock_disc==0) {
                $status_ratinglock_disc=1;
            }
            if (isset($_GET['actionpage']) && $_GET['actionpage'] =='unlockrating' && $status_ratinglock_disc==1) {
                $status_ratinglock_disc=0;
            }

            $sql='UPDATE '.$tbl_wiki.' SET ratinglock_disc="'.Database::escape_string($status_ratinglock_disc).'"
                 WHERE c_id = '.$course_id.' AND reflink="'.Database::escape_string($page).'" AND '.$groupfilter.$condition_session; //Visibility. Value to all,not only for the first
            Database::query($sql);

              //Although the value now is assigned to all (not only the first), these three lines remain necessary. They do that by changing the page state is made when you press the button and not have to wait to change his page
            $sql='SELECT * FROM '.$tbl_wiki.'
                  WHERE c_id = '.$course_id.' AND reflink="'.Database::escape_string($page).'" AND '.$groupfilter.$condition_session.' ORDER BY id ASC';
            $result=Database::query($sql);
            $row=Database::fetch_array($result);
        }

        return $row['ratinglock_disc'];
    }

    /**
     * Notify page changes
     * @author Juan Carlos Raña <herodoto@telefonica.net>
     * @return int the current notification status
     */
    function check_notify_page($reflink)
    {
        $tbl_wiki = $this->tbl_wiki;
        $tbl_wiki_mailcue = $this->tbl_wiki_mailcue;
        $condition_session = $this->condition_session;
        $groupfilter = $this->groupfilter;
        $groupId = api_get_group_id();
        $session_id = api_get_session_id();
        $course_id = api_get_course_int_id();
        $userId = api_get_user_id();

        $sql = 'SELECT * FROM '.$tbl_wiki.'
                WHERE c_id = '.$course_id.' AND reflink="'.$reflink.'" AND '.$groupfilter.$condition_session.' ORDER BY id ASC';
        $result=Database::query($sql);
        $row=Database::fetch_array($result);
        $id = $row['id'];
        $sql='SELECT * FROM '.$tbl_wiki_mailcue.'
              WHERE c_id = '.$course_id.' AND id="'.$id.'" AND user_id="'.api_get_user_id().'" AND type="P"';
        $result=Database::query($sql);
        $row=Database::fetch_array($result);

        $idm = $row['id'];

        if (empty($idm)) {
            $status_notify=0;
        } else {
            $status_notify=1;
        }

        // Change status
        if (isset($_GET['actionpage']) && $_GET['actionpage'] =='locknotify' && $status_notify==0) {
            $sql = "SELECT id FROM $tbl_wiki_mailcue  WHERE c_id = $course_id AND id = $id AND user_id = $userId";
            $result = Database::query($sql);
            $exist = false;
            if (Database::num_rows($result)) {
                $exist = true;
            }
            if ($exist == false) {
                $sql="INSERT INTO ".$tbl_wiki_mailcue." (c_id, id, user_id, type, group_id, session_id) VALUES
                ($course_id, '".$id."','".api_get_user_id()."','P','".$groupId."','".$session_id."')";
                Database::query($sql);
            }

            $status_notify=1;
        }
        if (isset($_GET['actionpage']) && $_GET['actionpage'] =='unlocknotify' && $status_notify==1) {
            $sql = 'DELETE FROM '.$tbl_wiki_mailcue.'
                    WHERE id="'.$id.'" AND user_id="'.api_get_user_id().'" AND type="P" AND c_id = '.$course_id;
            Database::query($sql);

            $status_notify=0;
        }
        //show status
        return $status_notify;
    }

    /**
     * Notify discussion changes
     * @author Juan Carlos Raña <herodoto@telefonica.net>
     * @return int current database status of rating discuss and change it if get action
     */
    function check_notify_discuss($reflink)
    {
        $tbl_wiki_mailcue = $this->tbl_wiki_mailcue;
        $tbl_wiki = $this->tbl_wiki;
        $condition_session = $this->condition_session;
        $groupfilter = $this->groupfilter;

        $course_id = api_get_course_int_id();
        $groupId = api_get_group_id();
        $session_id = api_get_session_id();

        $sql = 'SELECT * FROM '.$tbl_wiki.'
                WHERE c_id = '.$course_id.' AND reflink="'.$reflink.'" AND '.$groupfilter.$condition_session.'
                ORDER BY id ASC';
        $result=Database::query($sql);
        $row=Database::fetch_array($result);
        $id=$row['id'];
        $sql = 'SELECT * FROM '.$tbl_wiki_mailcue.'
             WHERE c_id = '.$course_id.' AND id="'.$id.'" AND user_id="'.api_get_user_id().'" AND type="D"';
        $result=Database::query($sql);
        $row=Database::fetch_array($result);

        $idm=$row['id'];

        if (empty($idm)) {
            $status_notify_disc=0;
        } else {
            $status_notify_disc=1;
        }

        //change status
        if (isset($_GET['actionpage']) && $_GET['actionpage'] =='locknotifydisc' && $status_notify_disc==0) {
            $sql="INSERT INTO ".$tbl_wiki_mailcue." (c_id, id, user_id, type, group_id, session_id) VALUES
            ($course_id, '".$id."','".api_get_user_id()."','D','".$groupId."','".$session_id."')";
            Database::query($sql);
            $status_notify_disc=1;
        }
        if (isset($_GET['actionpage']) && $_GET['actionpage'] =='unlocknotifydisc' && $status_notify_disc==1) {
            $sql = 'DELETE FROM '.$tbl_wiki_mailcue.'
                    WHERE c_id = '.$course_id.' AND id="'.$id.'" AND user_id="'.api_get_user_id().'" AND type="D" AND c_id = '.$course_id;
            Database::query($sql);
            $status_notify_disc=0;
        }

        return $status_notify_disc;
    }

    /**
     * Notify all changes
     * @author Juan Carlos Raña <herodoto@telefonica.net>
     */
    function check_notify_all()
    {
        $tbl_wiki_mailcue = $this->tbl_wiki_mailcue;
        $course_id = api_get_course_int_id();
        $groupId = api_get_group_id();
        $session_id=api_get_session_id();

        $sql = 'SELECT * FROM '.$tbl_wiki_mailcue.'
                WHERE  c_id = '.$course_id.' AND user_id="'.api_get_user_id().'" AND type="F" AND group_id="'.$groupId.'" AND session_id="'.$session_id.'"';
        $result=Database::query($sql);
        $row=Database::fetch_array($result);

        $idm=$row['user_id'];

        if (empty($idm)) {
            $status_notify_all=0;
        } else {
            $status_notify_all=1;
        }

        //change status
        if (isset($_GET['actionpage']) && $_GET['actionpage'] =='locknotifyall' && $status_notify_all==0) {
            $sql="INSERT INTO ".$tbl_wiki_mailcue." (c_id, user_id, type, group_id, session_id) VALUES
            ($course_id, '".api_get_user_id()."','F','".$groupId."','".$session_id."')";
            Database::query($sql);
            $status_notify_all=1;
        }

        if (isset($_GET['actionpage']) && isset($_GET['actionpage']) && $_GET['actionpage']  =='unlocknotifyall' && $status_notify_all==1) {
            $sql ='DELETE FROM '.$tbl_wiki_mailcue.'
                   WHERE  c_id = '.$course_id.' AND user_id="'.api_get_user_id().'" AND type="F" AND group_id="'.$groupId.'" AND session_id="'.$session_id.'" AND c_id = '.$course_id;
            Database::query($sql);
            $status_notify_all=0;
        }

        //show status
        return $status_notify_all;
    }

    /**
     * Sends pending e-mails
     */
    function check_emailcue($id_or_ref, $type, $lastime='', $lastuser='')
    {
        $tbl_wiki_mailcue = $this->tbl_wiki_mailcue;
        $tbl_wiki = $this->tbl_wiki;
        $condition_session = $this->condition_session;
        $groupfilter = $this->groupfilter;
        $_course = $this->courseInfo;
        $groupId = api_get_group_id();
        $session_id=api_get_session_id();
        $course_id = api_get_course_int_id();

        $group_properties  = GroupManager :: get_group_properties($groupId);
        $group_name = $group_properties['name'];
        $allow_send_mail = false; //define the variable to below
        $email_assignment = null;
        if ($type=='P') {
            //if modifying a wiki page
            //first, current author and time
            //Who is the author?
            $userinfo = api_get_user_info($lastuser);
            $email_user_author = get_lang('EditedBy').': '.$userinfo['complete_name'];

            //When ?
            $year = substr($lastime, 0, 4);
            $month = substr($lastime, 5, 2);
            $day = substr($lastime, 8, 2);
            $hours=substr($lastime, 11,2);
            $minutes=substr($lastime, 14,2);
            $seconds=substr($lastime, 17,2);
            $email_date_changes=$day.' '.$month.' '.$year.' '.$hours.":".$minutes.":".$seconds;

            //second, extract data from first reg
            $sql = 'SELECT * FROM '.$tbl_wiki.'
                    WHERE  c_id = '.$course_id.' AND reflink="'.$id_or_ref.'" AND '.$groupfilter.$condition_session.'
                    ORDER BY id ASC';
            $result=Database::query($sql);
            $row=Database::fetch_array($result);

            $id=$row['id'];
            $email_page_name=$row['title'];
            if ($row['visibility']==1) {
                $allow_send_mail=true; //if visibility off - notify off
                $sql = 'SELECT * FROM '.$tbl_wiki_mailcue.'
                        WHERE c_id = '.$course_id.' AND id="'.$id.'" AND type="'.$type.'" OR type="F" AND group_id="'.$groupId.'" AND session_id="'.$session_id.'"'; //type: P=page, D=discuss, F=full.
                $result=Database::query($sql);
                $emailtext=get_lang('EmailWikipageModified').' <strong>'.$email_page_name.'</strong> '.get_lang('Wiki');
            }
        } elseif ($type=='D') {
            //if added a post to discuss

            //first, current author and time
            //Who is the author of last message?
            $userinfo = api_get_user_info($lastuser);
            $email_user_author = get_lang('AddedBy').': '.$userinfo['complete_name'];

            //When ?
            $year = substr($lastime, 0, 4);
            $month = substr($lastime, 5, 2);
            $day = substr($lastime, 8, 2);
            $hours=substr($lastime, 11,2);
            $minutes=substr($lastime, 14,2);
            $seconds=substr($lastime, 17,2);
            $email_date_changes=$day.' '.$month.' '.$year.' '.$hours.":".$minutes.":".$seconds;

            //second, extract data from first reg

            $id=$id_or_ref; //$id_or_ref is id from tblwiki

            $sql='SELECT * FROM '.$tbl_wiki.' WHERE c_id = '.$course_id.' AND id="'.$id.'" ORDER BY id ASC';

            $result=Database::query($sql);
            $row=Database::fetch_array($result);

            $email_page_name=$row['title'];
            if ($row['visibility_disc']==1) {
                $allow_send_mail=true; //if visibility off - notify off
                $sql = 'SELECT * FROM '.$tbl_wiki_mailcue.'
                        WHERE c_id = '.$course_id.' AND id="'.$id.'" AND type="'.$type.'" OR type="F" AND group_id="'.$groupId.'" AND session_id="'.$session_id.'"'; //type: P=page, D=discuss, F=full
                $result=Database::query($sql);
                $emailtext=get_lang('EmailWikiPageDiscAdded').' <strong>'.$email_page_name.'</strong> '.get_lang('Wiki');
            }
        } elseif($type=='A') {
            //for added pages
            $id=0; //for tbl_wiki_mailcue
            $sql = 'SELECT * FROM '.$tbl_wiki.'
                    WHERE c_id = '.$course_id.'
                    ORDER BY id DESC'; //the added is always the last

            $result=Database::query($sql);
            $row=Database::fetch_array($result);
            $email_page_name=$row['title'];

            //Who is the author?
            $userinfo = api_get_user_info($row['user_id']);
            $email_user_author= get_lang('AddedBy').': '.$userinfo['complete_name'];

            //When ?
            $year = substr($row['dtime'], 0, 4);
            $month = substr($row['dtime'], 5, 2);
            $day = substr($row['dtime'], 8, 2);
            $hours=substr($row['dtime'], 11,2);
            $minutes=substr($row['dtime'], 14,2);
            $seconds=substr($row['dtime'], 17,2);
            $email_date_changes=$day.' '.$month.' '.$year.' '.$hours.":".$minutes.":".$seconds;

            if($row['assignment']==0) {
                $allow_send_mail=true;
            } elseif($row['assignment']==1) {
                $email_assignment=get_lang('AssignmentDescExtra').' ('.get_lang('AssignmentMode').')';
                $allow_send_mail=true;
            } elseif($row['assignment']==2) {
                $allow_send_mail=false; //Mode tasks: avoids notifications to all users about all users
            }

            $sql = 'SELECT * FROM '.$tbl_wiki_mailcue.'
                    WHERE c_id = '.$course_id.' AND  id="'.$id.'" AND type="F" AND group_id="'.$groupId.'" AND session_id="'.$session_id.'"'; //type: P=page, D=discuss, F=full
            $result=Database::query($sql);

            $emailtext=get_lang('EmailWikiPageAdded').' <strong>'.$email_page_name.'</strong> '.get_lang('In').' '. get_lang('Wiki');
        } elseif($type=='E') {
            $id=0;

            $allow_send_mail=true;

            //Who is the author?
            $userinfo = api_get_user_info(api_get_user_id());	//current user
            $email_user_author = get_lang('DeletedBy').': '.$userinfo['complete_name'];
            //When ?
            $today = date('r');		//current time
            $email_date_changes=$today;

            $sql = 'SELECT * FROM '.$tbl_wiki_mailcue.'
                    WHERE
                        c_id = '.$course_id.' AND
                        id="'.$id.'" AND type="F" AND
                        group_id="'.$groupId.'" AND
                        session_id="'.$session_id.'"'; //type: P=page, D=discuss, F=wiki
            $result = Database::query($sql);
            $emailtext = get_lang('EmailWikipageDedeleted');
        }
        ///make and send email
        if ($allow_send_mail) {
            while ($row = Database::fetch_array($result)) {
                $userinfo = api_get_user_info($row['user_id']);	//$row['user_id'] obtained from tbl_wiki_mailcue
                $name_to = $userinfo['complete_name'];
                $email_to = $userinfo['email'];
                $sender_name = api_get_setting('emailAdministrator');
                $sender_email = api_get_setting('emailAdministrator');
                $email_subject = get_lang('EmailWikiChanges').' - '.$_course['official_code'];
                $email_body = get_lang('DearUser').' '.api_get_person_name($userinfo['firstname'], $userinfo['lastname']).',<br /><br />';
                if($session_id==0){
                    $email_body .= $emailtext.' <strong>'.$_course['name'].' - '.$group_name.'</strong><br /><br /><br />';
                }else{
                    $email_body .= $emailtext.' <strong>'.$_course['name'].' ('.api_get_session_name(api_get_session_id()).') - '.$group_name.'</strong><br /><br /><br />';
                }
                $email_body .= $email_user_author.' ('.$email_date_changes.')<br /><br /><br />';
                $email_body .= $email_assignment.'<br /><br /><br />';
                $email_body .= '<font size="-2">'.get_lang('EmailWikiChangesExt_1').': <strong>'.get_lang('NotifyChanges').'</strong><br />';
                $email_body .= get_lang('EmailWikiChangesExt_2').': <strong>'.get_lang('NotNotifyChanges').'</strong></font><br />';
                @api_mail_html($name_to, $email_to, $email_subject, $email_body, $sender_name, $sender_email);
            }
        }
    }

    /**
     * Function export last wiki page version to document area
     * @author Juan Carlos Raña <herodoto@telefonica.net>
     */
    function export2doc($doc_id)
    {
        $_course = $this->courseInfo;
        $groupId 	= api_get_group_id();
        $data       = self::get_wiki_data($doc_id);

        if (empty($data)) {
            return false;
        }
        $wikiTitle 		= $data['title'];
        $wikiContents 	= $data['content'];

        $template =
            '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
            <html xmlns="http://www.w3.org/1999/xhtml" xml:lang="{LANGUAGE}" lang="{LANGUAGE}">
            <head>
            <title>{TITLE}</title>
            <meta http-equiv="Content-Type" content="text/html; charset={ENCODING}" />
            <style type="text/css" media="screen, projection">
            /*<![CDATA[*/
            {CSS}
            /*]]>*/
            </style>
            {ASCIIMATHML_SCRIPT}</head>
            <body dir="{TEXT_DIRECTION}">
            {CONTENT}
            </body>
            </html>';

        $css_file = api_get_path(TO_SYS, WEB_CSS_PATH).api_get_setting('stylesheets').'/default.css';
        if (file_exists($css_file)) {
            $css = @file_get_contents($css_file);
        } else {
            $css = '';
        }
        // Fixing some bugs in css files.
        $root_rel = api_get_path(REL_PATH);
        $css_path = 'main/css/';
        $theme = api_get_setting('stylesheets').'/';
        $css = str_replace('behavior:url("/main/css/csshover3.htc");', '', $css);
        $css = str_replace('main/', $root_rel.'main/', $css);
        $css = str_replace('images/', $root_rel.$css_path.$theme.'images/', $css);
        $css = str_replace('../../img/', $root_rel.'main/img/', $css);

        $asciimathmal_script = (api_contains_asciimathml($wikiContents) || api_contains_asciisvg($wikiContents))
           ? '<script src="'.api_get_path(TO_REL, SCRIPT_ASCIIMATHML).'" type="text/javascript"></script>'."\n" : '';

        $template = str_replace(array('{LANGUAGE}', '{ENCODING}', '{TEXT_DIRECTION}', '{TITLE}', '{CSS}', '{ASCIIMATHML_SCRIPT}'),
            array(api_get_language_isocode(), api_get_system_encoding(), api_get_text_direction(), $wikiTitle, $css, $asciimathmal_script),
            $template);

        if (0 != $groupId) {
            $groupPart = '_group' . $groupId; // and add groupId to put the same document title in different groups
            $group_properties  = GroupManager :: get_group_properties($groupId);
            $groupPath = $group_properties['directory'];
        } else {
            $groupPart = '';
            $groupPath ='';
        }

        $exportDir = api_get_path(SYS_COURSE_PATH).api_get_course_path(). '/document'.$groupPath;
        $exportFile = replace_dangerous_char($wikiTitle, 'strict') . $groupPart;

        //$clean_wikiContents = trim(preg_replace("/\[\[|\]\]/", " ", $wikiContents));
        //$array_clean_wikiContents= explode('|', $clean_wikiContents);
        $wikiContents = trim(preg_replace("/\[[\[]?([^\]|]*)[|]?([^|\]]*)\][\]]?/", "$1", $wikiContents));
        //TODO: put link instead of title

        $wikiContents = str_replace('{CONTENT}', $wikiContents, $template);

        // replace relative path by absolute path for courses, so you can see items into this page wiki (images, mp3, etc..) exported in documents
        if (api_strpos($wikiContents,'../../courses/') !== false) {
            $web_course_path = api_get_path(WEB_COURSE_PATH);
            $wikiContents = str_replace('../../courses/',$web_course_path,$wikiContents);
        }

        $i = 1;
        while ( file_exists($exportDir . '/' .$exportFile.'_'.$i.'.html') )
            $i++; //only export last version, but in new export new version in document area
        $wikiFileName = $exportFile . '_' . $i . '.html';
        $exportPath = $exportDir . '/' . $wikiFileName;
        file_put_contents( $exportPath, $wikiContents );
        $doc_id = add_document($_course, $groupPath.'/'.$wikiFileName, 'file', filesize($exportPath), $wikiTitle);
        api_item_property_update($_course, TOOL_DOCUMENT, $doc_id, 'DocumentAdded', api_get_user_id(), $groupId);

        return $doc_id;
        // TODO: link to go document area
    }

    /**
     * Exports the wiki page to PDF
     */
    function export_to_pdf($id, $course_code)
    {
        require_once api_get_path(LIBRARY_PATH).'pdf.lib.php';
        $data        = self::get_wiki_data($id);
        $content_pdf = api_html_entity_decode($data['content'], ENT_QUOTES, api_get_system_encoding());

        //clean wiki links
        $content_pdf=trim(preg_replace("/\[[\[]?([^\]|]*)[|]?([^|\]]*)\][\]]?/", "$1", $content_pdf));
        //TODO: It should be better to display the link insted of the tile but it is hard for [[title]] links

        $title_pdf   = api_html_entity_decode($data['title'], ENT_QUOTES, api_get_system_encoding());
        $title_pdf   = api_utf8_encode($title_pdf, api_get_system_encoding());
        $content_pdf = api_utf8_encode($content_pdf, api_get_system_encoding());

        $html='
        <!-- defines the headers/footers - this must occur before the headers/footers are set -->

        <!--mpdf
        <pageheader name="odds" content-left="'.$title_pdf.'"  header-style-left="color: #880000; font-style: italic;"  line="1" />
        <pagefooter name="odds" content-right="{PAGENO}/{nb}" line="1" />

        <!-- set the headers/footers - they will occur from here on in the document -->
        <!--mpdf
        <setpageheader name="odds" page="odd" value="on" show-this-page="1" />
        <setpagefooter name="odds" page="O" value="on" />

        mpdf-->'.$content_pdf;


        $css_file = api_get_path(TO_SYS, WEB_CSS_PATH).api_get_setting('stylesheets').'/print.css';
        if (file_exists($css_file)) {
            $css = @file_get_contents($css_file);
        } else {
            $css = '';
        }
        require_once api_get_path(LIBRARY_PATH).'pdf.lib.php';
        $pdf = new PDF();
        $pdf->content_to_pdf($html, $css, $title_pdf, $course_code);
        exit;
    }

    /**
     * Function prevent double post (reload or F5)
     *
     */
    function double_post($wpost_id)
    {
        if (isset($_SESSION['wpost_id'])) {
            if ($wpost_id == $_SESSION['wpost_id']) {
                 return false;
            } else {
                $_SESSION['wpost_id'] = $wpost_id;
                return true;
            }
        } else {
            $_SESSION['wpost_id'] = $wpost_id;
            return true;
        }
    }

    /**
     * Function wizard individual assignment
     * @author Juan Carlos Raña <herodoto@telefonica.net>
     */
    function auto_add_page_users($assignment_type)
    {
        //$assig_user_id is need to identify end reflinks
        $session_id = $this->session_id;
        $groupId = api_get_group_id();

        if ($groupId==0) {
              //extract course members
            if(!empty($session_id)) {
                $a_users_to_add = CourseManager :: get_user_list_from_course_code($_SESSION['_course']['id'], $session_id);
            } else {
                $a_users_to_add = CourseManager :: get_user_list_from_course_code($_SESSION['_course']['id'], 0);
            }
        } else {
            //extract group members
            $subscribed_users = GroupManager :: get_subscribed_users($groupId);
            $subscribed_tutors = GroupManager :: get_subscribed_tutors($groupId);
            $a_users_to_add_with_duplicates = array_merge($subscribed_users, $subscribed_tutors);

            //remove duplicates
            $a_users_to_add = $a_users_to_add_with_duplicates;
            //array_walk($a_users_to_add, create_function('&$value,$key', '$value = json_encode($value);'));
            $a_users_to_add = array_unique($a_users_to_add);
            //array_walk($a_users_to_add, create_function('&$value,$key', '$value = json_decode($value, true);'));
        }

        $all_students_pages = array();

        //data about teacher
        $userinfo = api_get_user_info(api_get_user_id());
        $username = api_htmlentities(sprintf(get_lang('LoginX'), $userinfo['username'], ENT_QUOTES));
        $name = $userinfo['complete_name']." - ".$username;
        if (api_get_user_id()<>0) {
            $image_path = UserManager::get_user_picture_path_by_id(api_get_user_id(),'web',false, true);
            $image_repository = $image_path['dir'];
            $existing_image = $image_path['file'];
            $photo= '<img src="'.$image_repository.$existing_image.'" alt="'.$name.'"  width="40" height="50" align="top" title="'.$name.'"  />';
        } else {
            $photo= '<img src="'.api_get_path(WEB_CODE_PATH)."img/unknown.jpg".'" alt="'.$name.'"  width="40" height="50" align="top"  title="'.$name.'"  />';
        }

        //teacher assignment title
        $title_orig=$_POST['title'];

        //teacher assignment reflink
        $link2teacher=$_POST['title']= $title_orig."_uass".api_get_user_id();

        //first: teacher name, photo, and assignment description (original content)
       // $content_orig_A='<div align="center" style="background-color: #F5F8FB;  border:double">'.$photo.'<br />'.api_get_person_name($userinfo['firstname'], $userinfo['lastname']).'<br />('.get_lang('Teacher').')</div><br/><div>';
        $content_orig_A='<div align="center" style="background-color: #F5F8FB; border:solid; border-color: #E6E6E6"><table border="0"><tr><td style="font-size:24px">'.get_lang('AssignmentDesc').'</td></tr><tr><td>'.$photo.'<br />'.Display::tag('span', api_get_person_name($userinfo['firstname'], $userinfo['lastname']), array('title'=>$username)).'</td></tr></table></div>';
        $content_orig_B='<br/><div align="center" style="font-size:24px">'.get_lang('AssignmentDescription').': '.$title_orig.'</div><br/>'.$_POST['content'];

        //Second: student list (names, photo and links to their works).
        //Third: Create Students work pages.
        foreach($a_users_to_add as $user_id=>$o_user_to_add) {
            if($o_user_to_add['user_id'] != api_get_user_id()) {
                //except that puts the task
                $assig_user_id = $o_user_to_add['user_id']; //identifies each page as created by the student, not by teacher
                $image_path = UserManager::get_user_picture_path_by_id($assig_user_id,'web',false, true);
                $image_repository = $image_path['dir'];
                $existing_image = $image_path['file'];
                $username = api_htmlentities(sprintf(get_lang('LoginX'), $o_user_to_add['username'], ENT_QUOTES));
                $name = api_get_person_name($o_user_to_add['firstname'], $o_user_to_add['lastname'])." . ".$username;
                $photo= '<img src="'.$image_repository.$existing_image.'" alt="'.$name.'"  width="40" height="50" align="bottom" title="'.$name.'"  />';

                $is_tutor_of_group = GroupManager :: is_tutor_of_group($assig_user_id,$groupId); //student is tutor
                $is_tutor_and_member = (GroupManager :: is_tutor_of_group($assig_user_id,$groupId) && GroupManager :: is_subscribed($assig_user_id, $groupId)); //student is tutor and member

                if($is_tutor_and_member) {
                    $status_in_group=get_lang('GroupTutorAndMember');
                } else {
                    if($is_tutor_of_group) {
                        $status_in_group=get_lang('GroupTutor');
                    } else {
                        $status_in_group=" "; //get_lang('GroupStandardMember')
                    }
                }

                if($assignment_type==1) {
                    $_POST['title']= $title_orig;
                    $_POST['comment']=get_lang('AssignmentFirstComToStudent');
                    $_POST['content']='<div align="center" style="background-color: #F5F8FB; border:solid; border-color: #E6E6E6"><table border="0"><tr><td style="font-size:24px">'.get_lang('AssignmentWork').'</td></tr><tr><td>'.$photo.'<br />'.$name.'</td></tr></table></div>[['.$link2teacher.' | '.get_lang('AssignmentLinktoTeacherPage').']] '; //If $content_orig_B is added here, the task written by the professor was copied to the page of each student. TODO: config options

                    //AssignmentLinktoTeacherPage
                     $all_students_pages[] = '<li>'.
                     Display::tag('span', strtoupper($o_user_to_add['lastname']).', '.$o_user_to_add['firstname'], array('title'=>$username)).
                     ' [['.$_POST['title']."_uass".$assig_user_id.' | '.$photo.']] '.$status_in_group.'</li>'; //don't change this line without guaranteeing that users will be ordered by last names in the following format (surname, name)
                    $_POST['assignment']=2;
                }
                $this->assig_user_id = $assig_user_id;
                self::save_new_wiki();
            }
        }//end foreach for each user

        foreach ($a_users_to_add as $user_id=>$o_user_to_add) {
            if($o_user_to_add['user_id'] == api_get_user_id()) {
                $assig_user_id=$o_user_to_add['user_id'];
                if($assignment_type==1) {
                    $_POST['title']= $title_orig;
                    $_POST['comment']=get_lang('AssignmentDesc');
                    sort($all_students_pages);
                    $_POST['content']=$content_orig_A.$content_orig_B.'<br/><div align="center" style="font-size:18px; background-color: #F5F8FB; border:solid; border-color:#E6E6E6">'.get_lang('AssignmentLinkstoStudentsPage').'</div><br/><div style="background-color: #F5F8FB; border:solid; border-color:#E6E6E6"><ol>'.implode($all_students_pages).'</ol></div><br/>';
                    $_POST['assignment']=1;
                 }
                $this->assig_user_id = $assig_user_id;
                self::save_new_wiki();
            }
        } //end foreach to teacher
    }

    /**
     * Displays the results of a wiki search
     * @param   string  Search term
     * @param   int     Whether to search the contents (1) or just the titles (0)
     */

    function display_wiki_search_results($search_term, $search_content=0, $all_vers=0)
    {
        $tbl_wiki = $this->tbl_wiki;
        $condition_session = $this->condition_session;
        $groupfilter = $this->groupfilter;
        $_course = $this->courseInfo;

        echo '<legend>'.get_lang('WikiSearchResults').'</legend>';
        $course_id = api_get_course_int_id();

        //only by professors when page is hidden
        if (api_is_allowed_to_edit(false,true) || api_is_platform_admin()) {
            if ($all_vers=='1') {
                if ($search_content=='1') {
                    $sql = "SELECT * FROM ".$tbl_wiki."
                            WHERE c_id = $course_id AND title LIKE '%".Database::escape_string($search_term)."%' OR content LIKE '%".Database::escape_string($search_term)."%' AND ".$groupfilter.$condition_session."";//search all pages and all versions
                } else {
                    $sql = "SELECT * FROM ".$tbl_wiki."
                            WHERE c_id = $course_id AND title LIKE '%".Database::escape_string($search_term)."%' AND ".$groupfilter.$condition_session."";//search all pages and all versions
                }
            } else {
                if ($search_content=='1') {
                   $sql = "SELECT * FROM ".$tbl_wiki." s1
                            WHERE s1.c_id = $course_id AND title LIKE '%".Database::escape_string($search_term)."%' OR content LIKE '%".Database::escape_string($search_term)."%' AND
                            id=(SELECT MAX(s2.id) FROM ".$tbl_wiki." s2 WHERE s2.c_id = $course_id AND s1.reflink = s2.reflink AND ".$groupfilter.$condition_session.")";// warning don't use group by reflink because don't return the last version
                }
                else {
                   $sql = " SELECT * FROM ".$tbl_wiki." s1
                            WHERE s1.c_id = $course_id AND title LIKE '%".Database::escape_string($search_term)."%' AND
                            id=(SELECT MAX(s2.id) FROM ".$tbl_wiki." s2 WHERE s2.c_id = $course_id AND s1.reflink = s2.reflink AND ".$groupfilter.$condition_session.")";// warning don't use group by reflink because don't return the last version
                }
            }
        } else {
            if($all_vers=='1') {
                if ($search_content=='1') {
                    $sql = "SELECT * FROM ".$tbl_wiki."
                            WHERE c_id = $course_id AND visibility=1 AND title LIKE '%".Database::escape_string($search_term)."%' OR content LIKE '%".Database::escape_string($search_term)."%' AND ".$groupfilter.$condition_session."";//search all pages and all versions
                } else {
                    $sql = "SELECT * FROM ".$tbl_wiki."
                            WHERE c_id = $course_id AND visibility=1 AND title LIKE '%".Database::escape_string($search_term)."%' AND ".$groupfilter.$condition_session."";//search all pages and all versions
                }
            } else {
                if($search_content=='1') {
                   $sql = " SELECT * FROM ".$tbl_wiki." s1
                            WHERE s1.c_id = $course_id AND visibility=1 AND title LIKE '%".Database::escape_string($search_term)."%' OR content LIKE '%".Database::escape_string($search_term)."%' AND
                            id=(SELECT MAX(s2.id) FROM ".$tbl_wiki." s2 WHERE s2.c_id = $course_id AND  s1.reflink = s2.reflink AND ".$groupfilter.$condition_session.")";// warning don't use group by reflink because don't return the last version
            } else {
                   $sql = " SELECT * FROM ".$tbl_wiki." s1
                            WHERE s1.c_id = $course_id AND visibility=1 AND title LIKE '%".Database::escape_string($search_term)."%' AND
                            id=(SELECT MAX(s2.id) FROM ".$tbl_wiki." s2 WHERE s2.c_id = $course_id AND s1.reflink = s2.reflink AND ".$groupfilter.$condition_session.")";// warning don't use group by reflink because don't return the last version
                }
            }
        }

        $result = Database::query($sql);

        //show table
        $rows = array();
        if (Database::num_rows($result) > 0) {
            while ($obj = Database::fetch_object($result)) {
                //get author
                $userinfo = api_get_user_info($obj->user_id);

                //get time
                $year 	 = substr($obj->dtime, 0, 4);
                $month	 = substr($obj->dtime, 5, 2);
                $day 	 = substr($obj->dtime, 8, 2);
                $hours   = substr($obj->dtime, 11,2);
                $minutes = substr($obj->dtime, 14,2);
                $seconds = substr($obj->dtime, 17,2);

                //get type assignment icon
                if($obj->assignment==1) {
                    $ShowAssignment=Display::return_icon('wiki_assignment.png', get_lang('AssignmentDesc'),'',ICON_SIZE_SMALL);
                } elseif ($obj->assignment==2) {
                    $ShowAssignment=Display::return_icon('wiki_work.png', get_lang('AssignmentWork'),'',ICON_SIZE_SMALL);
                } elseif ($obj->assignment==0) {
                    $ShowAssignment='<img src="../img/px_transparent.gif" />';
                }
                $row = array();
                $row[] =$ShowAssignment;

                if($all_vers=='1') {
                    $row[] = '<a href="'.api_get_self().'?'.api_get_cidreq().'&action=showpage&title='.api_htmlentities(urlencode($obj->reflink)).'&view='.$obj->id.'&session_id='.api_htmlentities(urlencode($_GET['$session_id'])).'&group_id='.api_htmlentities(urlencode($_GET['group_id'])).'">'.api_htmlentities($obj->title).'</a>';
                } else {
                    $row[] = '<a href="'.api_get_self().'?'.api_get_cidreq().'&action=showpage&title='.api_htmlentities(urlencode($obj->reflink)).'&session_id='.api_htmlentities($_GET['session_id']).'&group_id='.api_htmlentities($_GET['group_id']).'">'.$obj->title.'</a>';
                }

                $row[] = $obj->user_id <>0 ? '<a href="../user/userInfo.php?uInfo='.$userinfo['user_id'].'">'.api_htmlentities($userinfo['complete_name']).'</a>' : get_lang('Anonymous').' ('.$obj->user_ip.')';
                $row[] = $year.'-'.$month.'-'.$day.' '.$hours.":".$minutes.":".$seconds;

                if ($all_vers=='1') {
                    $row[] = $obj->version;
                } else {
                    if (api_is_allowed_to_edit(false,true)|| api_is_platform_admin()) {
                        $showdelete=' <a href="'.api_get_self().'?'.api_get_cidreq().'&action=delete&title='.api_htmlentities(urlencode($obj->reflink)).'&group_id='.api_htmlentities($_GET['group_id']).'">'.Display::return_icon('delete.png', get_lang('Delete'),'',ICON_SIZE_SMALL);
                    }
                    $row[] = '<a href="'.api_get_self().'?'.api_get_cidreq().'&action=edit&title='.api_htmlentities(urlencode($obj->reflink)).'&group_id='.api_htmlentities($_GET['group_id']).'">'.Display::return_icon('edit.png', get_lang('EditPage'),'',ICON_SIZE_SMALL).'</a> <a href="'.api_get_self().'?cidReq='.$_course[id].'&action=discuss&title='.api_htmlentities(urlencode($obj->reflink)).'&session_id='.api_htmlentities($_GET['session_id']).'&group_id='.api_htmlentities($_GET['group_id']).'">'.Display::return_icon('discuss.png', get_lang('Discuss'),'',ICON_SIZE_SMALL).'</a> <a href="'.api_get_self().'?cidReq='.$_course[id].'&action=history&title='.api_htmlentities(urlencode($obj->reflink)).'&session_id='.api_htmlentities($_GET['session_id']).'&group_id='.api_htmlentities($_GET['group_id']).'">'.Display::return_icon('history.png', get_lang('History'),'',ICON_SIZE_SMALL).'</a> <a href="'.api_get_self().'?cidReq='.$_course[id].'&action=links&title='.api_htmlentities(urlencode($obj->reflink)).'&group_id='.api_htmlentities($_GET['group_id']).'">'.Display::return_icon('what_link_here.png', get_lang('LinksPages'),'',ICON_SIZE_SMALL).'</a>'.$showdelete;
                }
                $rows[] = $row;
            }

            $table = new SortableTableFromArrayConfig($rows, 1, 10,'SearchPages_table','','','ASC');
            $table->set_additional_parameters(array('cidReq' => $_GET['cidReq'],'action'=> $_GET['action'], 'group_id'=>Security::remove_XSS($_GET['group_id']),'mode_table'=>'yes2','search_term'=>$search_term, 'search_content'=>$search_content, 'all_vers'=>$all_vers));
            $table->set_header(0,get_lang('Type'), true, array ('style' => 'width:30px;'));
            $table->set_header(1,get_lang('Title'), true);
            if ($all_vers=='1') {
                $table->set_header(2,get_lang('Author'), true);
                $table->set_header(3,get_lang('Date'), true);
                $table->set_header(4,get_lang('Version'), true);
            } else {
                $table->set_header(2,get_lang('Author').' ('.get_lang('LastVersion').')', true);
                $table->set_header(3,get_lang('Date').' ('.get_lang('LastVersion').')', true);
                $table->set_header(4,get_lang('Actions'), false, array ('style' => 'width:130px;'));
            }
            $table->display();
        } else {
            echo get_lang('NoSearchResults');
        }
    }

    /**
     * Returns a date picker
     * @todo replace this function with the formvalidator datepicker
     *
     */
    function draw_date_picker($prefix,$default='')
    {
        if (empty($default)) {
            $default = date('Y-m-d H:i:s');
        }
        $parts = split(' ',$default);
        list($d_year,$d_month,$d_day) = split('-',$parts[0]);
        list($d_hour,$d_minute) = split(':',$parts[1]);

        $month_list = array(
            1=>get_lang('JanuaryLong'),
            2=>get_lang('FebruaryLong'),
            3=>get_lang('MarchLong'),
            4=>get_lang('AprilLong'),
            5=>get_lang('MayLong'),
            6=>get_lang('JuneLong'),
            7=>get_lang('JulyLong'),
            8=>get_lang('AugustLong'),
            9=>get_lang('SeptemberLong'),
            10=>get_lang('OctoberLong'),
            11=>get_lang('NovemberLong'),
            12=>get_lang('DecemberLong')
        );

        $minute = range(10,59);
        array_unshift($minute,'00','01','02','03','04','05','06','07','08','09');
        $date_form = self::make_select($prefix.'_day', array_combine(range(1,31),range(1,31)), $d_day);
        $date_form .= self::make_select($prefix.'_month', $month_list, $d_month);
        $date_form .= self::make_select($prefix.'_year', array($d_year-2=>$d_year-2, $d_year-1=>$d_year-1, $d_year=> $d_year, $d_year+1=>$d_year+1, $d_year+2=>$d_year+2), $d_year).'&nbsp;&nbsp;&nbsp;&nbsp;';
        $date_form .= self::make_select($prefix.'_hour', array_combine(range(0,23),range(0,23)), $d_hour).' : ';
        $date_form .= self::make_select($prefix.'_minute', $minute, $d_minute);

        return $date_form;
    }

    /**
     * Draws an HTML form select with the given options
     *
     */
    function make_select($name,$values,$checked='')
    {
        $output = '<select name="'.$name.'" id="'.$name.'">';
        foreach($values as $key => $value) {
            $output .= '<option value="'.$key.'" '.(($checked==$key)?'selected="selected"':'').'>'.$value.'</option>';
        }
        $output .= '</select>';
        return $output;
    }

    /**
     * Translates a form date into a more usable format
     *
     */
    function get_date_from_select($prefix)
    {
        return $_POST[$prefix.'_year'].'-'.self::two_digits($_POST[$prefix.'_month']).'-'.self::two_digits($_POST[$prefix.'_day']).' '.self::two_digits($_POST[$prefix.'_hour']).':'.self::two_digits($_POST[$prefix.'_minute']).':00';
    }

    /**
    * Converts 1-9 to 01-09
    */
    function two_digits($number)
    {
        $number = (int)$number;
        return ($number < 10) ? '0'.$number : $number;
    }

    /**
     * Get wiki information
     * @param   int     wiki id
     * @return  array   wiki data
     */
    function get_wiki_data($id)
    {
        $tbl_wiki = $this->tbl_wiki;
        $course_id = api_get_course_int_id();
        $id = intval($id);
        $sql = 'SELECT * FROM '.$tbl_wiki.'
                WHERE c_id = '.$course_id.' AND id = '.$id.' ';
        $result=Database::query($sql);
        $data = array();
        while ($row=Database::fetch_array($result,'ASSOC'))   {
            $data = $row;
        }
        return $data;
    }

    /**
     * Get wiki information
     * @param   string     wiki id
     * @param int $courseId
     * @return  array   wiki data
     */
    function getPageByTitle($title, $courseId = null)
    {
        $tbl_wiki = $this->tbl_wiki;
        if (empty($courseId)) {
            $courseId = api_get_course_int_id();
        } else {
            $courseId = intval($courseId);
        }

        if (empty($title) || empty($courseId)) {
            return array();
        }

        $title = Database::escape_string($title);
        $sql = "SELECT * FROM $tbl_wiki WHERE c_id = $courseId AND reflink = '$title'";
        $result = Database::query($sql);
        $data = array();
        if (Database::num_rows($result)) {
            $data = Database::fetch_array($result,'ASSOC');
        }
        return $data;
    }

    /**
     * @param string $title
     * @param int $courseId
     * @param string
     * @param string
     * @return bool
     */
    function deletePage($title, $courseId, $groupfilter = null, $condition_session = null)
    {
        $tbl_wiki = $this->tbl_wiki;
        $tbl_wiki_discuss = $this->tbl_wiki_discuss;
        $tbl_wiki_mailcue = $this->tbl_wiki_mailcue;
        $tbl_wiki_conf = $this->tbl_wiki_conf;

        $pageInfo = self::getPageByTitle($title, $courseId);
        if (!empty($pageInfo)) {
            $pageId = $pageInfo['id'];
            $sql = "DELETE FROM $tbl_wiki_conf
                    WHERE c_id = $courseId AND page_id = $pageId";
            Database::query($sql);

            $sql = 'DELETE FROM '.$tbl_wiki_discuss.'
                    WHERE c_id = '.$courseId.' AND publication_id = '.$pageId;
            Database::query($sql);

            $sql='DELETE FROM  '.$tbl_wiki_mailcue.'
                    WHERE c_id = '.$courseId.' AND id = '.$pageId.' AND '.$groupfilter.$condition_session.'';
            Database::query($sql);

            $sql = 'DELETE FROM '.$tbl_wiki.'
                    WHERE c_id = '.$courseId.' AND id = '.$pageId.' AND '.$groupfilter.$condition_session.'';
            Database::query($sql);
            self::check_emailcue(0, 'E');
            return true;
        }
        return false;
    }

    public function getAllWiki()
    {
        $tbl_wiki = $this->tbl_wiki;
        $course_id = $this->course_id;
        $condition_session = $this->condition_session;

        $sql = "SELECT * FROM $tbl_wiki
                WHERE c_id = $course_id AND is_editing != '0' ".$condition_session;
        $result = Database::query($sql);
        return Database::store_result($result, 'ASSOC');
    }

    public function updateWikiIsEditing($isEditing)
    {
        $tbl_wiki = $this->tbl_wiki;
        $course_id = $this->course_id;
        $condition_session = $this->condition_session;
        $isEditing = Database::escape_string($isEditing);

        $sql = 'UPDATE '.$tbl_wiki.' SET is_editing="0", time_edit="0000-00-00 00:00:00"
                WHERE c_id = '.$course_id.' AND is_editing="'.$isEditing.'" '.$condition_session;
        Database::query($sql);
    }
}
