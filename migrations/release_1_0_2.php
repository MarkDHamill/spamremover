<?php
/**
 * Spam remover. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2021, MarkDHamill, https://www.phpbbservices.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbservices\spamremover\migrations;

class release_1_0_2 extends \phpbb\db\migration\migration
{

	public function effectively_installed()
	{
		return $this->config->offsetExists('phpbbservices_spamremover_test_mode');
	}

	static public function depends_on()
	{
		return array(
			'\phpbbservices\spamremover\migrations\install_schema',
			'\phpbb\db\migration\data\v330\v330',
		);
	}

	public function update_data()
	{
		return array(
			array('config.add',	array('phpbbservices_spamremover_test_mode', 0)),
		);
	}

}

