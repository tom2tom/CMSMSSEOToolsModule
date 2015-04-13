<?php
# This file is part of CMS Made Simple module: SEOTools.
# Copyright (C) 2010-2011 Henning Schaefer <henning.schaefer@gmail.com>
# Copyright (C) 2014-2015 Tom Phane <tpgww@onepost.net>
# Refer to licence and other details at the top of file SEOTools.module.php

# Setup and display admin page, after processing any action-request

function getImportantAlerts(&$mod, $omit_inactive = FALSE, $omit_ignored = FALSE)
{
	$gCms = cmsms();
	$db = $gCms->GetDb();
	$pre = cms_db_prefix();
	$config = $gCms->GetConfig();
	if (isset ($config['admin_url']))
		$adminurl = $config['admin_url'];
	else
		$adminurl = $config['root_url'].'/'.$config['admin_dir'];
	$alerts = array();
	// Pretty URLs not working
	if (($config['assume_mod_rewrite'] != 1) && ($config['internal_pretty_urls'] != 1)) {
	  $theme = $gCms->variables['admintheme'];
	  $alert = array();
	  $alert['group'] = 'system';
	  $alert['message'] = $mod->Lang('activate_pretty_urls');
	  $alert['links'][] =
'<a href="http://docs.cmsmadesimple.org/configuration/pretty-url" onclick="window.open(this.href,\'_blank\');return false;"><img src="'
.$adminurl.'/themes/'.$theme->themeName.'/images/icons/system/info-external.gif" title = "'
.$mod->Lang('get_help').'" style="vertical-align: middle;" /></a>';
	  $alerts[] = $alert;
	}
	// Content pages with short description
	$query = "SELECT C.content_id, C.content_name, C.active, S.ignored FROM "
	 .$pre."content C INNER JOIN "
	 .$pre."content_props P ON C.content_id = P.content_id LEFT JOIN "
	 .$pre."module_seotools S ON C.content_id = S.content_id WHERE ";
	if ($omit_inactive) {
		$query .= "C.active=1 AND";
	}
	$query .= "C.type LIKE ? AND P.prop_name=? AND P.content<>? AND CHAR_LENGTH(P.content) < 75"; //NOTE not much portable. $db->length dun't work!
	$parms = array('content%'); //can't be an injection risk here
	$parms[] = str_replace(' ','_',$mod->GetPreference('description_block',''));
	$parms[] = '';
	$result = $db->Execute($query, $parms);
	if ($result) {
		$code = 'shortmeta';
		$keep = !$omit_ignored;
		while ($problem = $result->fetchRow()) {
		  $ig = $problem['ignored'];
		  if (($ig == null && $keep)
			||($ig != null && strpos($ig,$code) !== FALSE)) {
			$alert = array();
			$alert['group'] = 'descriptions';
			$alert['active'] = $problem['active'];
			$alert['pages'] = array($problem['content_name']);
			$alert['message'] = $mod->Lang('meta_description_short');
			$alert['ignored'] = $problem['ignored'];
			$alert['links_data'][$problem['content_id']] = array($problem['content_name'],$code);
			$alerts[] = $alert;
		  }
		}
	}

	// Any pages with duplicate title
	$query = "SELECT c1.content_alias AS c1name, c1.content_id AS c1id, c1.active AS c1a,
	c2.content_alias AS c2name, c2.content_id AS c2id, c2.active as c2a, S.ignored FROM "
	.$pre."content c1 INNER JOIN "
	.$pre."content c2 ON c1.content_name = c2.content_name LEFT JOIN "
	.$pre."module_seotools ON c1.content_id = S.content_id WHERE ";
	if ($omit_inactive) {
		$query .= "c1.active=1 AND c2.active=1 AND ";
	}
	$query .= "c1.content_id<c2.content_id";
	$result = $db->Execute($query);
	if ($result) {
		$code = 'sametitle';
		$keep = !$omit_ignored;
		while ($problem = $result->fetchRow()) {
		  $ig = $problem['ignored'];
		  if (($ig == null && $keep)
			||($ig != null && strpos($ig,$code) !== FALSE)) {
			$alert = array();
			$alert['group'] = 'titles';
			$alert['active'] = $problem['c1a'].','.$problem['c2a'];
			$alert['pages'] = array($problem['c1name'],$problem['c2name']);
			$alert['message'] = $mod->Lang('duplicate_titles');
			$alert['ignored'] = $problem['ignored'];
			$alert['links_data'][$problem['c1id']] = array($problem['c1name'],$code);
			$alert['links_data'][$problem['c2id']] = array($problem['c2name'],$code);
			$alerts[] = $alert;
		  }
		}
	}

	// Any pages with duplicate description
	$query = "SELECT p1.content_id AS p1id, p2.content_id AS p2id, S.ignored FROM "
	.$pre."content_props p1 INNER JOIN "
	.$pre."content_props p2 ON p1.prop_name = p2.prop_name LEFT JOIN "
	.$pre."module_seotools S ON p1.content_id = S.content_id
	WHERE (p1.prop_name = ? AND p1.content_id < p2.content_id  AND p1.content <> ? AND p2.content = p1.content)";
	$parms = array();
	$parms[] = str_replace(' ','_',$mod->GetPreference('description_block',''));
	$parms[] = '';
	$result = $db->Execute($query, $parms);
	if ($result) {
		$query = "SELECT content_id, content_name, active FROM ".$pre."content WHERE ";
		if ($omit_inactive) {
			$query .= "active=1 AND ";
		}
		$query .= "(content_id=? OR content_id=?)";
		$code = 'samedesc';
		$keep = !$omit_ignored;
		while ($problem = $result->fetchRow()) {
		  $ig = $problem['ignored'];
		  if (($ig == null && $keep)
			||($ig != null && strpos($ig,$code) !== FALSE)) {
			$result1 = $db->Execute($query,array($problem['p1id'],$problem['p2id']));
			$first = $result1->fetchRow();
			$second = $result1->fetchRow();
			$alert = array();
			$alert['group'] = 'descriptions';
			$alert['active'] = $first['active'].','.$second['active'];
			$alert['pages'] = array($first['content_name'],$second['content_name']);
			$alert['message'] = $mod->Lang('duplicate_descriptions');
			$alert['ignored'] = $problem['ignored']; //CHECKME both?
			$alert['links_data'][$first['content_id']] = array($first['content_name'],$code);
			$alert['links_data'][$second['content_id']] = array($second['content_name'],$code);
			$alerts[] = $alert;
		  }
		}
	}
	// No author provided
	if ($mod->GetPreference('meta_publisher','') == '') {
		$alert = array();
		$alert['group'] = 'settings';
		$alert['message'] = $mod->Lang('provide_an_author');
		$alert['links'][] = $mod->getSeeLink (4,$this->Lang('visit_settings'));
		$alerts[] = $alert;
	}
	return $alerts;
}

