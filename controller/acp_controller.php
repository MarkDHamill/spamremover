<?php
/**
 *
 * Spam remover. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2021, MarkDHamill, https://www.phpbbservices.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbservices\spamremover\controller;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Spam remover ACP controller.
 */
class acp_controller
{

	protected $config;
	protected $db;
	protected $language;
	protected $log;
	protected $pagination;
	protected $phpbb_container;
	protected $phpbb_root_path;
	protected $phpEx;
	protected $request;
	protected $spam_found_table;
	protected $table_prefix;
	protected $template;
	protected $user;
	protected $u_action;

	private $board_url;
	private $user_agent;

	// These constants are used to identify the spam status of a post or private message
	const SPAM = 0;
	const BLATANT_SPAM = 1;
	const HAM = 2;

	const EXT_VERSION = '1.0.0-dev'; // Change this for every release since version in composer.json cannot be read but Akismet requires a version of the app in its calls.

	const SIMULATION_MODE = false;	// Normally set to false, when true will simulate an Akismet spam response by randomly assigning a spam status
	const TEST_MODE = false;		// Normally set to false, when true this facilitates testing and keeps a lot of "commit"-like action from happening to the database and on Akismet servers

	/**
	 * Constructor.
	 *
	 * @param \phpbb\config\config			$config				Config object
	 * @param \phpbb\language\language		$language			Language object
	 * @param \phpbb\log\log				$log				Log object
	 * @param \phpbb\request\request		$request			Request object
	 * @param \phpbb\template\template		$template			Template object
	 * @param \phpbb\user					$user				User object
	 * @param \phpbb\db\driver\factory 		$db 				The database factory object
	 * @param string						$php_ext 			PHP file suffix
	 * @param string 						$phpbb_root_path 	Relative path to phpBB root
	 * @param ContainerInterface 			$phpbb_container 	Container
	 * @param string						$table_prefix 		Prefix for phpbb's database tables
	 * @param \phpbbservices\spamremover\	$spam_found_table	Extension's spam found table
	 *
	 */
	public function __construct(\phpbb\config\config $config, \phpbb\language\language $language, \phpbb\log\log $log, \phpbb\request\request $request, \phpbb\template\template $template, \phpbb\user $user, \phpbb\db\driver\factory $db, $php_ext, $phpbb_root_path, ContainerInterface $phpbb_container, $table_prefix, $spam_found_table)
	{
		// Connect the services
		$this->phpbb_container 	= $phpbb_container;

		$this->config			= $config;
		$this->db				= $db;
		$this->language			= $language;
		$this->log				= $log;
		$this->pagination 		= $this->phpbb_container->get('pagination');
		$this->phpbb_root_path 	= $phpbb_root_path;
		$this->phpEx			= $php_ext;
		$this->request			= $request;
		$this->spam_found_table	= $spam_found_table;
		$this->table_prefix 	= $table_prefix;
		$this->template			= $template;
		$this->user				= $user;

		// Assign private variables
		$this->board_url 		= $this->board_url = generate_board_url() . '/';
		$this->user_agent 		= 'phpBB/' . $this->config['version'] . ' | spamremover/' . SELF::EXT_VERSION;	// Akismet requires an agent when calling its service.
	}

