<?php
namespace App\Service\Cms;

use App\Entity\Cms\Visit as VisitEntity;
use App\Entity\PhpBB\User as UserEntity;
use App\Service\Factory;
use App\Service\User;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Jaybizzle\CrawlerDetect\CrawlerDetect;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use TurboLabIt\BaseCommand\Traits\EnvTrait;


class Visit
{
    use EnvTrait;

    protected Request $request;

    public function __construct(
        RequestStack $requestStack,
        protected EntityManagerInterface $em, ParameterBagInterface $parameters,
        protected Factory $factory
    )
    {
        $this->request = $requestStack->getCurrentRequest();
        $this->parameters = $parameters;
    }


    public function isCountable() : bool
    {
        $ipAddress = $this->request->getClientIp();

        return
            !empty($ipAddress) &&
            $ipAddress != '127.0.0.1' &&
            !(new CrawlerDetect())->isCrawler();
    }


    public function visit(Article|Tag|File $oCms, ?User $user) : array
    {
        $arrDefaultResponse = [
            'views'     => $oCms->getViews(false),
            'comments'  => method_exists($oCms, 'getCommentsNum') ? $oCms->getCommentsNum(false) : null,
            'new'       => 0,
        ];

        if( !$this->isCountable() || !$oCms->isVisitable() ) {
            return $arrDefaultResponse;
        }

        if( !empty($user) ) {
            $user = $user->getEntity();
        }

        //
        $visitEntity =
            $this->em->getRepository(VisitEntity::class)
                ->getOrNewByVisitLogic($user, $oCms->getEntity(), $this->request->getClientIp());

        $isNew = empty($visitEntity->getId());
        $visitDate = $visitEntity->getUpdatedAt();

        if($isNew) {

            $this->em->persist($visitEntity);
            $visitEntity->setUpdatedAt(new DateTime());
            $this->em->flush();
        }

        if( empty($visitDate) || $visitDate < (new DateTime())->modify('-24 hours') ) {

            if( !$isNew ) {

                $visitEntity->setUpdatedAt(new DateTime());
                $this->em->flush();
            }

            $oCms->countOneVisit();

            return
                array_merge($arrDefaultResponse, [
                    'views' => $oCms->getViews(false),
                    'new'   => 1,
                ]);
        }

        return $arrDefaultResponse;
    }
}
