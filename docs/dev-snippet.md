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

````php
const string CSRF_TOKEN_ID  = 'something';
...
'csrfTokenFieldName'    => static::CSRF_TOKEN_PARAM_NAME,
'csrfToken'             => $this->csrfTokenManager->getToken(static::CSRF_TOKEN_ID)->getValue()
````

````html
<input type="hidden" name="{{ csrfTokenFieldName }}" value="{{ csrfToken }}">
````

````php
$this->validateCsrfToken();
````


## 🪧 Chalkboard message

````html
{% embed 'parts/alert-chalkboard.html.twig' %}
    {% block alertTitle %}TITLE{% endblock %}
    {% block alertBody %}CONTENT{% endblock %}
{% endembed %}
````



## Editor

````html
{% if Article.currentUserCanEdit %}
{% endif %}
````
