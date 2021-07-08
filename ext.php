<?php
/**
 *
 * Spam remover. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2021, MarkDHamill, https://www.phpbbservices.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbservices\spamremover;

class ext extends \phpbb\extension\base
{
	public function is_enableable()
	{

		$config = $this->container->get('config');
		$language = $this->container->get('language');
		$language->add_lang(array('common'), 'phpbbservices/spamremover');

		if (
			phpbb_version_compare($config['version'], '3.3.0', '<') ||
			phpbb_version_compare($config['version'], '4.0', '>=') ||
			!extension_loaded('sockets'))
		{
			return $language->lang('ACP_SPAMREMOVER_INSTALL_REQUIREMENTS');
		}
		else
		{
			return true;
		}
	}
}
