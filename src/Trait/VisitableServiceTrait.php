<?php
namespace App\Trait;


trait VisitableServiceTrait
{
    protected int $localViewCount   = 0;
    protected bool $isVisitable     = true;


    public function isVisitable() : bool { return $this->isVisitable; }

    public function countOneVisit() : static
    {
        $this->localViewCount++;
        $this->getRepository()->countOneView( $this->getId() );
        return $this;
    }


    public function getViews(bool $formatted = true) : int|string
    {
        $num = $this->localViewCount;

        if($formatted && !empty($num) ) {
            $num = number_format($num, 0, '', '.');
        }

        return $num;
    }
}
