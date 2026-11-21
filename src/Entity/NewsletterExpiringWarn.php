<?php
namespace App\Entity;

use App\Entity\PhpBB\User;
use App\Repository\NewsletterExpiringWarnRepository;
use App\Trait\IdableEntityTrait;
use App\Trait\TimestampableEntityTrait;
use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity(repositoryClass: NewsletterExpiringWarnRepository::class)]
#[ORM\UniqueConstraint(name: 'user_unique_idx', columns: ['user_id'])]
class NewsletterExpiringWarn
{
    use IdableEntityTrait, TimestampableEntityTrait;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(referencedColumnName: 'user_id', nullable: false)]
    private ?User $user = null;


    public function getUser() : ?User
    {
        return $this->user;
    }

    public function setUser(?User $user) : static
    {
        $this->user = $user;
        return $this;
    }
}