function getTabLink ($index, $label)
{
	return '<a class="@'.$index.'" href="#">'.$label.'</a>';
}

function getNoticeAlerts(&$mod)
{
	$alerts = array();
	// No standard meta
	if (!$mod->GetPreference('meta_standard',FALSE)) {
		$alert = array();
		$alert['message'] = $mod->Lang('use_standard_meta');
		$alert['links'][] = getTabLink (4,$this->Lang('visit_settings'));
		$alerts[] = $alert;
	}
	// Submit a sitemap
	if (!$mod->GetPreference('create_sitemap',0)) {
		$alert = array();
		$alert['message'] = $mod->Lang('create_a_sitemap');
		$alert['links'][] = getTabLink (6,$mod->Lang('visit_settings'));
		$alerts[] = $alert;
	}elseif(!$mod->GetPreference('push_sitemap',0)) {
	  // Automatically submit the sitemap
		$alert = array();
		$alert['message'] = $mod->Lang('automatically_upload_sitemap');
		$alert['links'][] = getTabLink (6,$mod->Lang('visit_settings'));
		$alerts[] = $alert;
	}
	// Create a robots.txt file
	if (!$mod->GetPreference('create_robots',0)) {
		$alert = array();
		$alert['message'] = $mod->Lang('create_robots');
		$alert['links'][] = getTabLink (6,$mod->Lang('visit_settings'));
		$alerts[] = $alert;
	}
	// Set a default image
	return $alerts;
}

if (! $this->CheckAccess()) {
    return $this->DisplayErrorPage($this->Lang('accessdenied'));
}

function getFixLink(&$mod, $sp, $id, $pagename = '')
{
	$gCms = cmsms();
	$config = $gCms->GetConfig();
	if (isset ($config['admin_url']))
		$adminurl = $config['admin_url'];
	else
		$adminurl = $config['root_url'].'/'.$config['admin_dir'];
	$theme = $gCms->variables['admintheme'];
	$lnk = '<a href="'.$adminurl.'/editcontent.php?'.$mod->pathstr.'='.$sp.'&content_id='.$id
	 .'"><img src="'.$adminurl.'/themes/'
	 .$theme->themeName.'/images/icons/system/edit.gif" title = "';
	if ($pagename) {
		$lnk .= $mod->Lang('edit_page',$pagename);
	} else {
		$lnk .= $mod->Lang('edit_page2');
	}
	$lnk .= '" style="vertical-align: middle;" /></a>';
	return $lnk;
}

if (isset($_GET['what'])) {
	$what = $_GET['what'];
} else {
	$what = FALSE;
}
$pre = cms_db_prefix();

// Do the action, if any
switch ($what)
{
case 'toggle_index':
	$query = "SELECT indexable FROM ".$pre."module_seotools WHERE content_id=?";
	$info = $db->GetOne($query,array($_GET['content_id']));
	$parms = array();
	if ($info == FALSE) {
		$query = "INSERT INTO ".$pre."module_seotools SET content_id=?, indexable=0";
	} else {
		$query = "UPDATE ".$pre."module_seotools SET indexable=? WHERE content_id=?";
		if ($info == '1') {
			$parms[] = 0;
		} else {
			$parms[] = 1;
		}
	}
	$parms[] = $_GET['content_id'];
    $db->Execute($query,$parms);
/* only manual updates
	$funcs = new SEO_file();
	if ($this->GetPreference('create_robots',0)) {
        $funcs->createRobotsTXT($this);
    }
    if ($this->GetPreference('create_sitemap',0)) {
        $funcs->createSitemap($this);
    }
*/
    $_GET['tab'] = 'pagedescriptions';
	break;
case 'toggle_ignore':
	$pages = explode ('@',$_GET['content_data']);
	unset ($pages[0]);
	foreach ($pages as $sig) {
		list ($id,$ignored) = explode ('-', $sig);
		$id = (int)$id;
		$query = "SELECT content_id,ignored FROM ".$pre."module_seotools WHERE content_id=?";
		$res = $db->GetRow($query,array($id));
		$parms = array();
		if ($res) {
			if ($res['ignored']) {
				$codes = explode(',',$res['ignored']);
				if (in_array($ignored, $codes)) {
					foreach ($codes as $i => $name) {
					  if ($name == $ignored) unset($codes[$i]);
					}
				} else {
					$codes[] = $ignored;
				}
				if ($codes) {
					$query = "UPDATE ".$pre."module_seotools SET ignored=? WHERE content_id=?";
					$parms[] = implode(',',$codes);
				} else {
					$query = "UPDATE ".$pre."module_seotools SET ignored=NULL WHERE content_id=?";
				}
			} else {
				$query = "UPDATE ".$pre."module_seotools SET ignored=? WHERE content_id=?";
				$parms[] = $ignored;
			}
		} else {
			$query = "INSERT INTO ".$pre."module_seotools (ignored,content_id) VALUES (?,?)";
			$parms[] = $ignored;
		}
		$parms[] = $id;
    	$db->Execute($query,$parms);
		unset ($parms);
	}
	break;
case 'set_priority':
	//non-database-specific 'UPSERT' equivalent is needed
	if (preg_match ('/'.$db->dbtype.'/i', 'mysql')) {
		$query = "INSERT INTO ".$pre."module_seotools (content_id, priority) VALUES (?,?) ON DUPLICATE KEY UPDATE priority=?";
		$parms = array((int)$_GET['content_id'],$_GET['priority'],$_GET['priority']);
	} else {
		$query = "select content_id from ".$pre."module_seotools where content_id=?";
		$res = $db->getone($query,array($id));
		if ($res) {
			$query = "update ".$pre."module_seotools set priority=? where content_id=?";
			$parms = array($_get['priority'],(int)$_get['content_id']);
		} else {
			$query = "insert into ".$pre."module_seotools (content_id, priority) values (?,?)";
			$parms = array((int)$_get['content_id'],$_get['priority']);
		}
	}
	$db->execute($query, $parms);
/* only manual updates
    if ($this->GetPreference('create_sitemap',0)) {
		$funcs = new SEO_file();
        $funcs->createSitemap($this);
    }
*/
	$_GET['tab'] = 'pagedescriptions';
	break;
case 'reset_priority':
    $query = "UPDATE ".$pre."module_seotools SET priority=NULL WHERE content_id=?";
    $db->Execute($query,array($_GET['content_id']));
/* only manual updates
    if ($this->GetPreference('create_sitemap',0)) {
		$funcs = new SEO_file();
        $funcs->createSitemap($this);
    }
*/
    $_GET['tab'] = 'pagedescriptions';
	break;
case 'reset_ogtype':
    $query = "UPDATE ".$pre."module_seotools SET ogtype=NULL WHERE content_id=?";
    $db->Execute($query,array($_GET['content_id']));
    $_GET['tab'] = 'pagedescriptions';
	break;
case 'reset_keywords':
    $query = "UPDATE ".$pre."module_seotools SET keywords=NULL WHERE content_id=?";
    $db->Execute($query,array($_GET['content_id']));
    $_GET['tab'] = 'pagedescriptions';
	break;
case 'edit_ogtype':
	$this->Redirect($id, 'edit_ogtype', $returnid, array('content_id'=>$_GET['content_id']));
	break;
case 'edit_keywords':
	$this->Redirect($id, 'edit_keywords', $returnid, array('content_id'=>$_GET['content_id']));
	break;
}

