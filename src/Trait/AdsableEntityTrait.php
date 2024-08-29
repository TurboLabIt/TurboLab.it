<?php
namespace App\Trait;

use Doctrine\ORM\Mapping as ORM;


trait AdsableEntityTrait
{
    #[ORM\Column]
    protected ?bool $showAds = true;

    public function showAds(): ?bool { return $this->showAds; }

    public function setShowAds(bool $showAds): static
    {
        $this->showAds = $showAds;
        return $this;
    }
}
