<?php
namespace App\Entity;

use App\Entity\PhpBB\User;
use App\Repository\NewsletterOpenerRepository;
use App\Trait\IdableEntityTrait;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;


#[ORM\Entity(repositoryClass: NewsletterOpenerRepository::class)]
#[ORM\UniqueConstraint(name: 'user_unique_idx', columns: ['user_id'])]
class NewsletterOpener
{
    use IdableEntityTrait, TimestampableEntity;

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
