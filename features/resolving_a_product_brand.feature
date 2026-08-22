@madcoders_brand_resolution
Feature: Resolving a product's brand from its attribute
    In order to reuse the brand my catalogue already carries
    As a Store Owner
    I want products to be attached to a brand through a product attribute

    Background:
        Given the store operates on a single channel in "United States"
        And the store has a product attribute "Brand" with code "brand"
        And brands are enabled in the settings
        And the brand attribute in the settings is "brand"

    @ui
    Scenario: A product whose attribute value matches a brand code
        Given the store has a brand "Adidas" with code "adidas"
        And the store has a product "Sandals" with the brand attribute "adidas"
        When the product brands are resynced
        Then the product "Sandals" should belong to the brand "adidas"

    @ui
    Scenario: Several attribute values mapped onto one brand
        Given the store has a brand "Nike" with code "nike"
        And the brand mapping maps "Nike Inc." to "nike"
        And the brand mapping maps "NIKE Sportswear" to "nike"
        And the store has a product "Running Shoes" with the brand attribute "Nike Inc."
        And the store has a product "Track Jacket" with the brand attribute "NIKE Sportswear"
        When the product brands are resynced
        Then the product "Running Shoes" should belong to the brand "nike"
        And the product "Track Jacket" should belong to the brand "nike"

    @ui
    Scenario: An attribute value that names no brand
        Given the store has a brand "Nike" with code "nike"
        And the store has a product "Mystery Item" with the brand attribute "a-brand-nobody-created"
        When the product brands are resynced
        Then the product "Mystery Item" should belong to no brand

    @ui
    Scenario: Resolution keeps working while the shop display is switched off
        Given the store has a brand "Adidas" with code "adidas"
        And the store has a product "Sandals" with the brand attribute "adidas"
        And brands are disabled in the settings
        When the product brands are resynced
        Then the product "Sandals" should belong to the brand "adidas"

    @ui
    Scenario: Nothing is resolved while no brand attribute is configured
        Given the store has a brand "Adidas" with code "adidas"
        And the store has a product "Sandals" with the brand attribute "adidas"
        And no brand attribute is configured in the settings
        When the product brands are resynced
        Then the product "Sandals" should belong to no brand
