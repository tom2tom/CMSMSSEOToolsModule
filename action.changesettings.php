<?php
# This file is part of CMS Made Simple module: SEOTools.
# Copyright (C) 2010-2011 Henning Schaefer <henning.schaefer@gmail.com>
# Copyright (C) 2014-2015 Tom Phane <tpgww@onepost.net>
# Refer to licence and other details at the top of file SEOTools.module.php

# Regenerate sitemap.xml and/or robots.txt, according to preferences

if (isset($_POST['cancel']))
	$this->Redirect($id, 'defaultadmin');

if (!$this->CheckAccess('Edit SEO Settings'))
	return $this->DisplayErrorPage($this->Lang('accessdenied'));

if (isset($_POST['do_regenerate'])) {
	$funcs = new SEO_file();
	$botok = ($this->GetPreference('create_robots',0)) ? $funcs->createRobotsTXT($this) : FALSE;
	$mapok = ($this->GetPreference('create_sitemap',0)) ? $funcs->createSitemap($this) : FALSE;

	if ($botok || $mapok) {
		if ($botok && $mapok) {
			$audit = 'Manually regenerated sitemap.xml and robots.txt';
			$msgkey = 'both_regenerated';
		}
		elseif ($botok) {
			$audit = 'Manually regenerated robots.txt';
			$msgkey = 'robot_regenerated';
		}
		else {
			$audit = 'Manually regenerated sitemap.xml';
			$msgkey = 'sitemap_regenerated';
		}
		$this->Audit(0, $this->Lang('friendlyname'), $audit);
		$this->Redirect($id, 'defaultadmin', '', array('message'=>$msgkey,'tab'=>'sitemapsettings'));
	}
	else {
		$this->Redirect($id, 'defaultadmin', '', array('warning'=>1,'message'=>'none_regenerated','tab'=>'sitemapsettings'));
	}
}

if (isset($_POST['save_meta_settings'])) {
	$this->SetPreference('meta_standard',$_POST['meta_standard']);
	$this->SetPreference('meta_dublincore',$_POST['meta_dublincore']);
	$this->SetPreference('meta_opengraph',$_POST['meta_opengraph']);
	$this->SetPreference('additional_meta_tags',$_POST['additional_meta_tags']);

	$this->SetPreference('meta_publisher',$_POST['meta_publisher']);
	$this->SetPreference('meta_contributor',$_POST['meta_contributor']);
	$this->SetPreference('meta_copyright',$_POST['meta_copyright']);

	$this->SetPreference('meta_location',$_POST['meta_location']);
	$this->SetPreference('meta_region',$_POST['meta_region']);
	$this->SetPreference('meta_latitude',$_POST['meta_latitude']);
	$this->SetPreference('meta_longitude',$_POST['meta_longitude']);

	$this->SetPreference('meta_opengraph_title',$_POST['meta_opengraph_title']);
	$this->SetPreference('meta_opengraph_type',$_POST['meta_opengraph_type']);
	$this->SetPreference('meta_opengraph_sitename',$_POST['meta_opengraph_sitename']);
	$this->SetPreference('meta_opengraph_image',$_POST['meta_opengraph_image']);
	$this->SetPreference('meta_opengraph_admins',$_POST['meta_opengraph_admins']);
	$this->SetPreference('meta_opengraph_application',$_POST['meta_opengraph_application']);

	$this->SetPreference('content_type',$_POST['content_type']);
	$this->SetPreference('title',$_POST['title']);
	$this->SetPreference('meta_title',$_POST['meta_title']);
	$this->SetPreference('description_block',$_POST['description_block']);
	$this->SetPreference('description_auto_generate',$_POST['description_auto_generate']);
	$this->SetPreference('description_auto',$_POST['description_auto']);

	$this->Audit(0, $this->Lang('friendlyname'), 'Edited META settings');
	$this->Redirect($id, 'defaultadmin', '', array('message'=>'settings_updated','tab'=>'metasettings'));
}