	/**
	 * Display the options a user can configure for this extension.
	 *
	 * @return void
	 */
	public function display_options($mode)
	{

		// It could take a lot of time to send content to Akismet and get responses as well as purge spam from the database.
		// Let's try to let the script run as long as possible.
		set_time_limit(0);

		include($this->phpbb_root_path . 'includes/functions_privmsgs.' . $this->phpEx);
		include($this->phpbb_root_path . 'includes/functions_user.' . $this->phpEx);

		// Add our common language files
		$this->language->add_lang('common', 'phpbbservices/spamremover');

		// Create a form key for preventing CSRF attacks
		add_form_key('phpbbservices_spamremover_acp');

		// Create an array to collect errors that will be output to the user
		$errors = array();

		// Determine if the Akismet key is valid. We won't allow much to happen unless it is valid.
		$valid_key = $this->akismet_verify_key($this->config['phpbbservices_spamremover_akismet_key'], generate_board_url());

		$items_per_page = (int) $this->config['phpbbservices_spamremover_items_per_page'];

		// Is the form being submitted to us?
		if ($this->request->is_set_post('submit'))
		{

			// Test if the submitted form is valid
			if (!check_form_key('phpbbservices_spamremover_acp'))
			{
				$errors[] = $this->language->lang('FORM_INVALID');
			}

			// If no errors, take action based the form's data
			if (empty($errors))
			{

				if ($mode === 'settings')
				{

					// Save the values in the form fields to the database
					$this->config->set('phpbbservices_spamremover_akismet_key', $this->request->variable('phpbbservices_spamremover_akismet_key', ''));
					$this->config->set('phpbbservices_spamremover_batch_size', (int) $this->request->variable('phpbbservices_spamremover_batch_size', 25));
					$this->config->set('phpbbservices_spamremover_items_per_page', (int) $this->request->variable('phpbbservices_spamremover_items_per_page', 20));

					// Note the settings change action to the admin log
					$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_ACP_SPAMREMOVER_SETTINGS');

					// Option settings have been updated and logged. Confirm this to the user and provide link back to previous page.
					trigger_error($this->language->lang('ACP_SPAMREMOVER_SETTING_SAVED') . adm_back_link($this->u_action));

				}

				if ($mode === 'find')
				{

					// Actual finding of spam posts happens elsewhere since they can also occur as a result of a HTTP GET request.
					// So here we only save the values in the form fields to the database.
					$this->config->set('phpbbservices_spamremover_find_all_pms', (int) $this->request->variable('phpbbservices_spamremover_find_all_pms', 0));
					$this->config->set('phpbbservices_spamremover_find_all_posts', (int) $this->request->variable('phpbbservices_spamremover_find_all_posts', 0));
					$this->config->set('phpbbservices_spamremover_pms', (int) $this->request->variable('phpbbservices_spamremover_pms', 0));
					$this->config->set('phpbbservices_spamremover_pms_end_date', $this->request->variable('phpbbservices_spamremover_pms_end_date', ''));
					$this->config->set('phpbbservices_spamremover_pms_start_date', $this->request->variable('phpbbservices_spamremover_pms_start_date', ''));
					$this->config->set('phpbbservices_spamremover_posts', (int) $this->request->variable('phpbbservices_spamremover_posts', 0));
					$this->config->set('phpbbservices_spamremover_posts_end_date', $this->request->variable('phpbbservices_spamremover_posts_end_date', ''));
					$this->config->set('phpbbservices_spamremover_posts_start_date', $this->request->variable('phpbbservices_spamremover_posts_start_date', ''));

					// Note the settings change action to the admin log
					$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_ACP_SPAMREMOVER_FIND_SPAM_SETTINGS_SAVED');

				}

				if ($mode === 'detail_posts')
				{

					// Report any checked posts as ham and unmark them as spam. Checkboxes are passed as post variable only if they are checked.
					$request_vars = $this->request->get_super_global(\phpbb\request\request_interface::POST);
					foreach ($request_vars as $name => $value)
					{
						if (substr($name, 0, 2) == 'p-')    // Row for post is checked, so it was flagged as ham.
						{
							$post_id = substr($name, 2);	// Field name includes post_id, ex: p-9999 where 9999 is the post_id

							// Report the post as ham to Akismet and remove it from the database
							$sql_ary = array(
								'SELECT' => 'post_id, post_time, poster_ip, poster_id, post_username, post_text, username, 
												user_email, enable_bbcode, 	enable_smilies, enable_magic_url, user_sig_bbcode_uid, 
												user_sig_bbcode_bitfield, topic_title, forum_name, user_dateformat, topic_first_post_id,
												topic_posts_approved, topic_posts_unapproved, topic_posts_softdeleted',
								'FROM' => array(
												POSTS_TABLE  => 'p',
												USERS_TABLE  => 'u',
												TOPICS_TABLE => 't',
												FORUMS_TABLE => 'f'),
								'WHERE' => 'p.poster_id = u.user_id AND p.topic_id = t.topic_id AND p.forum_id = f.forum_id AND p.post_id = ' . (int) $post_id);
							$sql = $this->db->sql_build_query('SELECT', $sql_ary);

							$result = $this->db->sql_query($sql);
							$rowset = $this->db->sql_fetchrowset($result);

							if (count($rowset) === 1)    // Only one post was found, which should always be the case. Tell Akismet the post is ham, not spam.
							{

								// Submit ham
								$post_link = sprintf("%sviewtopic.$this->phpEx?f=%s&amp;t=%s#p%s", $this->board_url, $rowset[0]['forum_id'], $rowset[0]['topic_id'], $rowset[0]['post_id']);
								$post_type = ($rowset[0]['topic_first_post_id'] == $rowset[0]['post_id']) ? 'forum-post' : 'reply';
								$poster = trim($rowset[0]['post_username'] == '') ? $rowset[0]['username'] : $rowset[0]['post_username'];

								// Need BBCode flags to translate BBCode into HTML
								$flags = (($rowset[0]['enable_bbcode']) ? OPTION_FLAG_BBCODE : 0) +
											(($rowset[0]['enable_smilies']) ? OPTION_FLAG_SMILIES : 0) +
											(($rowset[0]['enable_magic_url']) ? OPTION_FLAG_LINKS : 0);
								$post_text = generate_text_for_display($rowset[0]['post_text'], $rowset[0]['bbcode_uid'], $rowset[0]['bbcode_bitfield'], $flags);

								$data = array('blog'                 => $this->board_url,
											  'user_ip'              => $rowset[0]['poster_ip'],
											  'referrer'             => '',
											  'permalink'            => $post_link,
											  'comment_type'         => $post_type,
											  'comment_author'       => $poster,
											  'comment_author_email' => $rowset[0]['user_email'],
											  'comment_author_url'   => '',
											  'comment_content'      => $post_text,
											  'comment_date_gmt'     => date('c', $rowset[0]['post_time']),
											  'blog_lang'            => $this->config['default_lang'],
											  'blog_charset'         => 'UTF-8',
								);

								$this->akismet_submit_ham($this->config['phpbbservices_spamremover_akismet_key'], $data);

								// Remove the row from the phpbb_spam_found table as it was judged not to be spam
								$this->delete_spam_found_row($rowset[0]['post_id'], 1);

							}
							$this->db->sql_freeresult($result);
							unset($data);
						}
					}

					// Note in the log the spam posts details function was run.
					$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_ACP_SPAMREMOVER_SPAM_POSTS_DETAILS_RAN');

					// Option settings have been updated and logged. Confirm this to the user and provide link back to previous page.
					meta_refresh(1, $this->u_action);	// Check private messages next
					trigger_error($this->language->lang('ACP_SPAMREMOVER_HAM_POSTS_REPORTED'));

				}

				if ($mode === 'detail_pms')
				{

					// Report any checked private messages as ham. Checkboxes are passed only if they are checked.
					$request_vars = $this->request->get_super_global(\phpbb\request\request_interface::POST);
					foreach ($request_vars as $name => $value)
					{
						if (substr($name, 0, 2) == 'm-')    // Row is checked, so it was flagged as ham
						{

							$msg_id = substr($name, 2);	// Field name includes msg_id, ex: m-9999 where 9999 is the msg_id

							// Report the private message as ham to Akismet and remove it from the database
							$sql_ary = array(
								'SELECT' => "m.msg_id, message_time, author_ip, m.author_id, message_text, u.username AS 'from', 
										u.user_email, enable_bbcode, enable_smilies, enable_magic_url, u.user_sig_bbcode_uid, 
										u.user_sig_bbcode_bitfield, message_subject, u.user_dateformat",
								'FROM' => array(
										PRIVMSGS_TABLE  => 'm',
										USERS_TABLE  => 'u'),
								'WHERE' => 'm.author_id = u.user_id AND m.msg_id = ' . (int) $msg_id);
							$sql = $this->db->sql_build_query('SELECT', $sql_ary);

							$result = $this->db->sql_query($sql);
							$rowset = $this->db->sql_fetchrowset($result);

							if (count($rowset) === 1)
							{
								// Submit ham
								$message_link = sprintf("%sucp.$this->phpEx?i=pm&amp;mode=view&amp;p=%s", $this->board_url, $rowset[0]['msg_id']);

								// Need BBCode flags to translate BBCode into HTML
								$flags = (($rowset[0]['enable_bbcode']) ? OPTION_FLAG_BBCODE : 0) +
									(($rowset[0]['enable_smilies']) ? OPTION_FLAG_SMILIES : 0) +
									(($rowset[0]['enable_magic_url']) ? OPTION_FLAG_LINKS : 0);
								$message_text = generate_text_for_display($rowset[0]['message_text'], $rowset[0]['bbcode_uid'], $rowset[0]['bbcode_bitfield'], $flags);

								$data = array('blog'                 => $this->board_url,
											  'user_ip'              => $rowset[0]['author_ip'],
											  'referrer'             => '',
											  'permalink'            => $message_link,
											  'comment_type'         => 'message',
											  'comment_author'       => $rowset[0]['from'],
											  'comment_author_email' => $rowset[0]['user_email'],
											  'comment_author_url'   => '',
											  'comment_content'      => $message_text,
											  'comment_date_gmt'     => date('c', $rowset[0]['message_time']),
											  'blog_lang'            => $this->config['default_lang'],
											  'blog_charset'         => 'UTF-8',
								);

								$this->akismet_submit_ham($this->config['phpbbservices_spamremover_akismet_key'], $data);

								// Remove the row from the phpbb_spam_found table as it was judged not to be spam
								$this->delete_spam_found_row($rowset[0]['msg_id'], 0);
							}
							$this->db->sql_freeresult($result);
							unset($data);
						}
					}

					// Note in the log the spam private messages details function was run.
					$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_ACP_SPAMREMOVER_SPAM_PMS_DETAILS_RAN');

					// Option settings have been updated and logged. Confirm this to the user and provide link back to previous page.
					meta_refresh(1, $this->u_action);	// Check private messages next
					trigger_error($this->language->lang('ACP_SPAMREMOVER_HAM_PMS_REPORTED'));

				}

				if ($mode === 'bulk_remove')
				{

					// May need logic to do this in batches similar to the find function. On boards with lots of spam there
					// could be hundreds of thousands of posts and PMs to delete. OTOH, by using transactions if we abruptly
					// run out of resources, the database should be consistent.

					// Save the values in the form fields
					$this->config->set('phpbbservices_spamremover_blatant_only', $this->request->variable('phpbbservices_spamremover_blatant_only', 0));
					$blatant_only = (bool) $this->request->variable('phpbbservices_spamremover_blatant_only', 0);

					$proceed = (bool) $this->request->variable('phpbbservices_spamremover_proceed', 0);
					if (!$proceed)
					{
						// Radio button not checked to confirm the action
						trigger_error($this->language->lang('ACP_SPAMREMOVER_NO_SPAM_REMOVED') . adm_back_link($this->u_action));
					}

					// Onward! Remove the spam!
					$topics_removed = 0;
					$users_removed = 0;

					// Delete the spam posts
					$blatant_only_sql = ($blatant_only) ? ' AND is_blatant_spam = 1' : '';
					$sql_ary = array(
						'SELECT' 	=> 'post_id',
						'FROM' 		=> array(
							POSTS_TABLE  		=> 'p',
							$this->spam_found_table	=> 's'),
						'WHERE'		=> 'is_post = 1 AND p.post_id = s.post_msg_id' . $blatant_only_sql);
					$sql = $this->db->sql_build_query('SELECT', $sql_ary);
					$result = $this->db->sql_query($sql);
					$rowset = $this->db->sql_fetchrowset($result);
					$posts_to_remove = count($rowset);

					foreach ($rowset as $row)
					{

						// Use a transaction. If we abruptly run out of resources, we want the database to be consistent.
						$this->db->sql_transaction('begin');

						// Delete the post
						$actions = $this->delete_post($row['post_id']);

						if (!is_array($actions) && $actions === false)
						{
							$this->db->sql_transaction('rollback');
							continue;	// Skip, post has been removed already, probably because its topic was previously deleted
						}

						// Delete the row in the phpbb_spam_found table;
						$this->delete_spam_found_row($row['post_id'], 1);

						if ($actions['topic_removed'])
						{
							$topics_removed++;
						}

						if ($actions['user_removed'])
						{
							$users_removed++;
						}

						$this->db->sql_transaction('commit');

					}
					$this->db->sql_freeresult($result);

					// Delete the spam private messages
					$sql_ary = array(
						'SELECT' 	=> 'msg_id',
						'FROM' 		=> array(
							PRIVMSGS_TABLE  	=> 'm',
							$this->spam_found_table	=> 's'),
						'WHERE'		=> 'is_post = 0 AND m.msg_id = s.post_msg_id' . $blatant_only_sql);
					$sql = $this->db->sql_build_query('SELECT', $sql_ary);
					$result = $this->db->sql_query($sql);
					$rowset = $this->db->sql_fetchrowset($result);
					$pms_to_remove = count($rowset);
					foreach ($rowset as $row)
					{
						// Use a transaction. If we abruptly run out of resources, we want the database to be consistent.
						$this->db->sql_transaction('begin');

						// Delete the private message
						$this->delete_private_message($row['msg_id']);

						// Delete the row in the phpbb_spam_found table;
						$this->delete_spam_found_row($row['msg_id'], 0);

						$this->db->sql_transaction('commit');
					}
					$this->db->sql_freeresult($result);

					// Note in the log the bulk remove function was run.
					$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_ACP_SPAMREMOVER_BULK_REMOVE_RAN', false, $posts_to_remove, $topics_removed, $users_removed, $pms_to_remove);

					// All done!
					trigger_error(sprintf($this->language->lang('ACP_SPAMREMOVER_SPAM_REMOVED'), $posts_to_remove, $topics_removed, $users_removed, $pms_to_remove) . adm_back_link($this->u_action));

				}

				if ($mode === 'reset')
				{
					if ((bool) $this->request->variable('phpbbservices_spamremover_reset', 0))
					{
						// Empty the phpbb_spam_found table
						$sql = 'DELETE FROM ' . $this->spam_found_table;
						$this->db->sql_query($sql);

						// Note in the log the reset occurred.
						$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_ACP_SPAMREMOVER_RESET');

						trigger_error($this->language->lang('ACP_SPAMREMOVER_SPAM_ERASED'). adm_back_link($this->u_action));
					}
					else
					{
						trigger_error($this->language->lang('ACP_SPAMREMOVER_NO_SPAM_ERASED'). adm_back_link($this->u_action));
					}

				}

			}	// No errors

		}	// Submit

		// Always check and flag if the key is missing or incorrect
		if (!$valid_key || strlen($this->config['phpbbservices_spamremover_akismet_key']) !== 12)
		{
			$errors[] = ($mode === 'settings') ? $this->language->lang('ACP_SPAMREMOVER_AKISMET_KEY_INVALID') : $this->language->lang('ACP_SPAMREMOVER_AKISMET_KEY_INVALID_SCANNER');
		}

		$s_errors = !empty($errors);

		$this->template->assign_vars(array(
			'ERROR_MSG'		=> $s_errors ? implode('<br>', $errors) : '',
			'S_ERROR'		=> $s_errors,
			'U_ACTION'		=> $this->u_action,
		));

		// Set output variables for display in the template
		if ($mode === 'settings')
		{
			$this->template->assign_vars(array(
				'L_ACP_SPAMREMOVER_SETTINGS_EXPLAIN_EXTRA'		=>
					$this->language->lang('ACP_SPAMREMOVER_SETTINGS_EXPLAIN_EXTRA',
						append_sid("index.$this->phpEx?i=-phpbbservices-spamremover-acp-main_module&amp;mode=find"),
						append_sid("index.$this->phpEx?i=-phpbbservices-spamremover-acp-main_module&amp;mode=summary"),
						append_sid("index.$this->phpEx?i=-phpbbservices-spamremover-acp-main_module&amp;mode=detail_posts"),
						append_sid("index.$this->phpEx?i=-phpbbservices-spamremover-acp-main_module&amp;mode=detail_pms"),
						append_sid("index.$this->phpEx?i=-phpbbservices-spamremover-acp-main_module&amp;mode=bulk_remove")),
				'PHPBBSERVICES_SPAMREMOVER_AKISMET_KEY'		=> $this->config['phpbbservices_spamremover_akismet_key'],
				'PHPBBSERVICES_SPAMREMOVER_BATCH_SIZE'		=> $this->config['phpbbservices_spamremover_batch_size'],
				'PHPBBSERVICES_SPAMREMOVER_ITEMS_PER_PAGE'	=> $this->config['phpbbservices_spamremover_items_per_page'],
				'S_INCLUDE_SR_CSS' 							=> true,
				'S_SETTINGS'								=> true,
			));
		}

		if ($mode === 'find')
		{
			// Initially a page displays, but it's also possible that this page will be called by trigger_error. If the
			// latter, rather than showing the form, we need to find and process posts or private messages instead.
			// The key is to look for the find_type parameter in the URL. If it exists, posts or private messages need to
			// be processed, rather than rendering a find interface.

			$find_type = $this->request->variable('find_type', 'none');
			if ($find_type === 'none' && !$this->request->is_set_post('submit'))
			{
				$num_posts = $this->config['num_posts'];

				// Get private message count
				$sql = 'SELECT count(*) as pms_count
						FROM ' . PRIVMSGS_TABLE . ' m';
				$result = $this->db->sql_query($sql);
				$num_pms = $this->db->sql_fetchfield('pms_count');
				$this->db->sql_freeresult($result);

				// Estimate the time to check all posts. Based on testing, Akismet can test about 4 posts per second.
				$est_post_seconds = round($num_posts/4);
				$post_hrs = floor($est_post_seconds / 3600);
				$est_post_seconds = ($post_hrs != 0) ? $est_post_seconds % ($post_hrs * 3600) : $est_post_seconds;
				$post_min = floor($est_post_seconds / 60);
				$post_sec = ($post_min != 0) ? $est_post_seconds % ($post_min * 60) : $est_post_seconds;

				// Estimate the time to check all private messages. Based on testing, Akismet can test about 4 private messages per second.
				$est_pms_seconds = round($num_pms/4);
				$pms_hrs = floor($est_pms_seconds / 3600);
				$est_pms_seconds = ($pms_hrs != 0) ? $est_pms_seconds % ($pms_hrs * 3600) : $est_pms_seconds;
				$pms_min = floor($est_pms_seconds / 60);
				$pms_sec = ($pms_min != 0) ? $est_pms_seconds % ($pms_min * 60) : $est_pms_seconds;

				// Populate the form fields with current values in the database
				$this->template->assign_vars(array(
					'L_ACP_SPAMREMOVER_FIND_SPAM_EXPLAIN'				=> $this->language->lang('ACP_SPAMREMOVER_FIND_SPAM_EXPLAIN',
						str_pad($post_hrs,2,'0', STR_PAD_LEFT),
						str_pad($post_min,2,'0', STR_PAD_LEFT),
						str_pad($post_sec,2,'0', STR_PAD_LEFT),
						str_pad($pms_hrs,2,'0', STR_PAD_LEFT),
						str_pad($pms_min,2,'0', STR_PAD_LEFT),
						str_pad($pms_sec,2,'0', STR_PAD_LEFT)),
					'PHPBBSERVICES_SPAMREMOVER_FIND_ALL_PMS'			=> (bool) $this->config['phpbbservices_spamremover_find_all_pms'],
					'PHPBBSERVICES_SPAMREMOVER_FIND_ALL_POSTS'			=> (bool) $this->config['phpbbservices_spamremover_find_all_posts'],
					'PHPBBSERVICES_SPAMREMOVER_PMS'						=> (bool) $this->config['phpbbservices_spamremover_pms'],
					'PHPBBSERVICES_SPAMREMOVER_PMS_END_DATE'			=> $this->config['phpbbservices_spamremover_pms_end_date'],
					'PHPBBSERVICES_SPAMREMOVER_PMS_START_DATE'			=> $this->config['phpbbservices_spamremover_pms_start_date'],
					'PHPBBSERVICES_SPAMREMOVER_POSTS'					=> (bool) $this->config['phpbbservices_spamremover_posts'],
					'PHPBBSERVICES_SPAMREMOVER_POSTS_END_DATE'			=> $this->config['phpbbservices_spamremover_posts_end_date'],
					'PHPBBSERVICES_SPAMREMOVER_POSTS_START_DATE'		=> $this->config['phpbbservices_spamremover_posts_start_date'],
					'S_FIND'											=> true,
					'S_INCLUDE_SR_CSS' 									=> true,
				));
			}

			// The following redirect logic looks unweildy, but trust me it's the only thing I could get to work properly.
			if ($find_type === 'none' && $this->request->is_set_post('submit') && !((bool) $this->config['phpbbservices_spamremover_posts']))
			{
				// Don't find posts on initial form submittal
				if (!((bool) $this->config['phpbbservices_spamremover_pms']))
				{
					// Also don't find private messages, so there's nothing to do
					trigger_error($this->language->lang('ACP_SPAMREMOVER_NO_ITEMS_FOUND') . adm_back_link($this->u_action));
				}
				else
				{
					// Find private messages (there were no posts to find)
					$this->config->set('phpbbservices_spamremover_posts_found', 1); // This value is a flag indicating posts were either found or didn't need to be found
					$this->find_spam_pms();	// This function redirects
				}
			}
			if ($find_type === 'none' && $this->request->is_set_post('submit') && (bool) $this->config['phpbbservices_spamremover_posts'] && !((bool) $this->config['phpbbservices_spamremover_pms']))
			{
				// Don't find private messages on initial form submit, but do find posts, so find posts only
				$this->find_spam_posts();	// This function redirects
			}
			if ($find_type === 'posts')
			{
				// This is executed through a trigger_error call only (GET request), basically after the first batch of posts have been checked but there are still more to test.
				$this->find_spam_posts();	// This function redirects
			}
			if ($find_type === 'pms')
			{
				// This is executed through by a trigger_error call only (GET request), basically after the first batch of private messages have been checked but there are still more to test.
				$this->find_spam_pms();	// This function redirects
			}

			// Initial form submittal logic occurs here only if both posts and private messages should be scanned for spam. Posts are checked before private
			// messages so start by finding spam posts.
			if ($this->request->is_set_post('submit'))
			{
				$this->find_spam_posts();
			}

		}

		if ($mode === 'summary')
		{

			$this->template->assign_vars(array(
				'S_INCLUDE_SR_CSS' 						=> true,
				'S_SUMMARY'								=> true,
			));

			// This array facilitates showing counts for spam types that may not be returned by the query
			$posts_row = array(SELF::HAM => 0, SELF::SPAM => 0, SELF::BLATANT_SPAM => 0);

			// Determine the date range, if any, of the posts to check for spam
			$start_date_str = trim($this->config['phpbbservices_spamremover_posts_start_date']);
			$end_date_str = trim($this->config['phpbbservices_spamremover_posts_end_date']);

			// Create the SQL to find the correct posts to check
			if ($start_date_str == '' && $end_date_str == '')
			{
				$date_range = '';
			}
			else if ($start_date_str !== '' && $end_date_str == '')
			{
				$date_range = ' AND post_time >= ' . strtotime($start_date_str);
			}
			else if ($start_date_str == '' && $end_date_str !== '')
			{
				$date_range = ' AND post_time <= ' . strtotime($end_date_str);
			}
			else
			{
				$date_range = ' AND post_time >= ' . strtotime($start_date_str) . ' AND post_time <= ' . strtotime($end_date_str);
			}

			// Determine total number of posts that were checked for spam
			$sql_ary = array(
				'SELECT' 	=> 'count(*) AS row_count',
				'FROM'		=> 	array(
					POSTS_TABLE => 'p',
					USERS_TABLE => 'u',
					TOPICS_TABLE => 't',
					FORUMS_TABLE => 'f'),
				'WHERE'		=> "p.poster_id = u.user_id AND p.topic_id = t.topic_id AND p.forum_id = f.forum_id $date_range",
				'ORDER_BY'	=> 'post_id');
			$sql = $this->db->sql_build_query('SELECT', $sql_ary);
			$result = $this->db->sql_query($sql);
			$row_count = $this->db->sql_fetchfield('row_count');
			$this->db->sql_freeresult($result);

			// Get a result set of posts that have been flagged as spam
			$sql_ary = array(
				'SELECT' 	=>'is_blatant_spam, count(*) as type_count',
				'FROM' 		=> array(
					$this->spam_found_table	=> 's'),
				'WHERE'		=> "is_post = 1 $date_range",
				'GROUP_BY' 	=> 'is_blatant_spam',
				'ORDER_BY'	=> 'is_blatant_spam');
			$sql = $this->db->sql_build_query('SELECT', $sql_ary);

			$result = $this->db->sql_query($sql);
			$rowset = $this->db->sql_fetchrowset($result);

			foreach ($rowset as $row)
			{
				$posts_row[$row['is_blatant_spam']] = (int) $row['type_count'];
			}

			// Calculate the ham
			$post_ham = $row_count - ($posts_row[SELF::SPAM] + $posts_row[SELF::BLATANT_SPAM]);

			$this->db->sql_freeresult($result);

			// Report post spam
			$this->template->assign_vars(array(
					'ACP_SPAMREMOVER_POSTS_HAM'				=> $post_ham,
					'ACP_SPAMREMOVER_POSTS_SPAM'			=> $posts_row[SELF::SPAM],
					'ACP_SPAMREMOVER_POSTS_BLATANT_SPAM'	=> $posts_row[SELF::BLATANT_SPAM],
				)
			);

			// This array facilitates showing counts for spam types that may not be returned by the query
			$pms_row = array(SELF::HAM => 0, SELF::SPAM => 0, SELF::BLATANT_SPAM => 0);

			// Determine total number of private messages that need to be checked
			$sql_ary = array(
				'SELECT' 	=> 'count(*) AS row_count',
				'FROM'		=> 	array(
					PRIVMSGS_TABLE => 'pm',
					USERS_TABLE => 'u'),
				'WHERE'		=> "pm.author_id = u.user_id $date_range",
				'ORDER_BY'	=> 'msg_id');
			$sql = $this->db->sql_build_query('SELECT', $sql_ary);
			$result = $this->db->sql_query($sql);
			$row_count = $this->db->sql_fetchfield('row_count');
			$this->db->sql_freeresult($result);

			// Get a result set of private messages that have been flagged as spam
			$sql_ary = array(
				'SELECT' 	=>'is_blatant_spam, count(*) as type_count',
				'FROM' 		=> array(
					$this->spam_found_table	=> 's'),
				'WHERE'		=> "is_post = 0 $date_range",
				'GROUP_BY' 	=> 'is_blatant_spam',
				'ORDER_BY'	=> 'is_blatant_spam');
			$sql = $this->db->sql_build_query('SELECT', $sql_ary);

			$result = $this->db->sql_query($sql);
			$rowset = $this->db->sql_fetchrowset($result);

			foreach ($rowset as $row)
			{
				$pms_row[$row['is_blatant_spam']] = (int) $row['type_count'];
			}

			// Calculate the ham
			$pms_ham = $row_count - ($pms_row[SELF::SPAM] + $pms_row[SELF::BLATANT_SPAM]);

			$this->db->sql_freeresult($result);

			// Report private message spam
			$this->template->assign_vars(array(
					'ACP_SPAMREMOVER_MSGS_HAM'				=> $pms_ham,
					'ACP_SPAMREMOVER_MSGS_SPAM'				=> $pms_row[SELF::SPAM],
					'ACP_SPAMREMOVER_MSGS_BLATANT_SPAM'		=> $pms_row[SELF::BLATANT_SPAM]
				)
			);

		}

		if ($mode === 'detail_posts')	// Show the posts Akismet found as spam
		{

			// Get some controls on how to layout the page
			$start = $this->request->variable('start', 0);
			$spamtype = $this->request->variable('spamtype', 'a');	// All spam is the default
			$sortby = $this->request->variable('sortby', 'p');	// Sort by post date/time is the default
			$sortorder = $this->request->variable('sortorder', 'a');
			$sortorder_sql = ($sortorder === 'a') ? 'ASC' : 'DESC';	// Sort ascending is the default

			switch ($spamtype)
			{
				case 'a':
				default:
					$subscribe_sql = 'is_post = 1';
				break;

				case 'p':
					$subscribe_sql = 'is_post = 1 AND is_blatant_spam = ' . SELF::SPAM;
				break;

				case 'b':
					$subscribe_sql = 'is_post = 1 AND is_blatant_spam = ' . SELF::BLATANT_SPAM;
				break;
			}

			switch($sortby)
			{
				case 'p':
				default:
					$sortby_sql = 'post_time';
				break;

				case 't':
					$sortby_sql = 'topic_title';
				break;

				case 'b':
					$sortby_sql = 'left_id, right_id, topic_last_post_time DESC, post_time';
				break;
			}

			$url = $this->request->server('REQUEST_URI','');

			// Page filtering and sorting controls
			$this->template->assign_vars(array(
				'ACP_SPAMREMOVER_ALL_SELECTED'			=> ($spamtype == 'a') ? ' selected="selected"' : '',
				'ACP_SPAMREMOVER_SPAM_SELECTED'			=> ($spamtype == 'p') ? ' selected="selected"' : '',
				'ACP_SPAMREMOVER_BLATANT_SPAM_SELECTED'	=> ($spamtype == 'b') ? ' selected="selected"' : '',

				'ACP_SPAMREMOVER_TOPIC_SELECTED'		=> ($sortby == 't') ? ' selected="selected"' : '',
				'ACP_SPAMREMOVER_POST_TIME_SELECTED'	=> ($sortby == 'p') ? ' selected="selected"' : '',
				'ACP_SPAMREMOVER_BOARD_SELECTED'		=> ($sortby == 'b') ? ' selected="selected"' : '',

				'ACP_SPAMREMOVER_ASCENDING_SELECTED'	=> ($sortorder == 'a') ? ' selected="selected"' : '',
				'ACP_SPAMREMOVER_DESCENDING_SELECTED'	=> ($sortorder == 'd') ? ' selected="selected"' : '',

				'S_DETAIL_POSTS'						=> true,

				'U_ACTION'								=> $url, // This will force the form action to include the full URL, including the start parameter
				)
			);

			$spam_types = array(SELF::SPAM => $this->language->lang('ACP_SPAMREMOVER_SPAM'),
								SELF::BLATANT_SPAM => $this->language->lang('ACP_SPAMREMOVER_BLATANT_SPAM'));

			// Get the total rows for pagination purposes
			$sql_ary = array(
				'SELECT'	=> 'COUNT(*) AS spam_count',
				'FROM'		=> array(
					$this->spam_found_table	=> 's',
				),
				'WHERE'		=> $subscribe_sql,
			);

			$sql = $this->db->sql_build_query('SELECT', $sql_ary);
			$result = $this->db->sql_query($sql);

			$spam_count = $this->db->sql_fetchfield('spam_count');
			$this->db->sql_freeresult($result);

			// Set $start for pagination purposes based on current spam post count
			$max_start = floor(max($spam_count - 1, 0) / $items_per_page) * $items_per_page;
			if ($start > $max_start)
			{
				$start = $max_start;
			}

			// Create pagination controls
			$pagination_url = append_sid("index.$this->phpEx?i=-phpbbservices-spamremover-acp-main_module&amp;mode=detail_posts&amp;spamtype=$spamtype&amp;sortby=$sortby&amp;sortorder=$sortorder");
			$this->pagination->generate_template_pagination($pagination_url, 'pagination', 'start', $spam_count, $items_per_page, $start);

			$sql_ary = array(
				'SELECT'	=> 'post_id, post_time, post_username, post_text, p.topic_id, u.user_id, is_blatant_spam, username, forum_name, topic_title,
								enable_bbcode, 	enable_smilies, enable_magic_url, topic_first_post_id,
								topic_posts_approved, topic_posts_unapproved, topic_posts_softdeleted, topic_first_post_id, left_id, right_id, topic_last_post_time',
				'FROM'		=> array(
								POSTS_TABLE		=> 'p',
								USERS_TABLE 	=> 'u',
								TOPICS_TABLE 	=> 't',
								FORUMS_TABLE	=> 'f',
								$this->spam_found_table	=> 's'),
				'WHERE'		=> "p.poster_id = u.user_id AND p.topic_id = t.topic_id AND p.forum_id = f.forum_id AND s.is_post = 1 AND s.post_msg_id = p.post_id AND $subscribe_sql",
				'ORDER_BY'	=> "$sortby_sql $sortorder_sql",
			);
			$sql = $this->db->sql_build_query('SELECT', $sql_ary);
			$result = $this->db->sql_query_limit($sql, $items_per_page, $start);
			$rowset = $this->db->sql_fetchrowset($result);

			foreach ($rowset as $row)
			{
				// Need BBCode flags to translate BBCode into HTML
				$flags = (($row['enable_bbcode']) ? OPTION_FLAG_BBCODE : 0) +
					(($row['enable_smilies']) ? OPTION_FLAG_SMILIES : 0) +
					(($row['enable_magic_url']) ? OPTION_FLAG_LINKS : 0);
				$forum_name = generate_text_for_display($row['forum_name'], $row['bbcode_uid'], $row['bbcode_bitfield'], $flags);
				$topic_title = generate_text_for_display($row['topic_title'], $row['bbcode_uid'], $row['bbcode_bitfield'], $flags);
				$post_text = strip_tags(generate_text_for_display($row['post_text'], $row['bbcode_uid'], $row['bbcode_bitfield'], $flags));

				$this->template->assign_block_vars('posts', array(
					'AKISMET'					=> $spam_types[$row['is_blatant_spam']],
					'FORUM_NAME'				=> $forum_name,
					'IS_FIRST_POST'				=> ($row['post_id'] == $row['topic_first_post_id']) ? $this->language->lang('YES') : $this->language->lang('NO'),
					'MARK'						=> $row['post_id'],
					'POSTER'					=> (trim($row['post_username']) == '') ? sprintf('<a href="%s" target="_blank">%s</a>', append_sid("../memberlist.$this->phpEx?mode=viewprofile&amp;u=" . $row['user_id']), $row['username']) : $row['post_username'],
					'POST_ID'					=> sprintf('<a href="%s" target="_blank">%s</a>', append_sid("../viewtopic.$this->phpEx?p=" . $row['post_id'] . '#p' . $row['post_id']), $row['post_id']),
					'POST_TEXT'					=> $post_text,
					'POST_TIME'					=> date($this->config['default_dateformat'], $row['post_time']),
					'TOPIC_REPLIES'				=> $row['topic_posts_approved'] - $row['topic_posts_unapproved'] - 1,
					'TOPIC_TITLE'				=> sprintf('<a href="%s" target="_blank">%s</a>', append_sid("../viewtopic.$this->phpEx?t=" . $row['topic_id']),$topic_title),
				));
			}
			$this->db->sql_freeresult($result);

		}

		if ($mode === 'detail_pms')	// Show the private messages Akismet found as spam
		{

			// Get some controls on how to layout the page
			$start = $this->request->variable('start', 0);
			$spamtype = $this->request->variable('spamtype', 'a');	// All spam is the default
			$sortby = $this->request->variable('sortby', 'm');
			$sortby_sql = ($sortby === 'm') ? 'message_time' : 'username';	// Sort by private message date/time is the default
			$sortorder = $this->request->variable('sortorder', 'a');
			$sortorder_sql = ($sortorder === 'a') ? 'ASC' : 'DESC';	// Sort ascending is the default

			switch ($spamtype)
			{
				case 'a':
				default:
					$subscribe_sql = 'is_post = 0';
				break;

				case 'p':
					$subscribe_sql = 'is_post = 0 AND is_blatant_spam = ' . SELF::SPAM;
				break;

				case 'b':
					$subscribe_sql = 'is_post = 0 AND is_blatant_spam = ' . SELF::BLATANT_SPAM;
				break;
			}

			$url = $this->request->server('REQUEST_URI','');

			// Page filtering and sorting controls
			$this->template->assign_vars(array(
					'ACP_SPAMREMOVER_ALL_SELECTED'			=> ($spamtype == 'a') ? ' selected="selected"' : '',
					'ACP_SPAMREMOVER_SPAM_SELECTED'			=> ($spamtype == 'p') ? ' selected="selected"' : '',
					'ACP_SPAMREMOVER_BLATANT_SPAM_SELECTED'	=> ($spamtype == 'b') ? ' selected="selected"' : '',

					'ACP_SPAMREMOVER_AUTHOR_SELECTED'		=> ($sortby == 'a') ? ' selected="selected"' : '',
					'ACP_SPAMREMOVER_MESSAGE_TIME_SELECTED'	=> ($sortby == 'm') ? ' selected="selected"' : '',

					'ACP_SPAMREMOVER_ASCENDING_SELECTED'	=> ($sortorder == 'a') ? ' selected="selected"' : '',
					'ACP_SPAMREMOVER_DESCENDING_SELECTED'	=> ($sortorder == 'd') ? ' selected="selected"' : '',

					'S_DETAIL_PMS'							=> true,

					'U_ACTION'								=> $url, // This will force the form action to include the full URL, including the start parameter
				)
			);

			$spam_types = array(SELF::SPAM => $this->language->lang('ACP_SPAMREMOVER_SPAM'),
								SELF::BLATANT_SPAM => $this->language->lang('ACP_SPAMREMOVER_BLATANT_SPAM'));

			// Get the total rows for pagination purposes
			$sql_ary = array(
				'SELECT'	=> 'COUNT(*) AS spam_count',
				'FROM'		=> array(
					$this->spam_found_table	=> 's',
				),
				'WHERE'		=> $subscribe_sql,
			);
			$sql = $this->db->sql_build_query('SELECT', $sql_ary);
			$result = $this->db->sql_query($sql);

			$spam_count = $this->db->sql_fetchfield('spam_count');
			$this->db->sql_freeresult($result);

			// Set $start for pagination purposes based on current private message spam count.
			$max_start = floor(max($spam_count - 1, 0) / $items_per_page) * $items_per_page;
			if ($start > $max_start)
			{
				$start = $max_start;
			}

			// Create pagination controls
			$pagination_url = append_sid("index.$this->phpEx?i=-phpbbservices-spamremover-acp-main_module&amp;mode=detail_pms&amp;spamtype=$spamtype&amp;sortby=$sortby&amp;sortorder=$sortorder");
			$this->pagination->generate_template_pagination($pagination_url, 'pagination', 'start', $spam_count, $items_per_page, $start);

			$sql_ary = array(
				'SELECT'	=> 'm.msg_id, m.author_id, u.username as author, message_subject, message_text, message_time, is_blatant_spam, enable_bbcode, enable_smilies, enable_magic_url, bbcode_uid, bbcode_bitfield',
				'FROM'		=> array(
					PRIVMSGS_TABLE		=> 'm',
					USERS_TABLE			=> 'u',
					$this->spam_found_table	=> 's',
				),
				'WHERE'		=> "u.user_id = m.author_id AND s.is_post = 0 AND s.post_msg_id = m.msg_id AND $subscribe_sql",
				'ORDER_BY'	=> "$sortby_sql $sortorder_sql"
			);
			$sql = $this->db->sql_build_query('SELECT', $sql_ary);
			$result = $this->db->sql_query_limit($sql, $items_per_page, $start);
			$rowset = $this->db->sql_fetchrowset($result);

			foreach ($rowset as $row)
			{
				// Need BBCode flags to translate BBCode into HTML
				$flags = (($row['enable_bbcode']) ? OPTION_FLAG_BBCODE : 0) +
					(($row['enable_smilies']) ? OPTION_FLAG_SMILIES : 0) +
					(($row['enable_magic_url']) ? OPTION_FLAG_LINKS : 0);
				$message_subject = generate_text_for_display($row['message_subject'], $row['bbcode_uid'], $row['bbcode_bitfield'], $flags);
				$message_text = strip_tags(generate_text_for_display($row['message_text'], $row['bbcode_uid'], $row['bbcode_bitfield'], $flags));

				$this->template->assign_block_vars('pms', array(
					'AKISMET'					=> $spam_types[$row['is_blatant_spam']],
					'FROM'						=> sprintf('<a href="%s" target="_blank">%s</a>', append_sid("../memberlist.$this->phpEx?mode=viewprofile&amp;u=" . $row['author_id']), $row['author']),
					'MARK'						=> $row['msg_id'],
					'MESSAGE_SUBJECT'			=> $message_subject,
					'MESSAGE_TEXT'				=> $message_text,
					'MESSAGE_TIME'				=> date($this->config['default_dateformat'], $row['message_time']),
					'MSG_ID'					=> $row['msg_id'],
				));
			}
			$this->db->sql_freeresult($result);

		}

		if ($mode === 'bulk_remove')
		{
			$this->template->assign_vars(array(
				'S_INCLUDE_SR_CSS' 						=> true,
				'S_REMOVE_BULK'							=> true,
			));
		}

		if ($mode === 'reset')
		{
			$this->template->assign_vars(array(
				'S_INCLUDE_SR_CSS' 						=> true,
				'S_RESET'								=> true,
			));
		}

	}

