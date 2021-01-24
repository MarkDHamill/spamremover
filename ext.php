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
		if (!extension_loaded('sockets'))
		{
			$language = $this->container->get('language');
			$language->add_lang(array('common'), 'phpbbservices/spamremover');
			$message_type = E_USER_WARNING;
			$message = $language->lang('ACP_SPAMREMOVER_INSTALL_REQUIREMENTS');
			trigger_error($message, $message_type);
			return false;
		}
		else
		{
			return true;
		}
	}
}
