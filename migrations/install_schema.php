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

class install_schema extends \phpbb\db\migration\migration
{

	static public function depends_on()
	{
		return array(
			'\phpbb\db\migration\data\v330\v330',
			'\phpbbservices\spamremover\migrations\install_acp_modules',
		);
	}

	public function update_schema()
	{
		return array(
			'add_tables'    => array(
				$this->table_prefix . 'spam_found'        => array(
					'COLUMNS'       		=> array(
						'post_msg_id' 		=> array('INT:10', 0),
						'is_post'			=> array('TINT:1', 1),
						'is_blatant_spam'	=> array('TINT:1', 0),
						'spam_check_time'	=> array('INT:11', 0)
					),
					'PRIMARY_KEY'       	=> array('is_post', 'post_msg_id'),
                ),
			)
		);

	}

	public function revert_schema()
	{
		return array(
			'drop_tables'    =>
				array($this->table_prefix . 'spam_found'),
		);
	}

}

