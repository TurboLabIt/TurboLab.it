<?php
namespace App\ServiceCollection\Cms;

use App\Entity\PhpBB\User as UserEntity;
use App\Service\User as UserService;


class ArticleAuthorCollection extends BaseArticleCollection
{
    protected UserService $author;


    public function setAuthor(UserEntity|UserService $user) : static
    {
        $this->author = $user instanceof UserService ? $user : $this->factory->createUser($user);
        return $this;
    }


    public function loadDrafts() : static
    {
        $userEntity = $this->author->getEntity();
        $paginator = $this->getRepository()->findDraftsByAuthor($userEntity) ?? [];
        return $this->setEntities($paginator);
    }


    public function loadInReview() : static
    {
        $userEntity = $this->author->getEntity();
        $paginator = $this->getRepository()->findInReviewByAuthor($userEntity) ?? [];
        return $this->setEntities($paginator);
    }


    public function loadPublished(?int $page = 1) : static
    {
        $userEntity = $this->author->getEntity();
        $paginator = $this->getRepository()->findByAuthor($userEntity, $page) ?? [];
        return $this->setEntities($paginator);
    }


    public function loadUpcoming() : static
    {
        $userEntity = $this->author->getEntity();
        $paginator = $this->getRepository()->findUpcomingByAuthor($userEntity) ?? [];
        return $this->setEntities($paginator);
    }


    public function loadKo() : static
    {
        $userEntity = $this->author->getEntity();
        $paginator = $this->getRepository()->findKoByAuthor($userEntity) ?? [];
        return $this->setEntities($paginator);
    }
}