	/**
	 * Set custom form action.
	 *
	 * @param string	$u_action	Custom form action
	 * @return void
	 */
	public function set_page_url($u_action)
	{
		$this->u_action = $u_action;
	}

	private function akismet_verify_key($key, $blog)
	{

		// Based on code found here: https://akismet.com/development/api/#verify-key
		//
		// $key = Askismet service authorizaton key associated with board (12 characters)
		// $blog = URL of board

		$blog = urlencode($blog);
		$request = 'key=' . $key . '&blog='. $blog;
		$host = $http_host = 'rest.akismet.com';
		$path = '/1.1/verify-key';
		$port = 443;
		$content_length = strlen($request);
		$http_request  = "POST $path HTTP/1.0\r\n";
		$http_request .= "Host: $host\r\n";
		$http_request .= "Content-Type: application/x-www-form-urlencoded\r\n";
		$http_request .= "Content-Length: {$content_length}\r\n";
		$http_request .= "User-Agent: {SELF::EXT_VERSION}\r\n";
		$http_request .= "\r\n";
		$http_request .= $request;
		$response = '';

		if (false != ($fs = @fsockopen('ssl://' . $http_host, $port,$errno,$errstr,10)))
		{
			fwrite($fs, $http_request);
			while (!feof($fs))
			{
				$response .= fgets( $fs, 1160 ); // One TCP-IP packet
			}
			fclose($fs);
			$response = explode( "\r\n\r\n", $response, 2 );
		}

		return 'valid' == $response[1];

	}

