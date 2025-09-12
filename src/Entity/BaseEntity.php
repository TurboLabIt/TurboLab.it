<?php
namespace App\Entity;


abstract class BaseEntity
{
    const string TLI_CLASS = 'base';

    public function getId() : ?int { return null; }

    public function getTitle() : ?string { return ''; }

    public function getClass() : string { return static::TLI_CLASS; }
}
