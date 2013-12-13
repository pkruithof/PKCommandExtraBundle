Description
-----------
This bundle adds some sugar to the default [ContainerAwareCommand](https://github.com/symfony/symfony/blob/master/src/Symfony/Bundle/FrameworkBundle/Command/ContainerAwareCommand.php). It provides methods to make your command single-processed, turn off logging, and more.

Installation
------------
Installation for Symfony 2.1 (and up) via [composer](http://getcomposer.org):

Add the following to your composer.json:
```json
{
    "require": {
        "pk/command-extra-bundle": "1.*"
    }
}
```

Update dependency:

```bash
composer.phar update pk/command-extra-bundle
```

Register bundle:

```php
// app/AppKernel.php
public function registerBundles()
{
    $bundles = array(
        // ...
        new PK\CommandExtraBundle\PKCommandExtraBundle(),
    );
    // ...
}
```

Usage
-----
The bundle mainly provides a class that you can extend your commands with. It's located at `PK\CommandExtraBundle\Command\Command` and it extends the [ContainerAwareCommand](https://github.com/symfony/symfony/blob/master/src/Symfony/Bundle/FrameworkBundle/Command/ContainerAwareCommand.php) provided by Symfony's framework bundle.

By extending the class you can call three methods in the configure method:

* `isSingleProcessed()`: This sets a flag indicating that this command can only run one at a time. Useful when you're starting the command with a cronjob, preventing multiple running instances. Requires the [posix](http://php.net/manual/en/book.posix.php) extension loaded.
* `disableLoggers()`: By default, a lot of information is being logged. If you don't need this information, or want your command to consume less memory (this depends on your setup of course, YMMV), this turns off logging for Doctrine and the default logger service.
* `setSummarizeDefinition()`: For now, only the time and memory consumed are logged at the beginning and end of the command. This method controls which of these are logged. Both are logged by default.

Example:

```php
protected function configure()
{
    $this
        ->setDefinition(array())
        ->setName('foo:bar')
        ->isSingleProcessed()
        ->disableLoggers()
        ->setSummarizeDefinition(array('memory' => false))
    ;
}
```

For tips and/or feature requests, please don't hesitate to [add an issue](https://github.com/pkruithof/PKCommandExtraBundle/issues/new).