	private function akismet_submit_ham($key, $data)
	{

		// Based on code found here: https://akismet.com/development/api/#submit-ham
		//
		// $key = Askismet service authorization key associated with board (12 characters)
		// $data = content of post or private message, rendered as HTML

		if (SELF::TEST_MODE)
		{
			return true;	// In test mode we don't actually want to submit any ham
		}

		$request = 'blog='. urlencode($data['blog']) .
			'&user_ip='. urlencode($data['user_ip']) .
			//'&user_agent='. urlencode(SELF::EXT_VERSION) .	// In phpBB, the user agent is not stored with the post or PM. Akismet advises not to send it, even though it's required.
			'&referrer='. urlencode($data['referrer']) .
			'&permalink='. urlencode($data['permalink']) .
			'&comment_type='. urlencode($data['comment_type']) .
			'&comment_author='. urlencode($data['comment_author']) .
			'&comment_author_email='. urlencode($data['comment_author_email']) .
			'&comment_author_url='. urlencode($data['comment_author_url']) .
			'&comment_content='. urlencode($data['comment_content']) .
			'&comment_date_gmt'. urlencode($data['comment_date_gmt']) .
			'&blog_lang'. urlencode($data['blog_lang']) .
			'&blog_charset'. urlencode($data['blog_charset']);

		$host = $http_host = $key.'.rest.akismet.com';
		$path = '/1.1/submit-ham';
		$port = 443;
		$content_length = strlen($request);
		$http_request  = "POST $path HTTP/1.0\r\n";
		$http_request .= "Host: $host\r\n";
		$http_request .= "Content-Type: application/x-www-form-urlencoded\r\n";
		$http_request .= "Content-Length: {$content_length}\r\n";
		$http_request .= 'User-Agent: {' . SELF::EXT_VERSION . "}\r\n";
		$http_request .= "\r\n";
		$http_request .= $request;
		$response = '';

		if( false != ( $fs = @fsockopen( 'ssl://' . $http_host, $port, $errno, $errstr, 10 ) ) )
		{
			fwrite( $fs, $http_request );

			while ( !feof( $fs ) )
			{
				$response .= fgets( $fs, 1160 ); // One TCP-IP packet
			}
			fclose( $fs );

			$response = explode( "\r\n\r\n", $response, 2 );
		}

		return ('Thanks for making the web a better place.' == $response[1]) ? true : false;

	}

