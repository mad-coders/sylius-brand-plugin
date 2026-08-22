# 0005 - Service wiring in XML, modular by concern

**Status:** accepted

## Context

The plugin wires a fair number of services: the settings provider, the brand resolver and
synchronizer, repositories, form types, a Twig extension, event listeners, a console command,
controllers, fixtures and Behat contexts. Autowiring everything from a single file makes service ids
unstable and hides the tags (`kernel.event_listener`, `sylius.grid`, `twig.extension`, ...) that the
plugin depends on.

## Decision

Services are declared **explicitly in XML**, split by concern under `config/services/`, and pulled
in by a glob from `config/services.xml` (an XML entry point rather than a PHP one: Symfony's
`PhpFileLoader` cannot resolve XML imports on its own):

```
config/services/
├── commands.xml
├── controllers.xml
├── fixtures.xml
├── forms.xml
├── listeners.xml
├── menu.xml
├── providers.xml
├── resolvers.xml
└── twig.xml
```

Service ids are prefixed `madcoders_sylius_brand.<concern>.<name>`, mirroring Sylius' own naming so
host applications can decorate or replace them predictably. Each service that has an interface also
gets an interface-named alias, so host code can autowire it.

## Consequences

- Tags and priorities are visible in one place per concern.
- Service ids are part of the public API of the plugin and are covered by semantic versioning.
- Adding a service means editing the XML file for its concern, not a catch-all.

## Rules

1. New service → declare it in the XML file for its concern with an explicit id and arguments.
2. Public ids only where the host application or Behat genuinely needs them.
3. Keep the business logic in the service; keep framework wiring (event listeners, controllers, Twig
   extensions) in thin classes that delegate to it.
