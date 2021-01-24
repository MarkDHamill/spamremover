<?php
/**
 *
 * Spam remover. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2021, MarkDHamill, https://www.phpbbservices.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbservices\spamremover\migrations;

class install_acp_modules extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return isset($this->config['phpbbservices_spamremover_akismet_key']);
	}

	public static function depends_on()
	{
		return array('\phpbb\db\migration\data\v330\v330');
	}

	public function update_data()
	{
		return array(
			array('config.add', array('phpbbservices_spamremover_akismet_key', '')),
			array('config.add', array('phpbbservices_spamremover_batch_size', 50)),
			array('config.add', array('phpbbservices_spamremover_blatant_only', 0)),
			array('config.add', array('phpbbservices_spamremover_find_all_pms', 1)),
			array('config.add', array('phpbbservices_spamremover_find_all_posts', 1)),
			array('config.add', array('phpbbservices_spamremover_items_per_page', 20)),
			array('config.add', array('phpbbservices_spamremover_last_pms_id', 0)),
			array('config.add', array('phpbbservices_spamremover_last_post_id', 0)),
			array('config.add', array('phpbbservices_spamremover_pms', 1)),
			array('config.add', array('phpbbservices_spamremover_pms_end_date', '')),
			array('config.add', array('phpbbservices_spamremover_pms_start_date', '')),
			array('config.add', array('phpbbservices_spamremover_posts', 1)),
			array('config.add', array('phpbbservices_spamremover_posts_end_date', '')),
			array('config.add', array('phpbbservices_spamremover_posts_start_date', '')),
			array('config.add', array('phpbbservices_spamremover_posts_found', 0)),
			array('module.add', array(
				'acp',
				'ACP_CAT_DOT_MODS',
				'ACP_SPAMREMOVER_TITLE'
			)),
			array('module.add', array(
				'acp',
				'ACP_SPAMREMOVER_TITLE',
				array(
					'module_basename'	=> '\phpbbservices\spamremover\acp\main_module',
					'modes'				=> array('settings','find','summary','detail_posts','detail_pms','bulk_remove','reset'),
				),
			)),
		);
	}
}
