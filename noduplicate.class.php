<?php

/**
 * @file noduplicate.class.php
 * @author Kijin Sung <kijin@kijinsung.com>
 * @license GPLv2 or Later <https://www.gnu.org/licenses/gpl-2.0.html>
 */
class NoDuplicateAddon
{
	/**
	 * Properties.
	 */
	protected $action_type;
	protected $is_enabled;
	protected $search_range;
	protected $search_time;
	protected $block_action;
	protected $duplicate_info;
	
	/**
	 * Constructor.
	 */
	public function __construct($args)
	{
		$this->action_type = $action_type = $args->action_type;
		$this->is_enabled = isset($args->{'block_' . $action_type}) ? ($args->{'block_' . $action_type} === 'Y' ? true : false) : true;
		$this->search_range = isset($args->{'range_' . $action_type}) ? $args->{'range_' . $action_type} : 'site';
		$this->search_time = isset($args->{'time_' . $action_type}) ? intval($args->{'time_' . $action_type}) : 3600;
		$this->block_action = isset($args->{'action_' . $action_type}) ? $args->{'action_' . $action_type} : 'auto';
	}
	
	/**
	 * Is the current request a duplicate?
	 */
	public function isDuplicate()
	{
		if (!$this->is_enabled)
		{
			return false;
		}
		
		if ($this->action_type === 'document')
		{
			return $this->_isDuplicateDocument();
		}
		else
		{
			return $this->_isDuplicateComment();
		}
	}
	
	/**
	 * Is the current document a duplicate?
	 */
	protected function _isDuplicateDocument()
	{
		// Normalize the currently submitted title and content.
		$title = $this->_normalizeText(Context::get('title'));
		$content = $this->_normalizeText(Context::get('content'));
		if ($title === '' || $content === '')
		{
			return false;
		}
		
		// Combine search conditions.
		$args = new stdClass;
		if ($this->search_range === 'module' && Context::get('mid'))
		{
			if ($module_srls = getModel('module')->getModuleSrlByMid(Context::get('mid')))
			{
				$args->module_srl = reset($module_srls);
			}
		}
		if ($this->search_range === 'category' && Context::get('category_srl'))
		{
			$args->category_srl = Context::get('category_srl');
		}
		if (Context::get('document_srl'))
		{
			$args->not_document_srl = Context::get('document_srl');
		}
		if ($logged_info = Context::get('logged_info'))
		{
			if ($logged_info->member_srl)
			{
				$args->member_srl = $logged_info->member_srl;
			}
		}
		$args->since_regdate = date('YmdHis', time() - $this->search_time);
		
		// Search for matching documents.
		$output = executeQuery('addons.noduplicate.getDocuments', $args);
		if (!is_array($output->data) && $output->data)
		{
			$output->data = array($output->data);
		}
		
		// Find any document with the same content.
		foreach ($output->data as $document)
		{
			if ($this->_normalizeText($document->title) === $title)
			{
				return $this->duplicate_info = $document;
			}
			if ($this->_normalizeText($document->content) === $content)
			{
				return $this->duplicate_info = $document;
			}
		}
		
		// If not found, return false.
		return false;
	}
	
	/**
	 * Is the current comment a duplicate?
	 */
	protected function _isDuplicateComment()
	{
		// Normalize the currently submitted content.
		$content = $this->_normalizeText(Context::get('content'));
		if ($content === '')
		{
			return false;
		}
		
		// Combine search conditions.
		$args = new stdClass;
		if ($this->search_range === 'module' && Context::get('mid'))
		{
			if ($module_srls = getModel('module')->getModuleSrlByMid(Context::get('mid')))
			{
				$args->module_srl = reset($module_srls);
			}
		}
		if ($this->search_range === 'document' && Context::get('document_srl'))
		{
			$args->document_srl = Context::get('document_srl');
		}
		if (Context::get('comment_srl'))
		{
			$args->not_comment_srl = Context::get('comment_srl');
		}
		if ($logged_info = Context::get('logged_info'))
		{
			if ($logged_info->member_srl)
			{
				$args->member_srl = $logged_info->member_srl;
			}
		}
		$args->since_regdate = date('YmdHis', time() - $this->search_time);
		
		// Search for matching documents.
		$output = executeQuery('addons.noduplicate.getComments', $args);
		if (!is_array($output->data) && $output->data)
		{
			$output->data = array($output->data);
		}
		
		// Find any comment with the same content.
		foreach ($output->data as $comment)
		{
			if ($this->_normalizeText($comment->content) === $content)
			{
				return $this->duplicate_info = $comment;
			}
		}
		
		// If not found, return false.
		return false;
	}
	
	/**
	 * Normalize text content.
	 */
	protected function _normalizeText($str)
	{
		// Strip all HTML tags.
		$str = strip_tags($str);
		
		// Decode HTML entities.
		$str = html_entity_decode($str, ENT_QUOTES, 'UTF-8');
		
		// Normalize whitespace.
		$str = preg_replace('/[\pZ\pC]+/u', ' ', $str);
		
		// Trim and return.
		return trim($str);
	}
	
	/**
	 * Automatically redirect to the identical document or comment.
	 */
	public function getRedirectUrl()
	{
		// Check if we should redirect or simply throw an error.
		if (!$this->duplicate_info)
		{
			return false;
		}
		if ($this->block_action === 'error')
		{
			return false;
		}
		if ($this->block_action === 'auto' && ztime($this->duplicate_info->regdate) < time() - 60)
		{
			return false;
		}
		
		// Generate the URL.
		$redirect_info = new stdClass;
		$redirect_info->document_srl = $this->duplicate_info->document_srl;
		
		if ($this->duplicate_info->module_srl)
		{
			$module_info = getModel('module')->getModuleInfoByModuleSrl($this->duplicate_info->module_srl);
			if ($module_info->mid)
			{
				$redirect_info->mid = $module_info->mid;
			}
			else
			{
				$redirect_info->mid = null;
			}
		}
		
		if ($redirect_info->mid !== null)
		{
			$redirect_info->url = getUrl('', 'mid', $redirect_info->mid, 'document_srl', $this->duplicate_info->document_srl);
		}
		else
		{
			$redirect_info->url = getUrl('', 'document_srl', $this->duplicate_info->document_srl);
		}
		
		if (isset($this->duplicate_info->comment_srl) && $this->duplicate_info->comment_srl)
		{
			$redirect_info->url .= '#comment_' . $this->duplicate_info->comment_srl;
			$redirect_info->comment_srl = $this->duplicate_info->comment_srl;
		}
		
		return $redirect_info;
	}
}