if (isset($_GET['message'])) {
  if (isset($_GET['warning'])) {
	$smarty->assign('message',$this->ShowErrors($this->Lang($_GET['message'])));
  } else {
	$smarty->assign('message',$this->ShowMessage($this->Lang($_GET['message'])));
  }
}

$indx = 0;
if (isset ($_GET['tab'])) {
	switch ($_GET['tab']) {
	case 'urgentfixes':
		$indx = 1;
		break;
	case 'importantfixes':
		$indx = 2;
		break;
	case 'pagedescriptions':
		$indx = 3;
		break;
	case 'metasettings':
		$indx = 4;
		break;
	case 'keywordsettings':
		$indx = 5;
		break;
	case 'sitemapsettings':
		$indx = 6;
		break;
	}
}

$smarty->assign('tab_headers',$this->StartTabHeaders().
	$this->SetTabHeader('alerts',$this->Lang('title_alerts'),$indx==0).
	$this->SetTabHeader('urgentfixes',$this->Lang('title_urgent'),$indx==1).
	$this->SetTabHeader('importantfixes',$this->Lang('title_important'),$indx==2).
	$this->SetTabHeader('pagedescriptions',$this->Lang('title_descriptions'),$indx==3).
	$this->SetTabHeader('metasettings',$this->Lang('title_metasettings'),$indx==4).
	$this->SetTabHeader('keywordsettings',$this->Lang('title_keywordsettings'),$indx==5).
	$this->SetTabHeader('sitemapsettings',$this->Lang('title_sitemapsettings'),$indx==6).
	$this->EndTabHeaders().$this->StartTabContent());
$smarty->assign('tab_footers',$this->EndTabContent());

$smarty->assign('start_alerts_tab',$this->StartTab('alerts'));
$smarty->assign('start_urgent_tab',$this->StartTab('urgentfixes'));
$smarty->assign('start_important_tab',$this->StartTab('importantfixes'));
$smarty->assign('start_description_tab',$this->StartTab('pagedescriptions'));
$smarty->assign('start_meta_tab',$this->StartTab('metasettings'));
$smarty->assign('start_keyword_tab',$this->StartTab('keywordsettings'));
$smarty->assign('start_sitemap_tab',$this->StartTab('sitemapsettings'));
$smarty->assign('end_tab',$this->EndTab());

/* Alerts and Fixes Tabs */

$smarty->assign('startform_problems',$this->CreateFormStart($id, 'allignore')); //several uses
$smarty->assign('end_form',$this->CreateFormEnd()); //several uses
$smarty->assign('start_urgent_set',$this->CreateFieldsetStart($id, 'alerts_urgent', $this->Lang('title_alerts_urgent')));
$smarty->assign('start_important_set',$this->CreateFieldsetStart($id, 'alerts_important', $this->Lang('title_alerts_important')));
$smarty->assign('start_notice_set',$this->CreateFieldsetStart($id, 'alerts_notices', $this->Lang('title_alerts_notices')));
$smarty->assign('end_set',$this->CreateFieldsetEnd());

if (isset ($config['admin_url']))
	$adminurl = $config['admin_url'];
else
	$adminurl = $config['root_url'].'/'.$config['admin_dir'];
$theme_url = $adminurl.'/themes/'.$gCms->variables['admintheme']->themeName.'/images/icons';
$icontrue = '<img src="'.$theme_url.'/system/true.gif" />';

