<?php

/**
 * @file noduplicate.addon.php
 * @author Kijin Sung <kijin@kijinsung.com>
 * @license GPLv2 or Later <https://www.gnu.org/licenses/gpl-2.0.html>
 */
if (!defined('__XE__')) exit();

/**
 * Detect duplicates before module action.
 */
if ($called_position === 'before_module_proc' && preg_match('/^proc[A-Z][a-z0-9_]+Insert(Document|Comment)$/', $this->act, $matches))
{
	include_once dirname(__FILE__) . '/noduplicate.class.php';
	$addon_info->action_type = strtolower($matches[1]) === 'document' ? 'document' : 'comment';
	$addon_obj = new NoDuplicateAddon($addon_info, $this);
	if ($addon_obj->isDuplicate())
	{
		$this->act .= '_NODUPLICATE_BLOCKED!';
		if ($redirect_info = $addon_obj->getRedirectUrl())
		{
			$this->setRedirectUrl($redirect_info->url);
			$this->add('mid', $redirect_info->mid);
			$this->add('document_srl', $redirect_info->document_srl);
			$this->add('comment_srl', $redirect_info->comment_srl);
		}
		else
		{
			Context::loadLang(dirname(__FILE__) . '/lang');
			if ($addon_info->action_type === 'document')
			{
				$this->stop('msg_noduplicate_is_duplicate_document');
			}
			else
			{
				$this->stop('msg_noduplicate_is_duplicate_comment');
			}
		}
	}
}
