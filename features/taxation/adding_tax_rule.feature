@taxation @taxation_tax_rule
Feature: Adding a new Tax Rule
  I want to create a new tax rule

  Background:
    Given the site operates on a store in "Austria"
    And the site has a tax rate "AT" with "20%" rate
    And the site has a tax rule group "AT"
    And the tax rule group has a tax rule for country "Austria" with tax rate "AT"
    And the tax rule group is valid for store "Austria"
    Then there should be a tax rule group "AT" with "1" rule

  Scenario: Simple Tax Rule with 20%
    Given I am in country "Austria"
    Then it should add "4" to the price "20"

  Scenario: Tax calculator should be null when no stores are assigned
    Given the site has a country "Germany" with currency "EUR"
    And the site has a tax rate "DE" with "19%" rate
    And the site has a tax rule group "Europe"
    And the tax rule group has a tax rule for country "Germany" with tax rate "DE"
    Then the tax calculator should be null for tax rule group in country "Germany"

  Scenario: Tax calculator should return same price when country is not configured
    Given the site has a country "Germany" with currency "EUR"
    And the country "Germany" is active
    And the country "Germany" is valid for store "Austria"
    And I am in country "Germany"
    Then it should add "0" to the price "20"

  Scenario: Canadian PST/GST/HST VAT
    Given the site has a currency "Canadian Dollar" with iso "CAD"
    And the site has a country "Canada" with currency "CAD"
    And the country "Canada" is active
    And the country "Canada" is valid for store "Austria"
    And I am in country "Canada"
    And the site has a tax rate "GST" with "5%" rate
    And the site has a tax rate "PST-BC" with "7%" rate
    And the site has a tax rule group "CAD"
    And it has a tax rule for country "Canada" with tax rate "GST" and it combines all rules
    And it has a tax rule for country "Canada" with tax rate "PST-BC" and it combines all rules
    And the tax rule group is valid for store "Austria"
    And I am in country "Canada"
    Then it should add "120" to the price "1000"