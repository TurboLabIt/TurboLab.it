<?php
/**
*
* @package phpBB Extension - Tapatalk: remove default signature
* @copyright (c) 2015 TurboLab.it
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

/**
*/
namespace turbolabit\tapatalkstripsign\event;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
* Event listener class
*/
class listener implements EventSubscriberInterface
{
    /**
    * Assign functions defined in this class to event listeners in the core
    *
    * @return array
    * @static
    * @access public
    */
    static public function getSubscribedEvents()
    {
            return array('core.message_parser_check_message'	=> 'removeTapatalkSign');
    }

    /**
    * Remove Tapatalk default signature
    *
    * @param $event the event
    * @access public
    */
    public function removeTapatalkSign($event)
    {
            $text_body	= $event["message"];

            $text_body = preg_replace('/Sent from my[\s\S]+?using Tapatalk/i', '',$text_body);
            $text_body = preg_replace('/Inviato dal mio[\s\S]+?utilizzando Tapatalk/i', '', $text_body);

            $event["message"] = trim($text_body);
    }
}
