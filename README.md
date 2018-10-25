# creemedia eZ contentbird Bundle

creemedia eZ contentbird Bundle is an eZPlatform bundle to connect contentbird with eZ Platform.

This bundle allows you to synchronize you Text written on [contentbird](http://contentbird.io/) with your eZ Platform project.

In this first version it is possible to push text to eZ platform and to pull changed content into contentbird.

### Register the bundle

Activate the bundle in `app\AppKernel.php` file.

```php
// app\AppKernel.php

public function registerBundles()
{
   ...
   $bundles = array(
       new FrameworkBundle(),
       ...
       new creemedia\Bundle\eZcontentbirdBundle\eZcontentbirdBundle(),
   );
   ...
}
```

### Register routes

```yml

# app/config/routing.yml

creemedia.contentbird:
    resource: "@eZcontentbirdBundle/Resources/config/routing.yml"

```
### Setup your credentials

```yaml
contentbird:
    token: "your-token"
```

### Fix autoload Problem

run ```composer dumpautoload -a```

License
-------

[License](LICENSE)
