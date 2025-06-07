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