	private function akismet_comment_check($key, $data)
	{

		// Based on Akismet code found here: https://akismet.com/development/api/#comment-check
		//
		// $key = Askismet key associated with board (12 characters)
		// $data = content of post or private message, rendered as HTML

		$request = 'blog='. urlencode($data['blog']) .
			'&user_ip='. urlencode($data['user_ip']) .
			//'&user_agent='. urlencode(SELF::EXT_VERSION) .	// The user agent is not stored with the post or PM. Akismet advises not to send it, even though it's required.
			'&referrer='. urlencode($data['referrer']) .
			'&permalink='. urlencode($data['permalink']) .
			'&comment_type='. urlencode($data['comment_type']) .
			'&comment_author_email='. urlencode($data['comment_author_email']) .
			'&comment_author_url='. urlencode($data['comment_author_url']) .
			'&comment_content='. urlencode($data['comment_content']) .
			'&comment_date_gmt'. urlencode($data['comment_date_gmt']) .
			'&blog_lang'. urlencode($data['blog_lang']) .
			'&blog_charset'. urlencode($data['blog_charset']);

		// If just testing, tell Akismet. In test mode, Akismet's database won't change based on the information it is sent.
		if (SELF::TEST_MODE)
		{
			$request .= '&is_test=1';
		}

		// Simulation mode is strictly for development purposes. We need something to show in reports in case nothing is flagged as spam by Akismet.
		// This constant should normally be set to false. Pass the right stuff to Akismet and it will flag a false response.
		if (SELF::SIMULATION_MODE)
		{
			$force_spam = (bool) random_int(0, 1); // Make each request random, roughly half of the time it's spam, half it's not
			if (!$force_spam)
			{
				$request .= '&comment_author='. urlencode($data['comment_author']);
				$request .= '&user_role='. urlencode('administrator');	// Akismet will flag as ham, but knows this is fake
			}
			else
			{
				$request .= '&comment_author='. urlencode('viagra-test-123');	// Akismet will flag as spam, but knows this is fake
			}
		}
		else
		{
			$request .= '&comment_author='. urlencode($data['comment_author']);		// Normal spam check call to Akismet
		}

		$host = $http_host = $key.'.rest.akismet.com';
		$path = '/1.1/comment-check';
		$port = 443;
		$content_length = strlen($request);

		// Create the HTTP header
		$http_request  = "POST $path HTTP/1.0\r\n";
		$http_request .= "Host: $host\r\n";
		$http_request .= "Content-Type: application/x-www-form-urlencoded\r\n";
		$http_request .= "Content-Length: {$content_length}\r\n";
		$http_request .= 'User-Agent: {' . SELF::EXT_VERSION . "}\r\n";
		$http_request .= "\r\n";
		$http_request .= $request;
		$response = '';

		if ( false != ( $fs = @fsockopen( 'ssl://' . $http_host, $port, $errno, $errstr, 10 ) ) )
		{
			fwrite( $fs, $http_request );
			while ( !feof( $fs ) )
			{
				$response .= fgets( $fs, 1160 ); // One TCP-IP packet
			}
			fclose( $fs );
			$response = explode( "\r\n\r\n", $response, 2 );
		}
		$metadata = explode("\r\n", $response[0]);

		// Return the spam judgment that was made by the Akismet service
		if ('true' == $response[1])
		{
			if (in_array('X-akismet-pro-tip: discard', $metadata))
		   	{
			   	return SELF::BLATANT_SPAM;
		   	}
			else
			{
				return SELF::SPAM;
			}
		}
		else
		{
			return SELF::HAM;
		}

	}