$urgent = array();
$urgent_alerts = $this->getUrgentAlerts();
if ($urgent_alerts) {
  $count = 0;
  $groups = array();
  //sort ALL alerts into types, count some of them
  foreach ($urgent_alerts as $alert) {
	if ((!array_key_exists ('active', $alert) || $alert['active'] == TRUE)
	 && (!array_key_exists ('ignored', $alert) || $alert['ignored'] == FALSE))
		$count++;
    $groups[$alert['group']][] = $alert;
  }
  $icon = '<img src="'.$theme_url.'/Notifications/1.gif" />';
  if ($count) {
	$smarty->assign('urgent_icon',$icon);
	$smarty->assign('urgent_text',$this->Lang('summary_urgent', $count));
	$smarty->assign('urgent_link','['
	.getTabLink(1,$this->Lang('view_all'))
	.']');
  }else{
	$smarty->assign('urgent_icon',$icontrue);
	$smarty->assign('urgent_text',$this->Lang('nothing_to_be_fixed'));
  }
  $j = 0;
  foreach($groups as $group => $galerts) {
	foreach ($galerts as $alert) {
		$onerow = new stdClass;
		$onerow->rowclass = 'row'.($j % 2 + 1);
		if (isset ($alert['pages']))
		 $onerow->pages = implode('<br />',$alert['pages']);
		else
		 $onerow->pages = '';
		$onerow->problem = $alert['message'];
		if (array_key_exists ('links_data', $alert)) {
			$links = $alert['links_data'];
			if (count ($links) == 1) {
				foreach ($links as $id => $data) {
					$onerow->action = getFixLink ($this, $_GET[$this->pathstr], $id);
					$sig = '@'.$id.'-'.$data[1];
				}
			} else {
				$s = array();
				$sig = '';
				foreach ($links as $id => $data) {
					$s[] = getFixLink ($this, $_GET[$this->pathstr], $id, $data[0]);
					$sig .= '@'.$id.'-'.$data[1];
				}
				$onerow->action = implode('<br />', $s);
				unset ($s);
			}
		} elseif (array_key_exists ('links', $alert)) {
			$links = 'TODO'; //QQQ
			$onerow->action = implode('<br />',$alert['links']);
			$sig = '';
		}
		else {
			$links = 'NONE'; //CHECKME
			$onerow->action = '';
			$sig = '';
		}
		if (array_key_exists ('ignored', $alert)) {
			$iname = ($alert['ignored']) ? 'true':'false';
			$onerow->ignored = $this->CreateTooltipLink(null, 'defaultadmin', '',
			'<img src="'.$theme_url.'/system/'.$iname.'.gif" style="vertical-align:middle;" />',
			$this->Lang('toggle'), array('what'=>'toggle_ignore','content_data'=>$sig,'tab'=>'urgentfixes'));
			$onerow->checkval = $sig;
			$onerow->sel = ''; //TODO
		}
		else {
			$onerow->checkval = '';
			$onerow->sel = '';
		}
		if (array_key_exists ('active', $alert)) {
			if(strpos ($alert['active'],',') === FALSE) {
				$act1 = $alert['active'];
				$act2 = FALSE;
			} else {
				list($act1, $act2) = explode(',',$alert['active']);
				$act1 = (int)$act1;
				$act2 = (int)$act2;
			}
			$cb = '<input type="checkbox" disabled="disabled"';
			if ($act1) $cb .= ' checked="checked"';
			$cb .= ' />';
			if ($act2 !== FALSE) {
				$cb .= '<br /><input type="checkbox" disabled="disabled"';
				if ($act2) $cb .= ' checked="checked"';
				$cb .= ' />';
			}
			$onerow->active = $cb;
		}
		else {
			$onerow->active = '';
		}
		
		$urgent[] = $onerow;
		$j++;
    }
  }
}else{
  $smarty->assign('urgent_icon',$icontrue);
  $smarty->assign('urgent_text',$this->Lang('nothing_to_be_fixed'));
}
$smarty->assign('urgents',$urgent);

$important = array();
$important_alerts = getImportantAlerts($this);
if ($important_alerts) {
  $count = 0;
  $groups = array();
  //sort ALL alerts into types, count some of them
  foreach ($important_alerts as $alert) {
	if (!array_key_exists ('ignored', $alert) || $alert['ignored'] == FALSE)
		$count++;
	$groups[$alert['group']][] = $alert;
  }
  $icon = '<img src="'.$theme_url.'/Notifications/2.gif" />';
  if ($count) {
	$smarty->assign('important_icon',$icon);
	$smarty->assign('important_text',$this->Lang('summary_important', $count));
	$smarty->assign('important_link','['
//	.$this->CreateLink(null, 'defaultadmin', null, $this->Lang('view_all'), array('tab'=>'importantfixes')).
	.getTabLink(2,$this->Lang('view_all'))
	.']');
  }else{
	$smarty->assign('important_icon',$icontrue);
	$smarty->assign('important_text',$this->Lang('nothing_to_be_fixed'));
  }
  $j = 0;
  foreach($groups as $group => $galerts) {
	foreach ($galerts as $alert) {
		$onerow = new stdClass;
		$onerow->rowclass = 'row'.($j % 2 + 1);
		if (isset ($alert['pages']))
			$onerow->pages = implode('<br />',$alert['pages']);
		else
			$onerow->pages = '';
		$onerow->problem = $alert['message'];
		if (array_key_exists ('links_data', $alert)) {
			$links = $alert['links_data'];
			if (count ($links) == 1) {
				foreach ($links as $id => $data) {
					$onerow->action = getFixLink ($this, $_GET[$this->pathstr], $id);
					$sig = '@'.$id.'-'.$data[1];
				}
			} else {
				$s = array();
				$sig = '';
				foreach ($links as $id => $data) {
					$s[] = getFixLink ($this, $_GET[$this->pathstr], $id, $data[0]);
					$sig .= '@'.$id.'-'.$data[1];
				}
				$onerow->action = implode('<br />', $s);
				$onerow->checkval = $sig;
				unset ($s);
			}
		} elseif (array_key_exists ('links', $alert)) {
			$links = 'TODO'; //QQQ
			$onerow->action = implode('<br />',$alert['links']);
			$sig = '';
		}
		else {
			$links = 'NONE';
			$sig = '';
		}

		if (array_key_exists ('ignored', $alert)) {
			$iname = ($alert['ignored']) ? 'true':'false';
			$onerow->ignored = $this->CreateTooltipLink(null, 'defaultadmin', '',
			 '<img src="'.$theme_url.'/system/'.$iname.'.gif" style="vertical-align:middle;" />',
			 $this->Lang('toggle'), array('what'=>'toggle_ignore','content_data'=>$sig,'tab'=>'importantfixes'));
			$onerow->checkval = $sig;
		}
		else {
			$onerow->ignored = '';
			$onerow->checkval = '';
		}
		if (array_key_exists ('active', $alert)) {
			if(strpos ($alert['active'],',') === FALSE) {
				$act1 = $alert['active'];
				$act2 = FALSE;
			} else {
				list($act1, $act2) = explode(',',$alert['active']);
				$act1 = (int)$act1;
				$act2 = (int)$act2;
			}
			$cb = '<input type="checkbox" disabled="disabled"';
			if ($act1) $cb .= ' checked="checked"';
			$cb .= ' />';
			if ($act2 !== FALSE) {
				$cb .= '<br /><input type="checkbox" disabled="disabled"';
				if ($act2) $cb .= ' checked="checked"';
				$cb .= ' />';
			}
			$onerow->active = $cb;
			$onerow->sel = ''; //TODO
		}
		else {
			$onerow->active = '';
			$onerow->sel = ''; //TODO
		}
		$important[] = $onerow;
		$j++;
	}
  }
}else{
	$smarty->assign('important_icon',$icontrue);
	$smarty->assign('important_text',$this->Lang('nothing_to_be_fixed'));
}
$smarty->assign('importants',$important);

