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
 * Spam remover ACP module info.
 */
class main_info
{
	public function module()
	{
		return array(
			'filename'	=> '\phpbbservices\spamremover\acp\main_module',
			'title'		=> 'ACP_SPAMREMOVER_TITLE',
			'modes'		=> array(
				'settings'	=> array(
					'title'	=> 'ACP_SPAMREMOVER_SETTINGS',
					'auth'	=> 'ext_phpbbservices/spamremover && acl_a_board',
					'cat'	=> array('ACP_SPAMREMOVER_TITLE')
				),
				'find'	=> array(
					'title'	=> 'ACP_SPAMREMOVER_FIND_SPAM',
					'auth'	=> 'ext_phpbbservices/spamremover && acl_a_board',
					'cat'	=> array('ACP_SPAMREMOVER_TITLE')
				),
				'summary'	=> array(
					'title'	=> 'ACP_SPAMREMOVER_SPAM_SUMMARY',
					'auth'	=> 'ext_phpbbservices/spamremover && acl_a_board',
					'cat'	=> array('ACP_SPAMREMOVER_TITLE')
				),
				'detail_posts'	=> array(
					'title'	=> 'ACP_SPAMREMOVER_SPAM_DETAIL_POSTS',
					'auth'	=> 'ext_phpbbservices/spamremover && acl_a_board',
					'cat'	=> array('ACP_SPAMREMOVER_TITLE')
				),
				'detail_pms'	=> array(
					'title'	=> 'ACP_SPAMREMOVER_SPAM_DETAIL_PMS',
					'auth'	=> 'ext_phpbbservices/spamremover && acl_a_board',
					'cat'	=> array('ACP_SPAMREMOVER_TITLE')
				),
				'bulk_remove'	=> array(
					'title'	=> 'ACP_SPAMREMOVER_BULK_REMOVE_SPAM',
					'auth'	=> 'ext_phpbbservices/spamremover && acl_a_board',
					'cat'	=> array('ACP_SPAMREMOVER_TITLE')
				),
				'reset'	=> array(
					'title'	=> 'ACP_SPAMREMOVER_RESET',
					'auth'	=> 'ext_phpbbservices/spamremover && acl_a_board',
					'cat'	=> array('ACP_SPAMREMOVER_TITLE')
				),
			),
		);
	}
}
