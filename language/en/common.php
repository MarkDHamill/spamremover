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
	'ACP_SPAMREMOVER_AKISMET_KEY'				=> 'Akismet API key',
	'ACP_SPAMREMOVER_AKISMET_KEY_EXPLAIN'		=> 'Enter the API key acquired from Askimet in this field. It should be exactly 12 characters. The key must be valid for this extension to find spam. To get a key, use the link in the legend above.',
	'ACP_SPAMREMOVER_AKISMET_KEY_INVALID'		=> 'Your Akismet key is invalid. It must be 12 characters. Use the link below to acquire a key.',
	'ACP_SPAMREMOVER_AKISMET_KEY_INVALID_SCANNER'	=> 'Your Akismet key is invalid. It must be 12 characters. Enter the key on the extension’s settings page.',
	'ACP_SPAMREMOVER_AKISMET_RESULT'			=> 'Spam type',
	'ACP_SPAMREMOVER_ALL'						=> 'All',
	'ACP_SPAMREMOVER_ALL_PMS_CHECKED'			=> 'All private messages meeting your criteria have been checked for spam. A total of %s of %s private messages were identified as spam.',
	'ACP_SPAMREMOVER_ALL_POSTS_CHECKED'			=> 'All posts meeting your criteria have been checked for spam. A total of %s of %s posts checked were flagged as spam.',
	'ACP_SPAMREMOVER_AUTHOR'					=> 'Author',
	'ACP_SPAMREMOVER_ARE_YOU_SURE'				=> 'Are you sure you want to continue? This action cannot be undone!',
	'ACP_SPAMREMOVER_BATCH_SIZE'				=> 'Batch size',
	'ACP_SPAMREMOVER_BATCH_SIZE_EXPLAIN'		=> 'The number of posts or private messages that will be checked by Akismet before the screen refreshes. If you set the number too high, you might experience a gateway timeout and other HTTP, PHP or database resource errors.',
	'ACP_SPAMREMOVER_BLATANT_SPAM'				=> 'Blatant spam',
	'ACP_SPAMREMOVER_BOARD_ORDER'				=> 'Board order (as on the index and view topic page)',
	'ACP_SPAMREMOVER_DATE_FORMAT'				=> 'YYYY-MM-DD',
	'ACP_SPAMREMOVER_FIND_ALL_PMS'				=> 'Search private messages already checked?',
	'ACP_SPAMREMOVER_FIND_ALL_PMS_EXPLAIN'		=> 'If spam private messages were checked in the past, setting this to Yes will force a recheck against Akismet’s database of all private messages that meet your date criteria. If No, private messages already checked will not be checked again. It’s possible, but unlikely, that Akismet’s judgments will change over time.',
	'ACP_SPAMREMOVER_FIND_ALL_POSTS'			=> 'Search posts already checked?',
	'ACP_SPAMREMOVER_FIND_ALL_POSTS_EXPLAIN'	=> 'If spam posts were checked in the past, setting this to Yes will force a recheck against Akismet’s database of all posts that meet your date criteria. If No, posts already checked will not be checked again. It’s possible, but unlikely, that Akismet’s judgments will change over time.',
	'ACP_SPAMREMOVER_GENERAL_SETTINGS'			=> 'General settings',
	'ACP_SPAMREMOVER_GUIDANCE'					=>
