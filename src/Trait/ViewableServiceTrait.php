<?php
namespace App\Trait;


trait ViewableServiceTrait
{
    protected string $clientIpAddress;
    protected int $localViewCount = 0;


    public function getClientIpAddress() : string
    {
        return $this->clientIpAddress;
    }


    public function setClientIpAddress(string $clientIpAddress): static
    {
        $this->clientIpAddress = $clientIpAddress;
        return $this;
    }


    public function countOneView() : static
    {
        $countableIpAddress =
            filter_var(
                $this->clientIpAddress, FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            ) ?? null;

        if( empty($countableIpAddress) ) {
            return $this;
        }

        $this->localViewCount++;
        $this->em->getRepository(static::ENTITY_CLASS)->countOneView( $this->getId() );
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
