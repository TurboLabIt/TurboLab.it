<?php
namespace App\ServiceCollection;

use TurboLabIt\Foreachable\Foreachable;


abstract class BaseServiceCollection implements \Iterator, \Countable, \ArrayAccess
{
    use Foreachable;
}