'<h2>Recommended approach</h2>
<ul>
<li>In test mode, nothing will be deleted and Akismet will take no action based on the information it is sent. <strong>Stay in test mode until you are ready to remove spam for real.</strong></li>
<li>See if you can determine when the spam started. If you can, set a date range from that date forward.</li>
<li>After setting an appropriately date range, try running a spam summary from that date forward. <strong>Trying to check every post on your board is likely to fail as it could send thousands of posts to Akismet. Unless your board is small, this is likely to cause a timeout error and the process may appear to hang.</strong> Press the Back button on your browser if an error occurs. There may be additional information in phpBB’s error log or your PHP error log.</li>
<li>If there was a timeout error, try reducing the date range until timeout issues no longer occur. This will give you an idea of a date range that will work reliably.</li>
</ul>
<h2>Recommended preparation</h2>
<ul>
<li>Disable your board before actually removing spam and reenable it afterward. It can take a long time to find and remove spam, so users may notice issues and inconsistencies if you don’t.</li>
<li>Next, make a complete backup of your database and be prepared to recover it in case of errors. Manually verify that you have a complete backup before deleting any spam. The .sql file should show SQL and the last character in the file should be a semicolon. Make sure you are familiar with procedures for recovering the database in case of unexpected errors. In the case of large databases, it may have to be recovered outside of phpBB.</li>
<li>You should also backup your board’s files folder. Attachments that are deleted cannot be recovered otherwise.</li>
</ul>
<h2>Removing the spam</h2>
<ul>
<li><strong>Warning!</strong> If the first post of a topic is marked as spam, the entire topic and all its replies will be removed too.</li>
<li><strong>Warning!</strong> If after deleting spam, the user’s post count is zero (0), their account will be removed too. (Administrators and global moderators are exceptions.)</li>
<li><strong>Warning!</strong> If you select to remove all spam, you cannot flag any false positives. Anything Akismet identifies as blatant spam will be removed. Regular spam will be removed too if that setting is enabled. You will not get a detail report of the content removed before it is removed. <strong>This approach is not recommended.</strong></li>
<li>Try running a spam details report first. This will show the spam found for the date range. Note the prevalance of any false positives through multiple tests over the date range of suspected spam. Only posts or private messages Akismet thinks is spam are shown. If a report is empty, no spam was detected for the date range requested.</li>
<li>When ready to remove the spam, disable test mode.</li>
<li>Run the spam details report again. Mark any false positives. These will be reported as ham to Akismet to improve their database. <strong>When you submit the form, the posts and private messages that are unmarked will be removed.</strong></li>
<li>Select additional date ranges if needed and repeat. When the spam summary report shows no spam found for all your date ranges, you should be done.</li>
<li>Afterward, reenable the board and verify it is working correctly and the spam has been removed.</li>
</ul>',
	'ACP_SPAMREMOVER_HAM'						=> 'Ham',
	'ACP_SPAMREMOVER_HAM_PMS_REPORTED'			=> 'Your marked private messages were reported to Akismet as ham',
	'ACP_SPAMREMOVER_HAM_POSTS_REPORTED'		=> 'Your marked posts were reported to Akismet as ham',
	'ACP_SPAMREMOVER_INSTALL_REQUIREMENTS'		=> 'This extension works with phpBB version 3.3 only. To communicate with Akismet, this extension also requires the PHP sockets extension.',
	'ACP_SPAMREMOVER_IS_FIRST_POST'				=> 'First post in topic',
	'ACP_SPAMREMOVER_ITEMS_PER_PAGE'			=> 'Items per page',
	'ACP_SPAMREMOVER_ITEMS_PER_PAGE_EXPLAIN'	=> 'Sets the maximum number of posts or private messages that will appear on a spam posts or spam private messages details report.',
	'ACP_SPAMREMOVER_MARK'						=> 'Report as not spam (ham)',
	'ACP_SPAMREMOVER_MSG_ID'					=> 'Message ID',
	'ACP_SPAMREMOVER_MSG_TEXT'					=> 'Message text (formatting removed)',
	'ACP_SPAMREMOVER_MSG_TIME'					=> 'Message Date/Time',
	'ACP_SPAMREMOVER_NO_ITEMS_FOUND'			=> 'No action taken because your settings indicate to check neither posts nor private messages.',
	'ACP_SPAMREMOVER_NO_SPAM_ERASED'			=> 'No spam was erased.',
	'ACP_SPAMREMOVER_NO_SPAM_REMOVED'			=> 'No spam was removed because you did not enable that option.',
	'ACP_SPAMREMOVER_PARTIAL_PMS_CHECKED'		=> 'A total of %u spam private messages have been found so far. %u private messages have been checked. There are %u private messages still to be checked. You are %s%% done with this step.',
	'ACP_SPAMREMOVER_PARTIAL_POSTS_CHECKED'		=> 'A total of %u spam posts have been found so far. %u posts have been checked. There are %u posts still to be checked. You are %s%% done with this step.',
	'ACP_SPAMREMOVER_PERMANENTLY_REMOVE'		=> 'Are you sure you want to <em>permanently</em> remove all items marked as spam?',
	'ACP_SPAMREMOVER_PERMANENTLY_REMOVE_EXPLAIN'	=> 'You must select Yes and then press submit to remove the spam. There’s no going back! You might want to first backup your database on the maintenance tab.',
	'ACP_SPAMREMOVER_PMS'						=> 'Search private messages for spam?',
	'ACP_SPAMREMOVER_PMS_DATE'					=> 'Private messages date range',
	'ACP_SPAMREMOVER_PMS_DATE_EXPLAIN'			=> 'Leave blank to scan all private messages. To select a date, use the date picker control.',
	'ACP_SPAMREMOVER_PMS_END_DATE'				=> 'Private messages date range ending',
	'ACP_SPAMREMOVER_PMS_END_DATE_EXPLAIN'		=> 'Leave blank to scan through the last private message. To select a date, use the date picker control.',
	'ACP_SPAMREMOVER_PMS_SETTINGS'				=> 'Private message settings',
	'ACP_SPAMREMOVER_PMS_START_DATE'			=> 'Private messages date range starting',
	'ACP_SPAMREMOVER_PMS_START_DATE_EXPLAIN'	=> 'Leave blank to start scanning from the first private message. To select a date, use the date picker control.',
	'ACP_SPAMREMOVER_POST_ID'					=> 'Post ID',
	'ACP_SPAMREMOVER_POST_SETTINGS'				=> 'Post settings',
	'ACP_SPAMREMOVER_POST_TEXT'					=> 'Post content (formatting removed)',
	'ACP_SPAMREMOVER_POST_TIME'					=> 'Post Date/Time',
	'ACP_SPAMREMOVER_POSTER'					=> 'Poster',
	'ACP_SPAMREMOVER_POSTS'						=> 'Search posts for spam?',
	'ACP_SPAMREMOVER_POSTS_END_DATE'			=> 'Posts date range ending',
	'ACP_SPAMREMOVER_POSTS_END_DATE_EXPLAIN'	=> 'Leave blank to scan through the last post. To select a date, use the date picker control.',
	'ACP_SPAMREMOVER_POSTS_START_DATE'			=> 'Posts date range starting',
	'ACP_SPAMREMOVER_POSTS_START_DATE_EXPLAIN'	=> 'Leave blank to start scanning from the first post. To select a date, use the date picker control.',
	'ACP_SPAMREMOVER_REMOVE_ONLY_BLATANT_SPAM'	=> 'Remove only the blatant spam?',
	'ACP_SPAMREMOVER_REMOVE_ONLY_BLATANT_SPAM_EXPLAIN'	=> 'Akismet defines blatant spam as spam that is definitely spam. This setting will remove only the blatant spam, leaving items marked as probable spam untouched.',
	'ACP_SPAMREMOVER_REMOVE_SPAM'				=> 'Remove all spam meeting my criteria (no details will be provided and you won’t be able to flag any false positives.)',
	'ACP_SPAMREMOVER_REMOVE_SPAM_BUTTON'		=> 'Remove spam',
	'ACP_SPAMREMOVER_SET_SEARCH_CRITERIA'		=> 'Set the search criteria',
	'ACP_SPAMREMOVER_SETTING_SAVED'				=> 'Settings have been saved successfully!',
	'ACP_SPAMREMOVER_SHOW'						=> 'Type of spam to show',
	'ACP_SPAMREMOVER_SORT_BY'					=> 'Sort by',
	'ACP_SPAMREMOVER_SORT_ORDER'				=> 'Sort order',
	'ACP_SPAMREMOVER_SPAM'						=> 'Probable spam',
	'ACP_SPAMREMOVER_SPAM_ERASED'				=> 'Spam data has been erased.',
	'ACP_SPAMREMOVER_SPAM_MESSAGES'				=> 'Spam private messages found',
	'ACP_SPAMREMOVER_SPAM_POSTS'				=> 'Spam posts found',
	'ACP_SPAMREMOVER_SPAM_REMOVED'				=> 'Spam was removed. A total of %u posts, %u topics, %u users and %u private messages were removed.',
	'ACP_SPAMREMOVER_SPAM_SUMMARY'				=> 'Spam summary',
	'ACP_SPAMREMOVER_SPAM_TYPE'					=> 'Spam type',
	'ACP_SPAMREMOVER_TEST_MODE'					=> 'Test mode',
	'ACP_SPAMREMOVER_TEST_MODE_EXPLAIN'			=> 'When set to Yes, the flagged spam is not actually removed from your board and Akismet is notified not to change its spam database if you flagged a post or private message as ham. No posts, private messages, topics or users are removed. Make sure to set this to No before bulk removing spam.',
	'ACP_SPAMREMOVER_TEST_MODE_IMPLICATIONS'	=> 'Since you are in test mode, if you press Submit no spam will actually be removed. Select the option on the Settings page to get out of test mode.',
	'ACP_SPAMREMOVER_TOPIC_REPLIES'				=> 'Topic replies',
));
