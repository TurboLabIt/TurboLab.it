<?php
namespace App\Service\Cms;

use App\Entity\Cms\ArticleTag;
use App\Entity\Cms\Tag as TagEntity;
use App\Service\Factory;
use App\Service\TextProcessor;


class TagEditor extends Tag
{
    public function __construct(Factory $factory, protected TextProcessor $textProcessor)
    {
        parent::__construct($factory);
    }


    //<editor-fold defaultstate="collapsed" desc="*** 📜 Title ***">
    public function setTitle(string $newTitle) : static
    {
        $cleanTitle = $this->textProcessor->processRawInputTitleForStorage($newTitle);
        $cleanTitle = mb_strtolower($cleanTitle);
        $this->entity->setTitle($cleanTitle);
        return $this;
    }
    //</editor-fold>

    //<editor-fold defaultstate="collapsed" desc="*** ♻️ Replacement ***">
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
    //</editor-fold>

    //<editor-fold defaultstate="collapsed" desc="*** 💾 Save ***">
    public function save(bool $persist = true) : static
    {
        if($persist) {

            $this->factory->getEntityManager()->persist($this->entity);
            $this->factory->getEntityManager()->flush();
        }

        return $this;
    }
    //</editor-fold>
}
