<?php
namespace App\Trait;

trait CommandTrait
{
    protected function buildItemTitle($key, $item) : string
    {
        if( is_string($item) ) {
            return parent::buildItemTitle(null, $item);
        }


        if( is_object($item) ) {
            return parent::buildItemTitle($item->getId(), $item);
        }


        if( is_array($item) ) {
            return '[' . $item['articleId'] . ']';
        }
    }


    protected function loadAllArticles() : int
    {
        $this
            ->fxTitle("🚚 Loading all articles from the database...")
            ->articles->loadAll();

        $articlesNum = $this->articles->count();
        $this->fxOK("##$articlesNum## article(s) loaded");

        return $articlesNum;
    }


    protected function loadAllFiles() : int
    {
        $this
            ->fxTitle("🚚 Loading all files from the database...")
            ->files->loadAll();

        $filesNum = $this->files->count();
        $this->fxOK("##$filesNum## file(s) loaded");

        return $filesNum;
    }
}
