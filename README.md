# creemedia eZ contentbird Bundle

creemedia eZ contentbird Bundle is an eZPlatform bundle to connect contentbird with eZ Platform.

This bundle allows you to synchronize you Text written on [contentbird](http://contentbird.io/) with your eZ Platform project.

In this first version it is possible to push text to eZ platform and to pull changed contnet into contentbird.

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

### Setup your credentials

```yaml
contentbird:
    token: "your-token"
```

License
-------

[License](LICENSE)