	private function delete_post($post_id)
	{

		// This function removes a post, but possibly its topic and the poster's account too. If the first post of a topic is identified by Askismet
		// as spam, the whole topic is considered spammy, so any replies to it are removed as well by deleting the topic.
		// Functions delete_topics and delete_posts are found in /includes/functions_admin.php.
		//
		// $post_id = post_id to delete
		//
		// Returns $actions array:
		//		$actions['topic_removed'] == boolean
		//		$actions['user_removed'] == boolean
		// or false if no action was taken (topic containing post was previously deleted)

		$actions['topic_removed'] = false;
		$actions['user_removed'] = false;

		// Is this the first post of a topic? If so the entire topic is removed.
		$sql_ary = array(
			'SELECT'	=> 'topic_first_post_id, p.topic_id, p.poster_id',
			'FROM'		=> array(
				POSTS_TABLE		=> 'p',
				TOPICS_TABLE	=> 't',
			),
			'WHERE'		=> 'p.topic_id = t.topic_id AND post_id = ' . (int) $post_id,
		);
		$sql = $this->db->sql_build_query('SELECT', $sql_ary);

		$result = $this->db->sql_query($sql);
		$rowset = $this->db->sql_fetchrowset($result);

		if (count($rowset) !== 1)
		{
			// If the count is zero, the topic containing the post was probably already removed. The value should never be a
			// value greater than one, as it would indicate a very messed up database, but if it is, the function exits harmlessly.
			$this->db->sql_freeresult($result);
			// Also delete row from the phpbb_spam_found table
			$this->delete_spam_found_row($post_id, 1);
			return false;
		}

		$topic_id = $rowset[0]['topic_id'];
		$poster_id = $rowset[0]['poster_id'];

		$delete_topic = (bool) ((int) $rowset[0]['topic_first_post_id'] === (int) $post_id);
		$this->db->sql_freeresult($result);

		if ($delete_topic)
		{
			// Delete the topic containing the spam, including all its posts
			if (!SELF::TEST_MODE)
			{
				delete_topics('topic_id', $topic_id);
			}
			$actions['topic_removed'] = true;
		}
		else
		{
			// Delete just this post. It's assumed other posts in the topic are not identified as spam, at least not yet.
			if (!SELF::TEST_MODE)
			{
				delete_posts('post_id', $post_id);
				// Also delete row from the phpbb_spam_found table
				$this->delete_spam_found_row($post_id, 1);
			}
			$actions['user_removed'] = false;
		}

		// If the poster now has no approved posts, delete their account because they are a filthy spammer!
		$this->conditionally_delete_user($poster_id);

		return $actions;

	}

	private function delete_private_message($msg_id)
	{

		// This function removes a private message from all private message boxes.
		//
		// $msg_id = msg_id to be removed

		// Get information on who this private message was sent to. This typically includes the author, since a copy goes into their sent box or outbox.
		$sql_ary = array(
			'SELECT'	=> '*',
			'FROM'		=> array(
				PRIVMSGS_TO_TABLE		=> 'pt',
			),
			'WHERE'		=> 'msg_id = ' . (int) $msg_id,
		);
		$sql = $this->db->sql_build_query('SELECT', $sql_ary);
		$result = $this->db->sql_query($sql);
		$rowset = $this->db->sql_fetchrowset($result);

		$author_ids = array();

		foreach ($rowset as $row)
		{
			$author_ids[] = $row['author_id'];
			if (!SELF::TEST_MODE)
			{
				// Delete this copy of the private message
				delete_pm($row['user_id'], array($msg_id), $row['folder_id']);	// Function is in includes/functions_privmsgs.php.
				// Also delete row from the phpbb_spam_found table
				$this->delete_spam_found_row($msg_id, 0);
			}
		}
		$this->db->sql_freeresult($result);

		$author_ids = array_unique($author_ids);

		if (!SELF::TEST_MODE)
		{
			foreach ($author_ids as $author_id)
			{
				$this->conditionally_delete_user($author_id);
			}
		}

		return;

	}

	private function conditionally_delete_user($user_id)
	{

		// If the poster or private message author now has no approved posts, delete their account because they are a filthy spammer!
		//
		// $user_id = user_id in phpbb_users table

		$sql_ary = array(
			'SELECT'	=> 'user_posts, user_type',
			'FROM'		=> array(
				USERS_TABLE		=> 'u',
			),
			'WHERE'		=> 'user_id = ' . (int) $user_id,
		);
		$sql = $this->db->sql_build_query('SELECT', $sql_ary);

		$result = $this->db->sql_query($sql);
		$rowset = $this->db->sql_fetchrowset($result);
		$user_posts = (int) $rowset[0]['user_posts'];

		if ($user_posts === 0)
		{
			// Do not remove accounts for any founder, guests, administrator or global moderator
			if ($rowset[0]['user_type'] !== USER_FOUNDER && $rowset[0]['user_type'] !== ANONYMOUS && $this->is_not_admin_or_moderator($rowset[0]['user_id']))
			{
				if (!SELF::TEST_MODE)
				{
					user_delete('remove', $user_id, false);
				}
			}
		}
		$this->db->sql_freeresult($result);
		return;

	}

