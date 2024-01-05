<?php
/**
*
* @package phpBB Extension - HTTPS on image hoster
* @copyright (c) 2015 TurboLab.it
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

/**
*/
namespace turbolabit\httpsonimg\event;
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
	
	/** @var array list of HTTPS image hoster */
	protected $https_image_hoster	= array(
					'imgur.com',
					'imageshack.com',
					'imageshack.us',
					'tapatalk.imageshack.com'
	);

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
		return array('core.modify_text_for_display_after'	=> 'replace_img_with_https');
	}
	
	/**
	* Adds the rel=nofollow attribute to all URLs in the posted messages
	*
	* @param $event the event
	* @access public
	*/
	public function replace_img_with_https($event)
	{
		$text	= "<wrapper>" . $event["text"]  . "</wrapper>";				//prevent DOCTYPE, HEAD, BODY output
		$text	= mb_convert_encoding($text, 'HTML-ENTITIES', 'UTF-8');		//preserve UTF-8 char
		
		$dom 	= new \DOMDocument;
		libxml_use_internal_errors(true);									//prevents warning on partial html
		$dom->loadHTML($text);
		$elements = $dom->getElementsByTagName('img');
		
		foreach ($elements as $element)
		{
			$src	= $element->getAttribute('src');
			$src	= trim($src);
			
			if ( $src != '' && strpos($src,'https://')===false )
			{
				$host		= parse_url($src, PHP_URL_HOST);
				
				if(is_string($host) && trim($host) != '')
				{
					$hostParts	= explode('.', $host);
					$hostParts	= array_reverse($hostParts);
					$host		= $hostParts[1] . '.' . $hostParts[0];
				
					//special case: something.co.uk
					if($hostParts[1]=='co' && $hostParts[2]=='uk')
					{
						$host	= $hostParts[2] . '.' . $host;
					}
					
					$host	= mb_strtolower($host);
					
					if(in_array($host, $this->https_image_hoster))
					{
						$src	= str_ireplace('http://', 'https://', $src);
						$element->setAttribute('src', $src);
					}
				}
			}
		}
		
		$event["text"] = $dom->saveHTML($dom->getElementsByTagName('wrapper')->item(0));
	}
}
