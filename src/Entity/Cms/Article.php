<?php
namespace App\Entity\Cms;

use App\Interface\ArticleInterface;
use App\Repository\Cms\ArticleRepository;
use App\Trait\AbstractableEntityTrait;
use App\Trait\AdsableEntityTrait;
use App\Trait\ArticleFormatableEntityTrait;
use App\Trait\BodyableEntityTrait;
use App\Trait\PublishableEntityTrait;
use App\Trait\TitleableEntityTrait;
use App\Trait\ViewableEntityTrait;
use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity(repositoryClass: ArticleRepository::class)]
class Article extends BaseCmsEntity
{
    use AbstractableEntityTrait;
    use AdsableEntityTrait;
    use ArticleFormatableEntityTrait;
    use BodyableEntityTrait;
    use PublishableEntityTrait;
    use TitleableEntityTrait;
    use ViewableEntityTrait;
}
