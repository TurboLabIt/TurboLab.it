<?php
namespace App\Trait;

use App\ServiceCollection\Cms\ArticleCollection;
use App\ServiceCollection\Cms\FileCollection;
use InvalidArgumentException;

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

        throw new InvalidArgumentException("Unhandled type in CommandTrait::buildItemTitle");
    }


    protected function loadAllArticles(ArticleCollection $articles) : int
    {
        $this->fxTitle("🚚 Loading all articles from the database...");
        $articles->loadAll();

        $articlesNum = $articles->count();
        $this->fxOK("##$articlesNum## article(s) loaded");

        return $articlesNum;
    }


    protected function loadAllFiles(FileCollection $files) : int
    {
        $this->fxTitle("🚚 Loading all files from the database...");
        $files->loadAll();

        $filesNum = $files->count();
        $this->fxOK("##$filesNum## file(s) loaded");

        return $filesNum;
    }
}