	private function is_not_admin_or_moderator($user_id)
	{

		// Returns true if the user_id is not for an administrator or global moderator.
		$sql_ary = array(
			'SELECT'	=> '1',
			'FROM'		=> array(
				USER_GROUP_TABLE		=> 'ug',
				GROUPS_TABLE			=> 'g',
			),
			'WHERE'		=> 'g.group_id = ug.group_id AND ' . $this->db->sql_in_set('group_name', array('ADMINISTRATORS', 'GLOBAL_MODERATORS'), false) . ' AND user_id = ' . (int) $user_id
		);
		$sql = $this->db->sql_build_query('SELECT', $sql_ary);

		$result = $this->db->sql_query($sql);
		$rowset = $this->db->sql_fetchrowset($result);
		$user_can_be_deleted = (count($rowset) === 1) ? true : false;
		$this->db->sql_freeresult($result);

		return $user_can_be_deleted;

	}

	private function find_spam_posts()
	{

		// Finds post spam until either all are processed, the batch size is reached or the still_on_time() function returns false. If not all posts
		// are processed, a meta refresh happens to process the next batch of posts.

		if (!((bool) $this->request->variable('phpbbservices_spamremover_posts', 0)))
		{
			// For some reason this function is being called but posts are not to be checked, so try to find spam private messages
			$this->config->set('phpbbservices_spamremover_posts_found', 1); // This value is a flag indicating find posts step has completed
			meta_refresh(1, $this->u_action . '&amp;find_type=pms');		// Check private messages next
		}

		$batch_size = (int) $this->config['phpbbservices_spamremover_batch_size'];

		// Determine the date range, if any, of the posts to check for spam
		$start_date_str = trim($this->config['phpbbservices_spamremover_posts_start_date']);
		$end_date_str = trim($this->config['phpbbservices_spamremover_posts_end_date']);

		// Create the SQL to find the correct posts to check
		if ($start_date_str == '' && $end_date_str == '')
		{
			$date_range = '';
		}
		else if ($start_date_str !== '' && $end_date_str == '')
		{
			$date_range = ' AND post_time >= ' . strtotime($start_date_str);
		}
		else if ($start_date_str == '' && $end_date_str !== '')
		{
			$date_range = ' AND post_time <= ' . strtotime($end_date_str);
		}
		else
		{
			$date_range = ' AND post_time >= ' . strtotime($start_date_str) . ' AND post_time <= ' . strtotime($end_date_str);
		}

		$last_post_id_checked = ((bool) $this->config['phpbbservices_spamremover_find_all_posts'] == 0) ? $this->config['phpbbservices_spamremover_last_post_id'] : 0;

		// Information on progress is passed as URL parameters. Couldn't get this to work with static variables.
		$posts_spam_found = (int) $this->request->variable('spam', 0);	// Running total of spam found so far
		$posts_checked = (int) $this->request->variable('checked', 0);	// Running total of posts checked so far
		$posts_to_check = (int) $this->request->variable('to_check', 0);	// Total posts requiring checking. If zero, it is calculated next.

		// Determine total number of posts that need to be checked
		if ($posts_to_check === 0);
		{
			$sql_ary = array(
				'SELECT' 	=> 'count(*) AS row_count',
				'FROM'		=> 	array(
					POSTS_TABLE => 'p',
					USERS_TABLE => 'u',
					TOPICS_TABLE => 't',
					FORUMS_TABLE => 'f'),
				'WHERE'		=> "p.poster_id = u.user_id AND p.topic_id = t.topic_id AND p.forum_id = f.forum_id $date_range AND p.post_id > " . (int) $last_post_id_checked,
				'ORDER_BY'	=> 'post_id');
			$sql = $this->db->sql_build_query('SELECT', $sql_ary);
			$result = $this->db->sql_query($sql);
			$posts_to_check = $this->db->sql_fetchfield('row_count');
			$this->db->sql_freeresult($result);
		}

		// Add criteria to search after last post_id searched, if requested
		$start_after_id = (int) $this->request->variable('find_id', 0);
		$skip_post_searched_sql = ($start_after_id == 0) ? '' : ' AND post_id > ' . $start_after_id;

		$sql_ary = array(
			'SELECT' 	=> 'post_id, post_time, poster_ip, poster_id, post_username, post_text, username, 
						user_email, enable_bbcode, enable_smilies, enable_magic_url, user_sig_bbcode_uid, 
						user_sig_bbcode_bitfield, topic_title, forum_name, user_dateformat, topic_first_post_id,
						topic_posts_approved, topic_posts_unapproved, topic_posts_softdeleted, topic_first_post_id',
			'FROM'		=> 	array(
				POSTS_TABLE => 'p',
				USERS_TABLE => 'u',
				TOPICS_TABLE => 't',
				FORUMS_TABLE => 'f'),
			'WHERE'		=> "p.poster_id = u.user_id AND p.topic_id = t.topic_id AND p.forum_id = f.forum_id $date_range $skip_post_searched_sql AND p.post_id > " . (int) $last_post_id_checked,
			'ORDER_BY'	=> 'post_id');

		$sql = $this->db->sql_build_query('SELECT', $sql_ary);
		$result = $this->db->sql_query_limit($sql, $batch_size);
		$rowset = $this->db->sql_fetchrowset($result);

		$i = 0;
		while (still_on_time() && ($i < $batch_size) && ($i < (count($rowset))))
		{

			// Need BBCode flags to translate BBCode into HTML
			$flags = (($rowset[$i]['enable_bbcode']) ? OPTION_FLAG_BBCODE : 0) +
				(($rowset[$i]['enable_smilies']) ? OPTION_FLAG_SMILIES : 0) +
				(($rowset[$i]['enable_magic_url']) ? OPTION_FLAG_LINKS : 0);
			$post_text = generate_text_for_display(censor_text($rowset[$i]['post_text']), $rowset[$i]['bbcode_uid'], $rowset[$i]['bbcode_bitfield'], $flags);

			// Is this spam?
			$data = array('blog'                 => $this->board_url,
						  'user_ip'              => $rowset[$i]['poster_ip'],
						  'referrer'             => '',
						  'permalink'            => '',
						  'comment_type'         => ($rowset[$i]['post_id'] == $rowset[$i]['topic_first_post_id']) ? 'forum-post' : 'reply',
						  'comment_author'       => $rowset[$i]['username'],
						  'comment_author_email' => $rowset[$i]['user_email'],
						  'comment_author_url'   => '',
						  'comment_content'      => $post_text,
						  'comment_date_gmt'     => date('c', $rowset[$i]['post_time']),
						  'blog_lang'            => $this->config['default_lang'],
						  'blog_charset'         => 'UTF-8',
			);

			// Do an Akismet comment check
			$spam_type = $this->akismet_comment_check($this->config['phpbbservices_spamremover_akismet_key'], $data, true);

			// Note if it was flagged as spam
			if (in_array($spam_type, array(SELF::SPAM, SELF::BLATANT_SPAM)))
			{
				$posts_spam_found++;
			}

			// Mark the post with the spam assessment and when it was done
			if ($spam_type !== SELF::HAM)
			{
				$spam_exists = $this->spam_exists($rowset[$i]['post_id'], 1);
				if ($spam_exists)
				{
					$sql_ary2 = array(
						'is_blatant_spam' 	=> (int) $spam_type,
						'spam_check_time' 	=> (int) time(),
					);
					$sql2 = 'UPDATE ' . $this->spam_found_table . ' SET ' . $this->db->sql_build_array('UPDATE', $sql_ary2) .
						' WHERE post_msg_id = ' . (int) $rowset[$i]['post_id'] . ' AND is_post = 1';
				}
				else
				{
					$sql_ary2 = array(
						'is_post'			=> 1,
						'post_msg_id'		=> (int) $rowset[$i]['post_id'],
						'is_blatant_spam' 	=> (int) $spam_type,
						'spam_check_time' 	=> (int) time(),
					);
					$sql2 = 'INSERT INTO ' . $this->spam_found_table . $this->db->sql_build_array('INSERT', $sql_ary2);
				}
				$this->db->sql_query($sql2);
			}
			else	// It's ham
			{
				// Delete from the phpbb_spam_posts table if perhaps it was once judged as spam but isn't anymore.
				$this->delete_spam_found_row($rowset[$i]['post_id'], 1);
			}

			// Note the last post_id saved
			$last_post_id = $rowset[$i]['post_id'];
			$this->config->set('phpbbservices_spamremover_last_post_id', $last_post_id);
			$posts_checked++;
			$i++;

		}
		$this->db->sql_freeresult($result);

		// Provide a status update with an automatic redirect
		$all_done = (bool) ((int) $posts_checked >= (int) $posts_to_check);

		if ($all_done)
		{
			// Note in the log the find spam posts function was run, then try private messages next
			$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_ACP_SPAMREMOVER_FIND_SPAM_POSTS_RAN');
			$check_pms = (bool) $this->config['phpbbservices_spamremover_pms'] == 1;
			if ($check_pms)
			{
				$this->config->set('phpbbservices_spamremover_posts_found', 1); // This value is a flag indicating find posts step has completed
				meta_refresh(1, $this->u_action . '&amp;find_type=pms');	// Check private messages next
				trigger_error($this->language->lang('ACP_SPAMREMOVER_ALL_POSTS_CHECKED', $posts_spam_found, $posts_to_check));
			}
			else
			{
				// Don't need to check private messages, so we're all done
				$this->u_action = append_sid("index.$this->phpEx?i=-phpbbservices-spamremover-acp-main_module&amp;mode=find");
				meta_refresh(1, $this->u_action);	// Check private messages next
				trigger_error($this->language->lang('ACP_SPAMREMOVER_ALL_POSTS_CHECKED', $posts_spam_found, $posts_to_check) . adm_back_link($this->u_action));
			}
		}
		else
		{
			$percent_done = round((($posts_checked / $posts_to_check) * 100),1);
			meta_refresh(1, $this->u_action . "&amp;find_type=posts&amp;find_id=$last_post_id&amp;spam=$posts_spam_found&amp;checked=$posts_checked&amp;to_check=$posts_to_check");	// Check next batch of posts
			trigger_error($this->language->lang('ACP_SPAMREMOVER_PARTIAL_POSTS_CHECKED', $posts_spam_found, $posts_checked, ($posts_to_check - $posts_checked), $percent_done) . adm_back_link($this->u_action));
		}

	}

