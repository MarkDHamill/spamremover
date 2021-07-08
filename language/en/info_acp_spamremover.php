<?php
/**
 *
 * Spam remover. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2021, MarkDHamill, https://www.phpbbservices.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = array();
}

// DEVELOPERS PLEASE NOTE
//
// All language files should use UTF-8 as their encoding and the files must not contain a BOM.
//
// Placeholders can now contain order information, e.g. instead of
// 'Page %s of %s' you can (and should) write 'Page %1$s of %2$s', this allows
// translators to re-order the output of data while ensuring it remains correct
//
// You do not need this where single placeholders are used, e.g. 'Message %d' is fine
// equally where a string contains only two placeholders which are used to wrap text
// in a url you again do not need to specify an order e.g., 'Click %sHERE%s' is fine
//
// Some characters you may want to copy&paste:
// ’ » “ ” …
//

$lang = array_merge($lang, array(
	'ACP_SPAMREMOVER_BULK_REMOVE_SPAM'				=> 'Bulk spam removal',
	'ACP_SPAMREMOVER_BULK_REMOVE_SPAM_EXPLAIN'		=> 'This function removes all spam found in posts and private messages based on the search criteria you set.<br><br>Before running this, you should run the details reports for both posts and private messages and selectively flag all content that is not spam. It is also <strong>highly recommended</strong> that you back up your database completely before running this program, as it can make <em>massive</em> changes to your database that cannot otherwise be recovered, deleting not just posts and private messages but in some cases whole topics and users. You can back up your database on the maintenance tab.<br><br>On boards with large amounts of spam, you may encounter errors generally due to the size of the content being deleted and resource limitations imposed by your web hosting. If this happens, you can run this repeatedly to remove any remaining spam. You may have to wait a while until the database resources are reset. This extension uses database transactions that should ensure that your database will not become inconsistent if this happens.<br><br><em>Please note:</em>',
	'ACP_SPAMREMOVER_BULK_REMOVE_SPAM_EXPLAIN_EXTRA'	=> '<ul><li>It is highly recommended that you disable your board before running this. <strong>ACP > Board settings > Disable board > Yes</strong></li><li>If the first post of a topic is marked as spam, its topic and all its replies will be deleted too.</li>
<li>Any spam posters with no approved posts will also have their accounts removed.</li><li>Reenable your board after all spam is removed. <strong>ACP > Board settings > Disable board > No</strong></li></ul>',
	'ACP_SPAMREMOVER_FIND_SPAM'						=> 'Find spam',
	'ACP_SPAMREMOVER_FIND_SPAM_EXPLAIN'				=> 'This page finds post and private message spam by sending selected posts and private messages to the Akismet anti-spam service. If you know when the spam started, look from that date forward as it can take a lot of time (hours in some cases, or even days) to check all posts and private messages on your board. The page may refresh periodically with an update of the status of the spam check. As content is checked, a running summary will show the progress. Posts are checked before private messages.<br><br><strong>Akismet will check approximately 4 posts or private messages per second. Based on this, it would take approximately %s:%s:%s (hours, minutes, seconds) to check all your posts and %s:%s:%s (hours, minutes, seconds) to check all your private messages.</strong> It may take substantially longer depending on the speed and quality of your server’s internet connection and the load on Akismet’s servers. Click on the link in any subsequent dialog box to abort. Any spam found will remain flagged. To reset the spam statistics, use the <strong>reset spam data</strong> function.',
	'ACP_SPAMREMOVER_RESET'							=> 'Reset spam data',
	'ACP_SPAMREMOVER_RESET_EXPLAIN'					=> 'Unmarks any posts and private messages flagged as spam, in case you want to ensure a fresh set of statistics.',
	'ACP_SPAMREMOVER_SELECTIVELY_REMOVE_SPAM'		=> 'Selectively remove spam',
	'ACP_SPAMREMOVER_SELECTIVELY_REMOVE_SPAM_EXPLAIN'	=> 'This page shows spam found and allows you to selectively choose which posts and private messages to remove.',
	'ACP_SPAMREMOVER_SETTINGS'						=> 'Settings',
	'ACP_SPAMREMOVER_SETTINGS_EXPLAIN'				=> 'This extension removes spam posts and spam private messages by calling the <a href="https://akismet.com">Akismet</a> web service, which determines if they are spam or not. The spam post’s topic with all its replies is removed if it’s the first post of a topic. The spammer’s account is also removed if otherwise the spammer would have no approved posts.<br><br>You must first <a href="https://akismet.com/plans/">acquire an Akismet API key</a>, which may require a fee depending on your use of the service. You may qualify for no fee if your use is for a personal board. If you need to pay a fee but are dealing with a one-time spam removal then you might want to pay for only one month.<br><br><em>Note</em>: If you are already using Akismet’s Wordpress plugin on your site, you can use that key below.<br><em>Note</em>: There is also an <a href="https://www.phpbb.com/customise/db/extension/akismet" target="_blank">Akismet Anti-spam Extension</a> available which can be used to check new posts, registrations and forms through the Akismet anti-spam service. If that extension is installed, you can use the same license key for it that you enter here.',
	'ACP_SPAMREMOVER_SETTINGS_EXPLAIN_EXTRA'		=> '<h2>Please follow these procedures to correctly use this extension:</h2><br>
<ol>
<li>First, configure the settings on this page correctly.</li>
<li>Next, use the <a href="%1$s">find spam</a> function to locate spam in posts and private messages.</li>
<li>Next, run the <a href="%2$s">spam summary</a> to get an idea of the scope of your spam issue within the types of content and date ranges you want checked. Note that all spam statistics will show zero unless you first run the <a href="%1$s">find spam</a> function.</li>
<li>Next, use the <a href="%3$s">spam posts details report</a> and the <a href="%4$s">spam private messages details report</a> to unflag any incorrectly identified spam. Make sure to use the pagination feature to review all the spam.</li>
<li>Only after completing all of these steps should you run the <a href="%5$s">bulk spam removal</a> function to permanently remove all the spam. Note you can opt to remove blatant spam only. You are encouraged to fully back up your database first, as there is no recovery feature. You can backup your database on the maintenance tab.</li>
</ol>',
	'ACP_SPAMREMOVER_SPAM_DETAIL_PMS'				=> 'Spam private messages details report',
	'ACP_SPAMREMOVER_SPAM_DETAIL_PMS_EXPLAIN'		=> 'Use this page to review and flag private messages flagged as spam that aren’t spam. You first need to run the <strong>find spam</strong> function for items to appear here.<br><br>If you trust Akismet’s flagging of blatant spam, you might want to filter results to show probable spam only. When you flag a private message as ham, Akismet will be notified to improve the accuracy of its service, and these private messages will not be removed.',
	'ACP_SPAMREMOVER_SPAM_DETAIL_POSTS'				=> 'Spam posts details report',
	'ACP_SPAMREMOVER_SPAM_DETAIL_POSTS_EXPLAIN'		=> 'Use this page to review and flag posts flagged as spam that aren’t spam. You first need to run the <strong>find spam</strong> function for items to appear here.<br><br>If you trust Akismet’s flagging of blatant spam, you might want to filter results to show probable spam only. When you flag a post as ham, Akismet will be notified to improve the accuracy of its service, and these posts will not be removed.',
	'ACP_SPAMREMOVER_SPAM_SUMMARY'					=> 'Spam summary',
	'ACP_SPAMREMOVER_SPAM_SUMMARY_EXPLAIN'			=> '<strong>You need to run the find spam function first to get valid statistics in this report, otherwise the spam counts will show all zeroes.</strong> These statistics may not be based on all your posts and private messages, but should include the date ranges you selected on the find spam page only. <br><br>Akismet defines blatant spam as spam you can safely discard without review. Content marked as probable spam should be manually inspected using the appropriate details report, and flagged if not spam to ensure it is not deleted when you run bulk spam removal.',
	'ACP_SPAMREMOVER_TITLE'							=> 'Spam remover',

	'LOG_ACP_SPAMREMOVER_BULK_REMOVE_RAN'			=> '<strong>Spam bulk remove function was run. A total of %u posts, %u topics, %u users and %u private messages were removed.</strong>',
	'LOG_ACP_SPAMREMOVER_FIND_SPAM_PMS_RAN'			=> '<strong>Find spam private messages function was run</strong>',
	'LOG_ACP_SPAMREMOVER_FIND_SPAM_POSTS_RAN'		=> '<strong>Find spam posts function was run</strong>',
	'LOG_ACP_SPAMREMOVER_FIND_SPAM_SETTINGS_SAVED'	=> '<strong>Find spam settings updated</strong>',
	'LOG_ACP_SPAMREMOVER_RESET'						=> '<strong>All spam data has been removed</strong>',
	'LOG_ACP_SPAMREMOVER_SETTINGS'					=> '<strong>Spam remover settings updated</strong>',
	'LOG_ACP_SPAMREMOVER_SPAM_PMS_DETAILS_RAN'		=> '<strong>Spam posts details function was run</strong>',
	'LOG_ACP_SPAMREMOVER_SPAM_POSTS_DETAILS_RAN'	=> '<strong>Spam posts details function was run</strong>',
));
