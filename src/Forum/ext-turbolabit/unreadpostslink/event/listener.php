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

	/** @var string phpBB root path */
	protected $root_path;

	/**
	* Constructor for listener
	*
	* @param \phpbb\config\config $config phpBB config
	* @param \phpbb\template\twig\twig $template phpBB template
	* @access public
	*/
	public function __construct(\phpbb\config\config $config, \phpbb\template\twig\twig $template, \phpbb\user $user, $root_path)
	{
		$this->config		= $config;
		$this->template		= $template;
		$this->user			= $user;
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
		return array('core.page_header_after'	=> 'assign_mark_forums_read');
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