	private function find_spam_pms()
	{

		// Finds private mesasge spam until either all are processed, the batch size is reached or the still_on_time() function returns false. If the still_on_time()
		// function returns false or the batch size is reached, a meta refresh happens to process the next batch of private messages.

		// After finding all requested spam posts, if a configuration value is not set, we don't want to find any spam private messages
		if (!((bool) $this->config['phpbbservices_spamremover_posts_found']))
		{
			return;
		}

		$batch_size = (int) $this->config['phpbbservices_spamremover_batch_size'];

		// Determine the date range, if any, of the private messages to check for spam
		$start_date_str = trim($this->config['phpbbservices_spamremover_pms_start_date']);
		$end_date_str = trim($this->config['phpbbservices_spamremover_pms_end_date']);

		// Create the SQL to find the correct posts to check
		if ($start_date_str == '' && $end_date_str == '')
		{
			$date_range = '';
		}
		else if ($start_date_str !== '' && $end_date_str == '')
		{
			$date_range = ' AND message_time >= ' . strtotime($start_date_str);
		}
		else if ($start_date_str == '' && $end_date_str !== '')
		{
			$date_range = ' AND message_time <= ' . strtotime($end_date_str);
		}
		else
		{
			$date_range = ' AND message_time >= ' . strtotime($start_date_str) . ' AND message_time <= ' . strtotime($end_date_str);
		}

		$last_pms_id_checked = ((bool) $this->config['phpbbservices_spamremover_find_all_pms'] == 0) ? $this->config['phpbbservices_spamremover_last_pms_id'] : 0;

		// Information on progress is passed as URL parameters. Couldn't get this to work with static variables.
		$pms_spam_found = (int) $this->request->variable('spam', 0);	// Running total of spam found so far
		$pms_checked = (int) $this->request->variable('checked', 0);	// Running total of private messages checked so far
		$pms_to_check = (int) $this->request->variable('to_check', 0);	// Total posts requiring checking. If zero, it is calculated next.

		// Determine total number of private messages that need to be checked
		if ($pms_to_check === 0)
		{
			$sql_ary = array(
				'SELECT' 	=> 'count(*) AS row_count',
				'FROM'		=> 	array(
					PRIVMSGS_TABLE => 'pm',
					USERS_TABLE => 'u'),
				'WHERE'		=> "pm.author_id = u.user_id $date_range AND pm.msg_id > " . (int) $last_pms_id_checked,
				'ORDER_BY'	=> 'msg_id');
			$sql = $this->db->sql_build_query('SELECT', $sql_ary);
			$result = $this->db->sql_query($sql);
			$pms_to_check = $this->db->sql_fetchfield('row_count');
			$this->db->sql_freeresult($result);
		}

		// Add criteria to search after last msg_id searched, if requested
		$start_after_id = (int) $this->request->variable('find_id', 0);
		$skip_pms_searched_sql = ($start_after_id === 0) ? '' : ' AND msg_id > ' . $start_after_id;

		$sql_ary = array(
			'SELECT' 	=> 'msg_id, message_time, author_ip, user_id, username, message_text, username, 
								user_email, enable_bbcode,	enable_smilies, enable_magic_url, user_sig_bbcode_uid, 
								user_sig_bbcode_bitfield, message_subject, user_dateformat',
			'FROM'		=> 	array(
				PRIVMSGS_TABLE => 'pm',
				USERS_TABLE => 'u'),
			'WHERE'		=> "pm.author_id = u.user_id $date_range $skip_pms_searched_sql AND pm.msg_id > " . (int) $last_pms_id_checked,
			'ORDER_BY'	=> 'msg_id');
		$sql = $this->db->sql_build_query('SELECT', $sql_ary);

		$result = $this->db->sql_query_limit($sql, $batch_size);
		$rowset = $this->db->sql_fetchrowset($result);

		$i = 0;
		while (still_on_time() && ($i < $batch_size) && ($i < (count($rowset))))
		{

			// Need BBCode flags to translate BBCode into HTML
			$flags = (($rowset[$i]['enable_bbcode']) ? OPTION_FLAG_BBCODE : 0) +
				(($rowset[$i]['enable_smilies']) ? OPTION_FLAG_SMILIES : 0) +
				(($rowset[$i]['enable_magic_url']) ? OPTION_FLAG_LINKS : 0);
			$message_text = generate_text_for_display(censor_text($rowset[$i]['message_text']), $rowset[$i]['bbcode_uid'], $rowset[$i]['bbcode_bitfield'], $flags);

			// Is this spam?
			$data = array('blog'                 => $this->board_url,
						  'user_ip'              => $rowset[$i]['author_ip'],
						  'referrer'             => '',
						  'permalink'            => '',
						  'comment_type'         => 'message',
						  'comment_author'       => $rowset[$i]['username'],
						  'comment_author_email' => $rowset[$i]['user_email'],
						  'comment_author_url'   => '',
						  'comment_content'      => $message_text,
						  'comment_date_gmt'     => date('c', $rowset[$i]['message_time']),
						  'blog_lang'            => $this->config['default_lang'],
						  'blog_charset'         => 'UTF-8',
			);

			// Do an Akismet comment check
			$spam_type = $this->akismet_comment_check($this->config['phpbbservices_spamremover_akismet_key'], $data, true);
			if (in_array($spam_type, array(SELF::SPAM, SELF::BLATANT_SPAM)))
			{
				$pms_spam_found++;
			}

			// Mark the private message with the spam assessment and when it was done
			if ($spam_type !== SELF::HAM)
			{
				$spam_exists = $this->spam_exists($rowset[$i]['msg_id'], 0);
				if ($spam_exists)
				{
					$sql_ary2 = array(
						'is_blatant_spam' 	=> (int) $spam_type,
						'spam_check_time' 	=> (int) time(),
					);
					$sql2 = 'UPDATE ' . $this->spam_found_table . ' SET ' . $this->db->sql_build_array('UPDATE', $sql_ary2) .
						' WHERE post_msg_id = ' . (int) $rowset[$i]['msg_id'] . ' AND is_post = 0';
				}
				else
				{
					$sql_ary2 = array(
						'is_post'			=> 0,
						'post_msg_id'		=> (int) $rowset[$i]['msg_id'],
						'is_blatant_spam' 	=> (int) $spam_type,
						'spam_check_time' 	=> (int) time(),
					);
					$sql2 = 'INSERT INTO ' . $this->spam_found_table . $this->db->sql_build_array('INSERT', $sql_ary2);
				}
				$this->db->sql_query($sql2);
			}
			else	// It's ham
			{
				// Delete from the phpbb_spam_posts table if perhaps it was once judged as spam but isn't anymore.
				$this->delete_spam_found_row($rowset[$i]['post_id'], 0);
			}

			// Note the last msg_id saved
			$last_msg_id = $rowset[$i]['msg_id'];
			$this->config->set('phpbbservices_spamremover_last_pms_id', $last_msg_id);
			$pms_checked++;
			$i++;
		}

		// Provide a status update with an automatic redirect
		$all_done = (bool) ((int) $pms_checked >= (int) $pms_to_check);

		if ($all_done)
		{
			// Note in the log the find spam function was run.
			$this->config->set('phpbbservices_spamremover_posts_found', 0); // This value is a flag indicating find private messages step completed
			$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_ACP_SPAMREMOVER_FIND_SPAM_PMS_RAN');
			trigger_error($this->language->lang('ACP_SPAMREMOVER_ALL_PMS_CHECKED', $pms_spam_found, $pms_to_check) . adm_back_link($this->u_action));
		}
		else
		{
			$percent_done = round((($pms_checked / $pms_to_check) * 100),1);

			meta_refresh(1, $this->u_action . "&amp;find_type=pms&amp;find_id=$last_msg_id&amp;spam=$pms_spam_found&amp;checked=$pms_checked&amp;to_check=$pms_to_check");	// Check next batch of private messages
			trigger_error($this->language->lang('ACP_SPAMREMOVER_PARTIAL_PMS_CHECKED', $pms_spam_found, $pms_checked, ($pms_to_check - $pms_checked), $percent_done) . adm_back_link($this->u_action));
		}

	}

	private function spam_exists($post_msg_id, $is_post)
	{
		// Checks to see if a row already exists in the phpbb_spam_found table. Returns true if it exists, false otherwise.
		$is_post_value = ($is_post) ? 1 : 0;
		$sql = 'SELECT * FROM ' . $this->spam_found_table . ' WHERE post_msg_id = ' . (int) $post_msg_id . ' AND is_post = ' . (int) $is_post_value;
		$result = $this->db->sql_query($sql);
		$rowset = $this->db->sql_fetchrowset($result);
		return (bool) count($rowset);
	}

	private function delete_spam_found_row($post_msg_id, $is_post)
	{
		// Deletes a row in the phpbb_spam_found table if it exists. This can happen if a post or private message was
		// previously judged by Akismet as spam, but isn't anymore. Or, more typically, if the admin marks a post or private
		// message as ham.
		$is_post_value = ($is_post) ? 1 : 0;
		$sql = 'DELETE FROM ' . $this->spam_found_table . ' WHERE post_msg_id = ' . (int) $post_msg_id . ' AND is_post = ' . (int) $is_post_value;
		$this->db->sql_query($sql);
	}

}