$notice = array();
$notice_alerts = getNoticeAlerts($this);
if ($notice_alerts) {
  $icon = '<img src="'.$theme_url.'/Notifications/3.gif" />';
  foreach ($notice_alerts as $alert) {
	$onerow = new stdClass;
	$onerow->icon = $icon;
	$onerow->text = $alert['message'];
	if (isset ($alert['links'])) $onerow->link = '['.implode(' | ',$alert['links']).']';
	$notice[] = $onerow;
  }
}else{
  $onerow = new stdClass;
  $onerow->icon = $icontrue;
  $onerow->text = $this->Lang('nothing_to_be_fixed');
  $notice[] = $onerow;
}
$smarty->assign('notices',$notice);

$smarty->assign('start_resources_set',$this->CreateFieldsetStart(null, 'resources',$this->Lang('title_resources')));
$smarty->assign('resource_links',array(
 '<a href="http://validator.w3.org">W3C validator</a>',
 '<a href="http://brokenlinkcheck.com">Link checker</a>',
 '<a href="http://www.feedthebot.com/tools">FeedtheBot</a>',
 '<a href="http://www.siteliner.com">Siteliner</a>'
));

$smarty->assign('title_pages',$this->Lang('title_pages'));
$smarty->assign('title_active',$this->Lang('title_active'));
$smarty->assign('title_problem',$this->Lang('title_problem'));
$smarty->assign('title_ignored',$this->Lang('title_ignored'));
$smarty->assign('title_action',$this->Lang('title_action'));
$smarty->assign('ignore1',$this->CreateInputSubmit(null, 'ignore_selected',
	$this->Lang('ignore'),'title="'.$this->Lang('help_ignore').'" onclick="return confirm_click(\'urgent\');"'));
$smarty->assign('unignore1',$this->CreateInputSubmit(null, 'unignore_selected',
	$this->Lang('unignore'),'title="'.$this->Lang('help_unignore').'" onclick="return confirm_click(\'urgent\');"'));
$smarty->assign('ignore2',$this->CreateInputSubmit(null, 'ignore_selected',
	$this->Lang('ignore'),'title="'.$this->Lang('help_ignore').'" onclick="return confirm_click(\'important\');"'));
$smarty->assign('unignore2',$this->CreateInputSubmit(null, 'unignore_selected',
	$this->Lang('unignore'),'title="'.$this->Lang('help_unignore').'" onclick="return confirm_click(\'important\');"'));

/* Page settings Tab */

$smarty->assign('startform_pages',$this->CreateFormStart($id, 'allindex'));
//$smarty->assign('title_id',$this->Lang('page_id'));
$smarty->assign('title_name',$this->Lang('page_name'));
$smarty->assign('title_priority',$this->Lang('priority'));
$smarty->assign('title_ogtype',$this->Lang('og_type'));
$smarty->assign('title_keywords',$this->Lang('keywords'));
$smarty->assign('title_desc',$this->Lang('description'));
$smarty->assign('title_index',$this->Lang('title_index'));

$iconreset = '<img src="'.$this->GetModuleURLPath().'/images/reset.png" class="systemicon" />';
$iconedit = '<img src="'.$theme_url.'/system/edit.gif" class="systemicon" />';
$icondown = '<img src="'.$theme_url.'/system/arrow-d.gif" class="systemicon" />';
$iconup = '<img src="'.$theme_url.'/system/arrow-u.gif" class="systemicon" />';
$default_ogtype = $this->GetPreference('meta_opengraph_type','');

$items = array();

$pagesettings = '';

$query = 'SELECT * FROM '.$pre.'content ORDER BY hierarchy ASC';
$result = $db->Execute($query);

