<?php
namespace App\Entity;


abstract class BaseEntity
{
    public function getId() : ?int { return null; }
    public function getTitle() : ?string { return ''; }
}
