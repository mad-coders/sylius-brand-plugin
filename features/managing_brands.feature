@madcoders_brand_admin
Feature: Managing brands
    In order to present the manufacturers I sell
    As an Administrator
    I want to manage brands in my store

    Background:
        Given the store operates on a single channel in "United States"
        And I am logged in as an administrator

    @ui
    Scenario: Adding a brand
        Given I want to create a new brand
        When I specify its code as "nike"
        And I name it "Nike" in "English (United States)"
        And I add it
        Then I should be notified that it has been successfully created
        And the brand "Nike" should appear in the store

    @ui
    Scenario: A brand slug is generated from its name
        Given I want to create a new brand
        When I specify its code as "modern-wear"
        And I name it "Modern Wear" in "English (United States)"
        And I leave its slug empty
        And I add it
        Then I should be notified that it has been successfully created
        And the brand "Modern Wear" should have the slug "modern-wear"

    @ui
    Scenario: Trying to reuse a brand code
        Given the store has a brand "Nike" with code "nike"
        And I want to create a new brand
        When I specify its code as "nike"
        And I name it "Nike Again" in "English (United States)"
        And I try to add it
        Then I should be told that a brand with this code already exists
        And there should still be only 1 brand in the store

    @ui
    Scenario: Trying to reuse a slug in the same locale
        Given the store has a brand "Modern Wear" with code "modern-wear"
        And I want to create a new brand
        When I specify its code as "modern-wear-2"
        And I name it "Modern Wear" in "English (United States)"
        And I try to add it
        Then I should be told that a brand with this slug already exists
        And there should still be only 1 brand in the store

    @ui
    Scenario: Browsing brands
        Given the store has a brand "Nike" with code "nike"
        And the store has a brand "Adidas" with code "adidas"
        When I browse brands
        Then I should see 2 brands in the list
        And I should see the brand "Nike" in the list

    @ui
    Scenario: Disabling a brand
        Given the store has a brand "Nike" with code "nike"
        When I want to modify this brand
        And I disable it
        And I save my changes
        Then I should be notified that it has been successfully edited
        And the brand "Nike" should be disabled

    @ui
    Scenario: Seeing why a product resolved to its brand
        Given the store has a brand "Nike" with code "nike"
        And the store has a product attribute "Brand" with code "brand"
        And brands are enabled in the settings
        And the brand attribute in the settings is "brand"
        And the store has a product "Running Shoes" with the brand attribute "nike"
        When the product brands are resynced
        And I view the product "Running Shoes"
        Then I should see that it resolved to the brand "Nike"

    @ui
    Scenario: Seeing that a product resolved to nothing
        Given the store has a product attribute "Brand" with code "brand"
        And brands are enabled in the settings
        And the brand attribute in the settings is "brand"
        And the store has a product "Mystery Item" with the brand attribute "a-brand-nobody-created"
        When the product brands are resynced
        And I view the product "Mystery Item"
        Then I should see that it resolved to no brand

    @ui
    Scenario: Filtering the product grid by brand
        Given the store has a brand "Nike" with code "nike"
        And the store has a product "Running Shoes" of the brand "nike"
        When I browse products
        Then the product grid should show the brand "Nike"
