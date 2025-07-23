<?php
namespace App\Trait;


trait SaveableTrait
{
    public function save(bool $persist = true) : static
    {
        if($persist) {

            $this->factory->getEntityManager()->persist($this->entity);
            $this->factory->getEntityManager()->flush();
        }

        return $this;
    }
}
