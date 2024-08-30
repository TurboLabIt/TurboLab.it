<?php
namespace App\Twig;

use DateTime;
use NumberFormatter;
use Twig\Extension\RuntimeExtensionInterface;


class TliRuntime implements RuntimeExtensionInterface
{
    public function friendlyNum(string|float|int $input): string
    {
        return (new NumberFormatter('it_IT', NumberFormatter::DECIMAL))->format($input);
    }


    public function friendlyDate(DateTime $date = null): ?string
    {
        if( empty($date) ) {
            return null;
        }

        $oNow = new DateTime();
        $secDiff = $oNow->getTimestamp() - $date->getTimestamp();
        $oneDayInSec = 3600 * 24;

        $stopFriendlyness = $oneDayInSec * 2;
        if( $secDiff < 0 || $secDiff > $stopFriendlyness ) {
            return $date->format('d/m/Y') . ', ' . $date->format('H:i');
        }

        if( $secDiff >= $oneDayInSec ) {
            return 'ieri alle ' . $date->format('H:i');
        }

        $oneHourInSec = 3600;
        if( $secDiff >= $oneHourInSec ) {
            $num    = (int)floor($secDiff / 3600);
            $word   = $num == 1 ? 'ora' : 'ore';
            return $num . ' ' . $word . ' fa';
        }

        $stopNow = 60 * 30;
        if( $secDiff >= $stopNow ) {
            $num    = (int)floor($secDiff / 60);
            return $num . ' minuti fa';
        }

        return 'un attimo fa';
    }
}
