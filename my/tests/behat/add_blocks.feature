@core @core_my
Feature: Add blocks to dashboard page
  In order to add more functionality to dashboard page
  As a user
  I need to add blocks to dashboard page

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | student1 | Student | 1 | student1@example.com |
      | student2 | Student | 2 | student2@example.com |
    And the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1 | topics |
    And the following "course enrolments" exist:
      | user | course | role |
      | student1 | C1 | student |
      | student2 | C1 | student |
    And I log in as "student1"
    And I click on "Dashboard" in the totara menu

  Scenario: Add blocks to page
    When I press "Customise this page"
    # Totara - we already have "Latest news" on Dashboard
    And I add the "Calendar" block
    And I add the "Latest badges" block
    Then I should see "Latest news"
    And I should see "Latest badges"
    And I should see "Calendar" in the "Calendar" "block"
    And I should see "Upcoming events" in the "Upcoming events" "block"
