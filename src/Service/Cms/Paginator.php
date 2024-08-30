<?php
namespace App\Service\Cms;

use TurboLabIt\PaginatorBundle\Service\Paginator as BasePaginator;


class Paginator extends BasePaginator
{
    protected string $mode  = self::MODE_LAST_SLUG;
}