$j = 0;
while ($page = $result->fetchRow()) {

    $prefix = '';
    $auto_priority = 80;
    $n = substr_count ($page['hierarchy'],'.');
    for ($i = 0; $i < $n; $i++) {
        $prefix .= '&raquo; ';
        $auto_priority  = $auto_priority / 2;
    }
    if ($page['default_content'] == 1) {
        $auto_priority = 100;
    }

    $onerow = new stdClass;
    $onerow->rowclass = 'row'.($j % 2 + 1);
    $onerow->name = $prefix.' '.$page['content_name'];

    if (strpos ($page['type'],'content') === 0) { //any content type
		$query = "SELECT content FROM ".$pre."content_props WHERE content_id = ? AND prop_name = ?";
		$parms = array($page['content_id']);
		$parms[] = str_replace(' ','_',$this->GetPreference('description_block',''));
		$description = $db->GetOne($query,$parms);
		$description_auto = FALSE;
		$funcs = new SEO_keyword();
		$kw = array_flip($funcs->getKeywordSuggestions($page['content_id'],$this));
		if (($description == FALSE) && ($this->GetPreference('description_auto_generate',FALSE))) {
			$last_keyword = array_pop($kw);
			$keywords = implode(', ',$kw) . " " . $this->Lang('and') . " " . $last_keyword;
			$description = $this->Lang('auto_generated').": ".str_replace('{keywords}',$keywords,$this->GetPreference('description_auto',''));
			$description = str_replace('{title}',$page['content_name'],$description);
			$description_auto = TRUE;
		}

		$updown = '';
		if ($auto_priority > 10) {
	    	$updown .= $this->CreateTooltipLink(null, 'defaultadmin', '', $icondown, $this->Lang('decrease_priority'), array('what'=>'set_priority','priority'=>$auto_priority-10,'content_id'=>$page['content_id']));
		}
		if ($auto_priority <= 90) {
			$updown .= $this->CreateTooltipLink(null, 'defaultadmin', '', $iconup, $this->Lang('increase_priority'), array('what'=>'set_priority','priority'=>$auto_priority+10,'content_id'=>$page['content_id']));
		}
		$priority = '('.$this->Lang('auto').') '.$auto_priority.'%';
		$ogtype = '('.$this->Lang('default').') '.$default_ogtype.' '.$this->CreateTooltipLink(null, 'defaultadmin', '', $iconedit, $this->Lang('edit_value'), array('what'=>'edit_ogtype','content_id'=>$page['content_id']));
		$keywords = '('.$this->Lang('auto').') '.count($kw).' '.$this->CreateTooltipLink(null, 'defaultadmin', '', $iconedit, implode(', ',$kw).'; '.$this->Lang('edit_value'), array('what'=>'edit_keywords','content_id'=>$page['content_id']));
		$iname = 'true';

		$query = "SELECT * FROM ".$pre."module_seotools WHERE content_id = ?";
		$info = $db->GetRow($query,array($page['content_id']));
		if ($info && $info['content_id'] != '') {
			if ($info['priority'] != 0) {
			  $priority = '<strong>'.$info['priority'] . '% '.$this->CreateTooltipLink(null, 'defaultadmin', '', $iconreset, $this->Lang('reset_to_default'), array('what'=>'reset_priority','content_id'=>$page['content_id'])) . '</strong>';
			  $auto_priority = $info['priority'];
			}
			if ($info['ogtype'] != '') {
			  $ogtype = '<strong>'.$info['ogtype'] . ' '
			  . $this->CreateTooltipLink(null, 'defaultadmin', '', $iconreset, $this->Lang('reset_to_default'), array('what'=>'reset_ogtype','content_id'=>$page['content_id']))
			  . $this->CreateTooltipLink(null, 'defaultadmin', '', $iconedit, $this->Lang('edit_value'), array('what'=>'edit_ogtype','content_id'=>$page['content_id'])).'</strong>';
			}
			if ($info['keywords'] != '') {
				$keywords = '<strong>'.count(explode(' ',$info['keywords']))
				. $this->CreateTooltipLink(null, 'defaultadmin', '', $iconreset, $this->Lang('reset_to_default'), array('what'=>'reset_keywords','content_id'=>$page['content_id']))
				. $this->CreateTooltipLink(null, 'defaultadmin', '', $iconedit, $this->Lang('edit_value'), array('what'=>'edit_keywords','content_id'=>$page['content_id'])).'</strong>';
			}
			if (!$info['indexable']) {
			  $iname = 'false';
			}
		}
		unset ($info);

		$onerow->priority = $updown.' '.$priority;
		$onerow->ogtype = $ogtype;
		$onerow->keywords = $keywords;
		if ($description != '') {
			$inm2 = ($description_auto) ? 'warning' : 'true';
			$onerow->desc ='<img src="'.$theme_url.'/system/'.$inm2.'.gif" title="'.strip_tags($description).'" style="vertical-align:middle;" />';
		}else{
			$onerow->desc = '<a href="editcontent.php?'.$this->pathstr.'='.$_GET[$this->pathstr].
			'&content_id='.$page['content_id'].'"><img src="'.$theme_url.'/system/false.gif" title="'.
			$this->Lang('click_to_add_description').'" style="vertical-align:middle;" /></a>';
		}
		$onerow->index = $this->CreateTooltipLink(null, 'defaultadmin', '',
			'<img src="'.$theme_url.'/system/'.$iname.'.gif" style="vertical-align:middle;" />',
			$this->Lang('toggle'), array('what'=>'toggle_index','content_id'=>$page['content_id']));
		$onerow->checkval = $page['content_id'];
		$onerow->sel = ''; //TODO
	} else {
		$onerow->priority = '---';
		$onerow->ogtype = '';
		$onerow->keywords = '';
		$onerow->desc = '';
		$onerow->index = '';
		$onerow->checkval = '';
		$onerow->sel = '';
	}
    $items[] = $onerow;
    $j++;
}
$smarty->assign('items',$items);
$smarty->assign('index',$this->CreateInputSubmit(null, 'index_selected',
	$this->Lang('index'),'title="'.$this->Lang('help_index').'" onclick="return confirm_click(\'indx\');"'));
$smarty->assign('unindex',$this->CreateInputSubmit(null, 'unindex_selected',
	$this->Lang('unindex'),'title="'.$this->Lang('help_unindex').'" onclick="return confirm_click(\'indx\');"'));

/* SEO Settings Tab */

// Get image files from /uploads/images
$files_list = array('('.$this->Lang('none').')'=>'');
$dp = opendir (cms_join_path ($config['root_path'],'uploads','images'));
while ($file = readdir($dp)) {
    if (strpos(substr($file, -5),'.gif') !== FALSE) {
        $files_list[$file] = $file;
    }
    if (strpos(substr($file, -5),'.png') !== FALSE) {
        $files_list[$file] = $file;
    }
    if (strpos(substr($file, -5),'.jpg') !== FALSE) {
        $files_list[$file] = $file;
    }
    if (strpos(substr($file, -5),'.jpeg') !== FALSE) {
        $files_list[$file] = $file;
    }
}
closedir($dp);

$smarty->assign('cancel',$this->CreateInputSubmit(null, 'cancel', $this->Lang('cancel')));

$smarty->assign('startform_settings',$this->CreateFormStart($id, 'changesettings')); //several uses
/* Page Title */

$smarty->assign('pr_ctype',$this->Lang('title_type'));
$smarty->assign('in_ctype',$this->CreateInputText(null, 'content_type', $this->GetPreference('content_type','html'), 10)
	.'<br />'.$this->Lang('help_content_type'));
$smarty->assign('start_page_set',$this->CreateFieldsetStart(null, 'title_description', $this->Lang('title_title_description')));
$smarty->assign('pr_ptitle',$this->Lang('title_title'));
$smarty->assign('in_ptitle',$this->CreateInputText(null, 'title', $this->GetPreference('title','{title} | {$sitename} - {$title_keywords}'), 60)
	.'<br />'.$this->Lang('title_title_help'));
$smarty->assign('pr_mtitle',$this->Lang('title_meta_title'));
$smarty->assign('in_mtitle',$this->CreateInputText(null, 'meta_title', $this->GetPreference('meta_title','{title} | {$sitename}'), 60)
	.'<br />'.$this->Lang('title_meta_help'));
$smarty->assign('pr_blockname',$this->Lang('title_description_block'));
$smarty->assign('in_blockname',$this->CreateInputText(null, 'description_block', $this->GetPreference('description_block',''), 60)
	.'<br />'.$this->Lang('description_block_help'));
