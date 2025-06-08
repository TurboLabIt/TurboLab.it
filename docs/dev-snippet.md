# [Dev snippet](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/dev-snippet.md)


## 👤 Current user

````php
$currentUser = $this->factory->getCurrentUser();
````

````html
{% if is_granted('IS_AUTHENTICATED_FULLY') %}
    <p>Welcome back, {{ app.user.username }}!</p>
{% else %}
    <p>Please log in to access this feature.</p>
{% endif %}
````


## 🥷 CSRF

````html
<input type="hidden" name="_csrf_token"
       value="{{ csrf_token(constant('App\\Controller\\MyController::CSRF_TOKEN_ID')) }}">
````

````php
#[Route(... methods: ['POST'])]
public function submit(Request $request, CsrfTokenManagerInterface $csrfTokenManager) : Response
{
    $currentUser = $this->factory->getCurrentUser();
    if( empty($currentUser) ) {
        throw $this->createAccessDeniedException('Non sei loggato!');
    }

    $csrfToken  = $request->request->get('_csrf_token');
    $oToken     = new CsrfToken(static::CSRF_TOKEN_ID, $csrfToken);
    $tokenCheck = $csrfTokenManager->isTokenValid($oToken);

    if( !$tokenCheck ) {
        throw $this->createAccessDeniedException('Verifica di sicurezza CSRF fallita. Prova di nuovo');
    }

    ...
}
````
