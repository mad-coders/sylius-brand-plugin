@madcoders_brand_shop
Feature: Browsing brands in the shop
    In order to find the products of a manufacturer I trust
    As a Customer
    I want to browse brands and their products

    Background:
        Given the store operates on a single channel in "United States"
        And brands are enabled in the settings
        And the brand attribute in the settings is "brand"

    @ui
    Scenario: Seeing the brands on the overview page
        Given the store has a brand "Nike" with code "nike"
        And the store has a brand "Adidas" with code "adidas"
        When I browse the brand overview page
        Then I should see the brand "Nike"
        And I should see the brand "Adidas"

    @ui
    Scenario: A brand kept off the overview is not listed
        Given the store has a brand "Nike" with code "nike"
        And the brand "Nike" is not displayed on the brand overview
        When I browse the brand overview page
        Then I should not see the brand "Nike"

    @ui
    Scenario: A brand kept off the overview has no page of its own
        Given the store has a brand "Nike" with code "nike"
        And the brand "Nike" is not displayed on the brand overview
        When I try to open the page of the brand "Nike"
        Then I should be told that the page does not exist

    @ui
    Scenario: A disabled brand is not listed
        Given the store has a brand "Nike" with code "nike"
        And the brand "Nike" is disabled
        When I browse the brand overview page
        Then I should not see the brand "Nike"

    @ui
    Scenario: Seeing the products of a brand
        Given the store has a brand "Nike" with code "nike"
        And the store has a product "Running Shoes" of the brand "nike"
        And the store has a product "Sandals" of the brand "adidas"
        When I open the page of the brand "Nike"
        Then I should see the product "Running Shoes"
        And I should not see the product "Sandals"

    @ui
    Scenario: The brand pages are gone when the feature is turned off
        Given the store has a brand "Nike" with code "nike"
        And brands are disabled in the settings
        When I try to browse the brand overview page
        Then I should be told that the page does not exist

    @ui
    Scenario: Seeing the brand on the product tiles of a listing
        Given the store has a brand "Nike" with code "nike"
        And the brand "Nike" is displayed on product tiles
        And the store has a product "Running Shoes" of the brand "nike"
        When I open the page of the brand "Nike"
        Then the product tiles should show the brand "Nike"

    @ui
    Scenario: The tile toggle keeps the brand off the tiles
        Given the store has a brand "Nike" with code "nike"
        And the store has a product "Running Shoes" of the brand "nike"
        When I open the page of the brand "Nike"
        Then I should see the product "Running Shoes"
        And the product tiles should not show the brand "Nike"

    @ui
    Scenario: Seeing the brand strip on the homepage
        Given the store has a brand "Nike" with code "nike"
        And the brand "Nike" is displayed on the homepage
        And the store has a brand "Adidas" with code "adidas"
        When I browse the homepage
        Then the homepage should show the brand "Nike"
        And the homepage should not show the brand "Adidas"

    @ui
    Scenario: The homepage strip is gone when the feature is turned off
        Given the store has a brand "Nike" with code "nike"
        And the brand "Nike" is displayed on the homepage
        And brands are disabled in the settings
        When I browse the homepage
        Then the homepage should not show the brand "Nike"
