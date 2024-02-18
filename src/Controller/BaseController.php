<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;


abstract class BaseController extends AbstractController
{
    const CACHE_DEFAULT_EXPIRY = 60 * 5;
    protected Request $request;


    protected function isCachable() : bool
    {
        $isLogged           = !empty($this->getUser() ?? null);
        $currentUserIp      = $this->request->getClientIp();
        $currentUserIsLocal =
            !filter_var(
                $currentUserIp, FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            );

        $currentEnv = $this->parameterBag->get("kernel.environment");
        $isDevEnv   = in_array($currentEnv, ["dev", "test"]);

        $result = !$isLogged && !$currentUserIsLocal && !$isDevEnv;
        return $result;
    }
}