if (isset($_POST['save_sitemap_settings'])) {
	$val = (isset($_POST['create_sitemap'])) ? 1 : 0;
	$this->SetPreference('create_sitemap', $val);
	$val = (isset($_POST['push_sitemap'])) ? 1 : 0;
	$this->SetPreference('push_sitemap', $val);
	$val = (isset($_POST['create_robots'])) ? 1 : 0;
	$this->SetPreference('create_robots', $val);
	$val = (isset($_POST['r_before'])) ? $_POST['r_before'] : '';
	$this->SetPreference('r_before',$val);
	$val = (isset($_POST['r_after'])) ? $_POST['r_after'] : '';
	$this->SetPreference('r_after',$val);
	$this->SetPreference('verification', $_POST['verification']);

	$this->Audit(0, $this->Lang('friendlyname'), 'Edited sitemap settings');
	$this->Redirect($id, 'defaultadmin', '', array('message'=>'settings_updated','tab'=>'sitemapsettings'));
}

if (isset($_POST['save_keyword_settings'])) {
	$val = $this->GetPreference('keyword_block','');
	$new = $_POST['keyword_block'];
	if ($new && $new != $val) {
		$pre = cms_db_prefix();
		$old = str_replace(' ','_',$val);
		$new = str_replace(' ','_',$new);
		// conform tabled properties
		$db->Execute('UPDATE '.$pre.'content_props SET prop_name=? WHERE prop_name=?',
			array($new,$old));
		//CHECKME conform block-name in all templates and pages?
		$this->SetPreference('keyword_block',$_POST['keyword_block']);
		$old = $new; //maybe needed for content-updates
	}
	else {
		$old = str_replace(' ','_',$val);
	}
	$this->SetPreference('keyword_minlength',$_POST['keyword_minlength']);
	$this->SetPreference('keyword_title_weight',$_POST['keyword_title_weight']);
	$this->SetPreference('keyword_description_weight',$_POST['keyword_description_weight']);
	$this->SetPreference('keyword_headline_weight',$_POST['keyword_headline_weight']);
	$this->SetPreference('keyword_content_weight',$_POST['keyword_content_weight']);
	$this->SetPreference('keyword_minimum_weight',$_POST['keyword_minimum_weight']);

	$val = $this->GetPreference('keyword_separator',' ');
	$new = $_POST['keyword_separator'];
	if ($new && $new != $val) {
		$words = explode($val,$_POST['default_keywords']);
		$this->SetPreference('default_keywords',implode($new,$words));
		$words = explode($val,$_POST['keyword_exclude']);
		$this->SetPreference('keyword_exclude',implode($new,$words));
		// Replace sep in all tabled keyword fields
		$pre = cms_db_prefix();
		$rst = $db->Execute('SELECT content_id,keywords FROM '.$pre.
			'module_seotools WHERE keywords IS NOT NULL AND keywords!=""');
		if ($rst) {
			$query = 'UPDATE '.$pre.'module_seotools SET keywords=? WHERE content_id=?';
			while (!$rst->EOF) {
				$merge = str_replace($val, $new, $rst->fields[1]);
				$db->Execute($query, array($merge, $rst->fields[0])); //CHECKME OK in this recordset-loop?
				$rst->MoveNext();
			}
			$rst->Close();
		}
		$rst = $db->Execute('SELECT content_id,content FROM '.$pre.
			'content_props WHERE prop_name=? AND content IS NOT NULL AND content!=""',
			array($old));
		if ($rst) {
			$query = 'UPDATE '.$pre.'content_props SET content=? WHERE content_id=?';
			while (!$rst->EOF) {
				$merge = str_replace($val, $new, $rst->fields[1]);
				$db->Execute($query, array($merge, $rst->fields[0])); //CHECKME OK in this recordset-loop?
				$rst->MoveNext();
			}
			$rst->Close();
		}
		$this->SetPreference('keyword_separator',$_POST['keyword_separator']);
	}
	else {
		$this->SetPreference('default_keywords',$_POST['default_keywords']);
		$this->SetPreference('keyword_exclude',$_POST['keyword_exclude']);
	}

	$this->Audit(0, $this->Lang('friendlyname'), 'Edited keyword settings');
	$this->Redirect($id, 'defaultadmin', '', array('message'=>'settings_updated','tab'=>'keywordsettings'));
}

?>
