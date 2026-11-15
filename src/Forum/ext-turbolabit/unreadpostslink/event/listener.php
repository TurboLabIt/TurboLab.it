<?php
/**
*
* @package phpBB Extension - Unread posts link in navbar
* @copyright (c) 2015 TurboLab.it
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

/**
*/
namespace turbolabit\unreadpostslink\event;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
* Event listener class
*/
class listener implements EventSubscriberInterface
{
	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\template\twig\twig */
	protected $template;

	/** @var \phpbb\user */
	protected $user;

	/** @var \phpbb\request\request_interface */
	protected $request;

	/** @var string phpBB root path */
	protected $root_path;

	/**
	* Constructor for listener
	*
	* @param \phpbb\config\config $config phpBB config
	* @param \phpbb\template\twig\twig $template phpBB template
	* @param \phpbb\request\request_interface $request phpBB request
	* @access public
	*/
	public function __construct(\phpbb\config\config $config, \phpbb\template\twig\twig $template, \phpbb\user $user, \phpbb\request\request_interface $request, $root_path)
	{
		$this->config		= $config;
		$this->template		= $template;
		$this->user			= $user;
		$this->request		= $request;
		$this->root_path	= $root_path;
	}
	
	/**
	* Assign functions defined in this class to event listeners in the core
	*
	* @return array
	* @static
	* @access public
	*/
	static public function getSubscribedEvents()
	{
		return array(
			'core.page_header_after'			=> 'assign_mark_forums_read',
			'core.search_modify_param_after'	=> 'rename_own_topics_search',
		);
	}


	/**
	* "I tuoi argomenti" (quick links) is "I tuoi messaggi" limited to the first post of each topic:
	* its results page gets its own title and breadcrumb
	*
	* @param $event the event
	* @access public
	*/
	public function rename_own_topics_search($event)
	{
		global $phpbb_root_path, $phpEx;

		if ($event['search_id'] != 'egosearch' || $this->request->variable('sf', '') != 'firstpost')
		{
			return;
		}

		$event['l_search_title'] = 'I tuoi argomenti';

		// search.php has just added the breadcrumb of "I tuoi messaggi": it's the last one
		$this->template->alter_block_array('navlinks', array(
			'BREADCRUMB_NAME'	=> 'I tuoi argomenti',
			'U_BREADCRUMB'		=> append_sid("{$phpbb_root_path}search.$phpEx", 'search_id=egosearch&amp;sf=firstpost'),
		), true, 'change');
	}
	
	/**
	* Assigns the "Mark forums read" URL to U_MARK_FORUMS
	*
	* @param $event the event
	* @access public
	*/
	public function assign_mark_forums_read($event)
	{
		global $phpbb_root_path, $phpEx;
	
		if ($this->user->data['is_registered'] || $this->config['load_anon_lastread'])
		{
			$link_mark_all_read	= append_sid("{$phpbb_root_path}index.$phpEx", 'hash=' . generate_link_hash('global') . '&amp;mark=forums&amp;mark_time=' . time());
		}
		else
		{
			$link_mark_all_read	= '';
		}
		
		$this->template->assign_vars(array('U_MARK_FORUMS' => $link_mark_all_read));
	}
}
