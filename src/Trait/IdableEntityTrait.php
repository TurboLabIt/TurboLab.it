<?php
namespace App\Trait;

use App\Exception\InvalidIdException;
use Doctrine\ORM\Mapping as ORM;


trait IdableEntityTrait
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['unsigned' => true])]
    protected ?int $id = null;

    public function getId() : ?int { return $this->id; }

    public function setId(int $id) : static
    {
        if( empty($id) || $id < 1 ) {
            throw new InvalidIdException();
        }

        $this->id = $id;
        return $this;
    }
}
