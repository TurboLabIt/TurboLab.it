<?php
namespace App\Service\Cms;

use App\Entity\Cms\ArticleTag;
use App\Entity\Cms\Tag as TagEntity;
use App\Entity\Cms\TagAuthor;
use App\Service\Factory;
use App\Service\TextProcessor;
use App\Service\User;
use App\Trait\SaveableTrait;


class TagEditor extends Tag
{
    use SaveableTrait;


    public function __construct(Factory $factory, protected TextProcessor $textProcessor)
    {
        parent::__construct($factory);
    }


    public function setTitle(string $newTitle) : static
    {
        $cleanTitle = $this->textProcessor->processRawInputTitleForStorage($newTitle);
        $cleanTitle = mb_strtolower($cleanTitle);
        $this->entity->setTitle($cleanTitle);
        return $this;
    }


    public function setReplacement(Tag|TagEntity|null $replacementTag) : static
    {
        $replacementTag = $replacementTag instanceof Tag ? $replacementTag->getEntity() : $replacementTag;
        $this->entity->setReplacement($replacementTag);

        $entityManager      = $this->factory->getEntityManager();
        $articleJunctions   = $this->entity->getArticles();

        foreach($articleJunctions as $junction) {

            $article    = $junction->getArticle();
            $user       = $junction->getUser();
            $ranking    = $junction->getRanking();

            $entityManager->remove($junction);

            $article->addTag(
                (new ArticleTag())
                    ->setArticle($article)
                    ->setTag($replacementTag)
                    ->setUser($user)
                    ->setRanking($ranking)
            );
        }

        return $this;
    }


    public function addAuthor(User $author) : static
    {
        $this->entity->addAuthor(
            (new TagAuthor())
                ->setUser( $author->getEntity() )
        );

        return $this;
    }
}
