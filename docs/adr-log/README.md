# Architectural decision log

Load-bearing decisions for this plugin, each with its context, the decision itself, and the rules
it puts on future changes. Read the relevant ADR **before** changing the area it governs, and don't
introduce a second way of doing the same thing.

New decision? Copy the next number and follow the same shape (Status / Context / Decision /
Consequences / Rules).

| # | Decision |
|---|---|
| [0001](0001-sylius-plugin-resource-model.md) | Built on the Sylius resource model |
| [0002](0002-doctrine-xml-mapped-superclasses.md) | Doctrine mapping as XML mapped superclasses |
| [0003](0003-configuration-through-the-settings-plugin.md) | Configuration through MonsieurBiz Settings |
| [0004](0004-brand-resolution-from-a-product-attribute.md) | Brand resolved from a product attribute, denormalised onto the product |
| [0005](0005-service-wiring.md) | Service wiring in XML, modular by concern |
| [0006](0006-quality-tooling.md) | Quality tooling: PHPStan, ECS, Rector, PHPUnit, Behat via Make |
| [0007](0007-conventional-commits.md) | Conventional Commits and the trunkless `1.0` branch model |
| [0008](0008-display-toggles-per-brand.md) | Display toggles live on the brand, not in configuration |
