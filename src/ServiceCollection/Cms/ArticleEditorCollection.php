<?php
namespace App\ServiceCollection\Cms;

use App\Entity\Cms\Article as ArticleEntity;
use App\Service\Cms\ArticleEditor;


class ArticleEditorCollection extends BaseArticleCollection
{
    public function loadCommentsTopicNeedsUpdate() : static
    {
        $arrArticles = $this->getRepository()->findCommentsTopicNeedsUpdate();
        return $this->setEntities($arrArticles);
    }


    public function createService(?ArticleEntity $entity = null) : ArticleEditor { return $this->factory->createArticleEditor($entity); }
}
