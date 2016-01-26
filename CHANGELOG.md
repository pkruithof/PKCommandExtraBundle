Changelog
=========

This changelog mostly documents breaking changes and deprecations.
For a complete list of releases, see the [releases page][0].

[0]: https://github.com/pkruithof/PKCommandExtraBundle/releases

## v2.0.0

### Changes:

* Added CS config
* Changed `symfony/framework-bundle` version constraint to be compatible with Symfony 3.
* Applied CS fixes and formatting
* Added phpdoc where missing
* Removed unused `services.xml`
* Moved inherited methods to top


### Breaking changes:

* Upgraded required PHP version to 5.6
* Added dependencies (used in code but wasn't required in composer yet):
 - `doctrine/doctrine-bundle` (1.5)
 - `monolog/monolog` (1.7)
* Changed visibility of most public methods to protected
* Removed deprecated `preventLogging` method. Use `disableLoggers` instead.
* Renamed method `getEntityManager` to `getDoctrineManager`, (can't assume `EntityManager`)