$smarty->assign('pr_autodesc',$this->Lang('description_auto_generate'));
$smarty->assign('in_autodesc',$this->CreateInputCheckbox(null, 'description_auto_generate', 1, $this->GetPreference('description_auto_generate',0)));
$smarty->assign('pr_autotext',$this->Lang('description_auto_title'));
$smarty->assign('in_autotext',$this->CreateInputText(null, 'description_auto', $this->GetPreference('description_auto','This page covers the topics {keywords}'), 40)
	.'<br />'.$this->Lang('description_auto_help'));
/* META Types */
$smarty->assign('start_meta_set',$this->CreateFieldsetStart(null, 'meta_type', $this->Lang('title_meta_type')));
$smarty->assign('pr_meta_stand',$this->Lang('meta_create_standard'));
$smarty->assign('in_meta_stand',$this->CreateInputCheckbox(null, 'meta_standard', 1, $this->GetPreference('meta_standard',0)));
$smarty->assign('pr_meta_dublin',$this->Lang('meta_create_dublincore'));
$smarty->assign('in_meta_dublin',$this->CreateInputCheckbox(null, 'meta_dublincore', 1, $this->GetPreference('meta_dublincore',0)));
$smarty->assign('pr_meta_open',$this->Lang('meta_create_opengraph'));
$smarty->assign('in_meta_open',$this->CreateInputCheckbox(null, 'meta_opengraph', 1, $this->GetPreference('meta_opengraph',0)));
/* META Defaults */
$smarty->assign('start_deflt_set',$this->CreateFieldsetStart(null, 'meta_defaults', $this->Lang('title_meta_defaults')));
$smarty->assign('pr_publish',$this->Lang('meta_publisher'));
$smarty->assign('in_publish',$this->CreateInputText(null, 'meta_publisher', $this->GetPreference('meta_publisher',''), 32)
	.'<br />'.$this->Lang('meta_publisher_help'));
$smarty->assign('pr_contrib',$this->Lang('meta_contributor'));
$smarty->assign('in_contrib',$this->CreateInputText(null, 'meta_contributor', $this->GetPreference('meta_contributor',''), 32)
	.'<br />'.$this->Lang('meta_contributor_help'));
$smarty->assign('pr_copyr',$this->Lang('meta_copyright'));
$smarty->assign('in_copyr',$this->CreateInputText(null, 'meta_copyright', $this->GetPreference('meta_copyright','(C) '.date('Y').'. All rights reserved.'), 32)
	.'<br />'.$this->Lang('meta_copyright_help'));
$smarty->assign('intro_location',$this->Lang('meta_location_description'));
$smarty->assign('pr_location',$this->Lang('meta_location'));
$smarty->assign('in_location',$this->CreateInputText(null, 'meta_location', $this->GetPreference('meta_location',''), 32)
	.'<br />'.$this->Lang('meta_location_help'));
$smarty->assign('pr_region',$this->Lang('meta_region'));
$smarty->assign('in_region',$this->CreateInputText(null, 'meta_region', $this->GetPreference('meta_region',''), 5, 5)
	.'<br />'.$this->Lang('meta_region_help'));
$smarty->assign('pr_lat',$this->Lang('meta_latitude'));
$smarty->assign('in_lat',$this->CreateInputText(null, 'meta_latitude', $this->GetPreference('meta_latitude',''), 15)
	.'<br />'.$this->Lang('meta_latitude_help'));
$smarty->assign('pr_long',$this->Lang('meta_longitude'));
$smarty->assign('in_long',$this->CreateInputText(null, 'meta_longitude', $this->GetPreference('meta_longitude',''), 15)
	.'<br />'.$this->Lang('meta_longitude_help'));
$smarty->assign('intro_ogmeta',$this->Lang('meta_opengraph_description'));
$smarty->assign('pr_ogtitle',$this->Lang('meta_opengraph_title'));
$smarty->assign('in_ogtitle',$this->CreateInputText(null, 'meta_opengraph_title', $this->GetPreference('meta_opengraph_title','{title}'), 32)
	.'<br />'.$this->Lang('meta_opengraph_title_help'));
$smarty->assign('pr_ogtype',$this->Lang('meta_opengraph_type'));
$smarty->assign('in_ogtype',$this->CreateInputText(null, 'meta_opengraph_type', $this->GetPreference('meta_opengraph_type',''), 32)
	.'<br />'.$this->Lang('meta_opengraph_type_help'));
$smarty->assign('pr_ogsite',$this->Lang('meta_opengraph_sitename'));
$smarty->assign('in_ogsite',$this->CreateInputText(null, 'meta_opengraph_sitename', $this->GetPreference('meta_opengraph_sitename',''), 32)
	.'<br />'.$this->Lang('meta_opengraph_sitename_help'));
$smarty->assign('pr_ogimage',$this->Lang('meta_opengraph_image'));
$smarty->assign('in_ogimage',$this->CreateInputDropdown(null, 'meta_opengraph_image', $files_list, null, $this->GetPreference('meta_opengraph_image',''))
	.'<br />'.$this->Lang('meta_opengraph_image_help'));
$smarty->assign('pr_ogadmin',$this->Lang('meta_opengraph_admins'));
$smarty->assign('in_ogadmin',$this->CreateInputText(null, 'meta_opengraph_admins', $this->GetPreference('meta_opengraph_admins',''), 32)
	.'<br />'.$this->Lang('meta_opengraph_admins_help'));
$smarty->assign('pr_ogapp',$this->Lang('meta_opengraph_application'));
$smarty->assign('in_ogapp',$this->CreateInputText(null, 'meta_opengraph_application', $this->GetPreference('meta_opengraph_application',''), 32)
	.'<br />'.$this->Lang('meta_opengraph_application_help'));
/* Additional Meta Tags */
$smarty->assign('start_extra_set',$this->CreateFieldsetStart(null, 'additional_meta', $this->Lang('title_additional_meta_tags')));
$smarty->assign('pr_extra',$this->Lang('additional_meta_tags_title'));
$smarty->assign('in_extra',
	$this->CreateTextArea(FALSE, null, $this->GetPreference('additional_meta_tags',''), 'additional_meta_tags', '', '', '', '', '60', '1','','','style="height:10em;"')
	.'<br />'.$this->Lang('additional_meta_tags_help'));

