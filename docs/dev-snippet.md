# [Dev snippet](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/dev-snippet.md)


## 👤 Current user

````php

````

````html

````


## 🥷 CSRF

````php
const string CSRF_TOKEN_ID  = 'something';
...
'csrfTokenFieldName'    => static::CSRF_TOKEN_PARAM_NAME,
'csrfToken'             => $this->csrfTokenManager->getToken(static::CSRF_TOKEN_ID)->getValue()
````

* [ArticleNewController.php](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Controller/Editor/ArticleNewController.php)
* [🔍 Search](https://github.com/search?q=repo%3ATurboLabIt%2FTurboLab.it%20CSRF_TOKEN_ID&type=code)


````html
<input type="hidden" name="{{ csrfTokenFieldName }}" value="{{ csrfToken }}">
````

* [article/editor/new.html.twig](https://github.com/TurboLabIt/TurboLab.it/blob/main/templates/article/editor/new.html.twig)
* [🔍 Search](https://github.com/search?q=repo%3ATurboLabIt%2FTurboLab.it%20%7B%7B%20csrfToken%20%7D%7D&type=code)


````php
$this->validateCsrfToken();
````

* [ArticleNewController.php](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Controller/Editor/ArticleNewController.php)
* [🔍 Search](https://github.com/search?q=repo%3ATurboLabIt%2FTurboLab.it%20validateCsrfToken&type=code)


## 🪧 Chalkboard message

````html
{% embed 'parts/alert-chalkboard.html.twig' %}

    {% block alertTitle %}
       ....
    {% endblock %}

    {% block alertBodyStyle %}{% endblock %}
    {% block alertBody %}
        <div>...</div>
    {% endblock %}

{% endembed %}
````

* [article/index.html.twig](https://github.com/TurboLabIt/TurboLab.it/blob/main/templates/article/index.html.twig)
* [🔍 Search](https://github.com/search?q=repo%3ATurboLabIt%2FTurboLab.it%20alert-chalkboard.html.twig&type=code)


## Editor

````html

````


## Env check

````php
use EnvTrait;

public function __construct(ParameterBagInterface $parameters)
{
    $this->parameters = $parameters;
}

public function something()
{
    if( $this->isDev() ) {
      ...
    }
}
...
````


## Cached response

````php
$html =
    $this->cache->get($cacheKey, function(ItemInterface $cacheItem) use(...) {

        $cacheItem->expiresAfter(3600 * 24);

        return
            $this->renderView(..., [
                ...
            ]);
    });

return new Response($html);
````
