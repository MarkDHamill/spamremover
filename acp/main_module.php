<?php
/**
 *
 * Spam remover. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2021, MarkDHamill, https://www.phpbbservices.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbservices\spamremover\acp;

/**
 * Spam remover ACP module.
 */
class main_module
{
	public $page_title;
	public $tpl_name;
	public $u_action;

	/**
	 * Main ACP module
	 *
	 * @param int    $id   The module ID
	 * @param string $mode The module mode (for example: manage or settings)
	 * @throws \Exception
	 */
	public function main($id, $mode)
	{
		global $phpbb_container;

		/** @var \phpbbservices\spamremover\controller\acp_controller $acp_controller */
		$acp_controller = $phpbb_container->get('phpbbservices.spamremover.controller.acp');

		/** @var \phpbb\language\language $language */
		$language = $phpbb_container->get('language');

		/** @param \phpbb\request\request	$request	Request object */
		$request = $phpbb_container->get('request');

		// Load a template from adm/style for our ACP page
		$this->tpl_name = 'acp_spamremover_body';

		// Get the mode
		$mode = $request->variable('mode', 'settings');

		// Set the page title for our ACP page
		if ($mode === 'settings')
		{
			$this->page_title = $language->lang('ACP_SPAMREMOVER_SETTINGS');
		}
		else if ($mode === 'find')
		{
			$this->page_title = $language->lang('ACP_SPAMREMOVER_FIND_SPAM');
		}
		else if ($mode === 'summary')
		{
			$this->page_title = $language->lang('ACP_SPAMREMOVER_SPAM_SUMMARY');
		}
		else if ($mode === 'detail_posts')
		{
			$this->page_title = $language->lang('ACP_SPAMREMOVER_SPAM_DETAIL_POSTS');
		}
		else if ($mode === 'detail_pms')
		{
			$this->page_title = $language->lang('ACP_SPAMREMOVER_SPAM_DETAIL_PMS');
		}
		else if ($mode === 'bulk_remove')
		{
			$this->page_title = $language->lang('ACP_SPAMREMOVER_BULK_REMOVE_SPAM');
		}
		else if ($mode === 'reset')
		{
			$this->page_title = $language->lang('ACP_SPAMREMOVER_RESET');
		}
		// Make the $u_action url available in our ACP controller
		$acp_controller->set_page_url($this->u_action);

		// Load the display options handle in our ACP controller
		$acp_controller->display_options($mode);
	}
}