$smarty->assign('submit1',$this->CreateInputSubmit(null, 'save_meta_settings', $this->Lang('save')));

/* SITEMAP Settings */

$smarty->assign('start_map_set',$this->CreateFieldsetStart(null, 'sitemap_description', $this->Lang('title_sitemap_description')));
$smarty->assign('pr_create_map',$this->Lang('create_sitemap_title'));
$smarty->assign('in_create_map',$this->CreateInputCheckbox(null, 'create_sitemap', 1, $this->GetPreference('create_sitemap',0)));
$smarty->assign('pr_push_map',$this->Lang('push_sitemap_title'));
if (ini_get('allow_url_fopen')) {
	$smarty->assign('in_push_map',$this->CreateInputCheckbox(null, 'push_sitemap', 1, $this->GetPreference('push_sitemap',0)));
} else {
	$smarty->assign('input_push_map',$this->Lang('no_url_fopen'));
}
$smarty->assign('pr_verify_code',$this->Lang('verification_title'));
$smarty->assign('in_verify_code',$this->CreateInputText(null, 'verification', $this->GetPreference('verification',''), 40));
$smarty->assign('help_verify',$this->Lang('verification_help'));
$smarty->assign('pr_create_bots',$this->Lang('create_robots_title'));
$smarty->assign('in_create_bots',$this->CreateInputCheckbox(null, 'create_robots', 1, $this->GetPreference('create_robots',0)));
$smarty->assign('submit2',$this->CreateInputSubmit(null, 'save_sitemap_settings', $this->Lang('save')));

if ($this->GetPreference('create_sitemap',0))
{
	if ($this->GetPreference('create_robots',0))
		$title = $this->Lang('button_regenerate_both');
	else
		$title = $this->Lang('button_regenerate_sitemap');
}
elseif ($this->GetPreference('create_robots',0))
	$title = $this->Lang('button_regenerate_robot');
else
	$title = null;

if ($title != null) {
	$smarty->assign('start_regen_set',$this->CreateFieldsetStart(null, 'regenerate_sitemap', $this->Lang('title_regenerate_both')));
	$smarty->assign('help_regenerate',$this->Lang('text_regenerate_sitemap'));
	$smarty->assign('regenerate',$this->CreateInputSubmit(null, 'do_regenerate', $title));
	$smarty->assign('sitemap_help',$this->Lang('help_sitemap_robots'));
}

/* KEYWORD Settings */
$smarty->assign('start_ksettings_set',$this->CreateFieldsetStart(null, 'keyword_weight_description', $this->Lang('title_keyword_weight')));

$smarty->assign('pr_wordsblock_name',$this->Lang('title_keyword_block'));
$smarty->assign('in_wordsblock_name',$this->CreateInputText(null, 'keyword_block', $this->GetPreference('keyword_block',''), 60)
	.'<br />'.$this->Lang('keyword_block_help'));
$smarty->assign('pr_kw_sep',$this->Lang('keyword_separator_title'));
$smarty->assign('in_kw_sep',$this->CreateInputText(null, 'keyword_separator', $this->GetPreference('keyword_separator',' '), 1)
	.'<br />'.$this->Lang('keyword_separator_help'));
$smarty->assign('pr_min_length',$this->Lang('keyword_minlength_title'));
$smarty->assign('in_min_length',$this->CreateInputText(null, 'keyword_minlength', $this->GetPreference('keyword_minlength','6'), 2)
	.'<br />'.$this->Lang('keyword_minlength_help'));
$smarty->assign('pr_title_weight',$this->Lang('keyword_title_weight_title'));
$smarty->assign('in_title_weight',$this->CreateInputText(null, 'keyword_title_weight', $this->GetPreference('keyword_title_weight','6'), 2)
	.'<br />'.$this->Lang('keyword_title_weight_help'));
$smarty->assign('pr_desc_weight',$this->Lang('keyword_description_weight_title'));
$smarty->assign('in_desc_weight',$this->CreateInputText(null, 'keyword_description_weight', $this->GetPreference('keyword_description_weight','4'), 2)
	.'<br />'.$this->Lang('keyword_description_weight_help'));
$smarty->assign('pr_head_weight',$this->Lang('keyword_headline_weight_title'));
$smarty->assign('in_head_weight',$this->CreateInputText(null, 'keyword_headline_weight', $this->GetPreference('keyword_headline_weight','2'), 2)
	.'<br />'.$this->Lang('keyword_headline_weight_help'));
$smarty->assign('pr_cont_weight',$this->Lang('keyword_content_weight_title'));
$smarty->assign('in_cont_weight',$this->CreateInputText(null, 'keyword_content_weight', $this->GetPreference('keyword_content_weight','1'), 2)
	.'<br />'.$this->Lang('keyword_content_weight_help'));
$smarty->assign('pr_min_weight',$this->Lang('keyword_minimum_weight_title'));
$smarty->assign('in_min_weight',$this->CreateInputText(null, 'keyword_minimum_weight', $this->GetPreference('keyword_minimum_weight','7'), 2)
	.'<br />'.$this->Lang('keyword_minimum_weight_help'));
$smarty->assign('start_kexclude_set',$this->CreateFieldsetStart(null, 'keyword_exclude_description', $this->Lang('title_keyword_exclude')));

$smarty->assign('pr_incl_words',$this->Lang('default_keywords_title'));
$smarty->assign('in_incl_words',
	$this->CreateTextArea(FALSE, null, $this->GetPreference('default_keywords',''), 'default_keywords', '', '', '', '', '60', '1','','','style="height:5em;"')
	.'<br />'.$this->Lang('default_keywords_help'));
$smarty->assign('pr_excl_words',$this->Lang('keyword_exclude_title'));
$smarty->assign('in_excl_words',
	$this->CreateTextArea(FALSE, null, $this->GetPreference('keyword_exclude',''), 'keyword_exclude', '', '', '', '', '60', '1','','','style="height:5em;"')
	.'<br />'.$this->Lang('keyword_exclude_help'));
$smarty->assign('submit3',$this->CreateInputSubmit(null, 'save_keyword_settings', $this->Lang('save')));
$smarty->assign('keyword_help',$this->Lang('help_keyword_generator'));

echo $this->ProcessTemplate('adminpanel.tpl');

?>
