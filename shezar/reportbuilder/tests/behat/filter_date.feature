@totara @totara_reportbuilder
Feature: Use the reportbuilder date filter
  To filter report data
  by date
  I need to use date filter

  @javascript
  Scenario: Reportbuilder date filter validation
    Given I am on a totara site
    And I log in as "admin"
    And I navigate to "Manage reports" node in "Site administration > Reports > Report builder"
    And I set the field "Report Name" to "Test user report"
    And I set the field "Source" to "User"
    And I press "Create report"
    And I switch to "Filters" tab
    And I select "User Last Login" from the "newstandardfilter" singleselect
    And I press "Save changes"
    And I follow "View This Report"

    When I set the field "user-lastlogindaysbeforechkbox" to "1"
    And I set the field "user-lastlogindaysbefore" to "1"
    And I set the field "user-lastlogindaysafterchkbox" to "0"
    And I click on "Search" "button" in the ".fitem_actionbuttons" "css_element"
    Then the field "user-lastlogindaysbeforechkbox" matches value "1"
    And the field "user-lastlogindaysbefore" matches value "1"
    And the field "user-lastlogindaysafterchkbox" matches value "0"

    When I set the field "user-lastlogindaysbeforechkbox" to "0"
    And I set the field "user-lastlogindaysafterchkbox" to "1"
    And I set the field "user-lastlogindaysafter" to "1"
    And I click on "Search" "button" in the ".fitem_actionbuttons" "css_element"
    Then the field "user-lastlogindaysbeforechkbox" matches value "0"
    And the field "user-lastlogindaysafterchkbox" matches value "1"
    And the field "user-lastlogindaysafter" matches value "1"

    When I set the field "user-lastlogindaysbeforechkbox" to "1"
    And I set the field "user-lastlogindaysbefore" to "2"
    And I set the field "user-lastlogindaysafterchkbox" to "1"
    And I set the field "user-lastlogindaysafter" to "3"
    And I click on "Search" "button" in the ".fitem_actionbuttons" "css_element"
    Then the field "user-lastlogindaysbeforechkbox" matches value "1"
    And the field "user-lastlogindaysbefore" matches value "2"
    And the field "user-lastlogindaysafterchkbox" matches value "1"
    And the field "user-lastlogindaysafter" matches value "3"

    When I set the field "user-lastlogindaysbeforechkbox" to "1"
    And I set the field "user-lastlogindaysbefore" to "-2"
    And I set the field "user-lastlogindaysafterchkbox" to "1"
    And I set the field "user-lastlogindaysafter" to "3"
    And I click on "Search" "button" in the ".fitem_actionbuttons" "css_element"
    Then the field "user-lastlogindaysbeforechkbox" matches value "0"
    And the field "user-lastlogindaysafterchkbox" matches value "1"
    And the field "user-lastlogindaysafter" matches value "3"

    When I set the field "user-lastlogindaysbeforechkbox" to "1"
    And I set the field "user-lastlogindaysbefore" to "2"
    And I set the field "user-lastlogindaysafterchkbox" to "1"
    And I set the field "user-lastlogindaysafter" to "-3"
    And I click on "Search" "button" in the ".fitem_actionbuttons" "css_element"
    Then the field "user-lastlogindaysbeforechkbox" matches value "1"
    And the field "user-lastlogindaysbefore" matches value "2"
    And the field "user-lastlogindaysafterchkbox" matches value "0"

    When I set the field "user-lastlogindaysbeforechkbox" to "1"
    And I set the field "user-lastlogindaysbefore" to "0"
    And I set the field "user-lastlogindaysafterchkbox" to "1"
    And I set the field "user-lastlogindaysafter" to "0"
    And I click on "Search" "button" in the ".fitem_actionbuttons" "css_element"
    Then the field "user-lastlogindaysbeforechkbox" matches value "0"
    And the field "user-lastlogindaysafterchkbox" matches value "0"

    When I set the field "user-lastlogindaysbeforechkbox" to "1"
    And I set the field "user-lastlogindaysbefore" to ""
    And I set the field "user-lastlogindaysafterchkbox" to "1"
    And I set the field "user-lastlogindaysafter" to ""
    And I click on "Search" "button" in the ".fitem_actionbuttons" "css_element"
    Then the field "user-lastlogindaysbeforechkbox" matches value "0"
    And the field "user-lastlogindaysafterchkbox" matches value "0"

    When I set the field "user-lastlogindaysbeforechkbox" to "1"
    And I set the field "user-lastlogindaysbefore" to "aa"
    And I set the field "user-lastlogindaysafterchkbox" to "1"
    And I set the field "user-lastlogindaysafter" to "bb"
    And I click on "Search" "button" in the ".fitem_actionbuttons" "css_element"
    Then the field "user-lastlogindaysbeforechkbox" matches value "0"
    And the field "user-lastlogindaysafterchkbox" matches value "0"

    When I set the field "user-lastlogindaysbeforechkbox" to "0"
    And I set the field "user-lastlogindaysafterchkbox" to "0"
    And I set the field "user-lastlogin_sck" to "1"
    And I set the field "user-lastlogin_eck" to "1"
    And I set the field "user-lastlogindaysbeforechkbox" to "1"
    And I set the field "user-lastlogindaysbefore" to "1"
    And I click on "Search" "button" in the ".fitem_actionbuttons" "css_element"
    Then the field "user-lastlogindaysbeforechkbox" matches value "1"
    And the field "user-lastlogindaysbefore" matches value "1"
    And the field "user-lastlogin_sck" matches value "0"
    And the field "user-lastlogin_eck" matches value "0"

    When I set the field "user-lastlogindaysbeforechkbox" to "0"
    And I set the field "user-lastlogindaysafterchkbox" to "0"
    And I set the field "user-lastlogin_sck" to "1"
    And I set the field "user-lastlogin_eck" to "1"
    And I set the field "user-lastlogindaysafterchkbox" to "1"
    And I set the field "user-lastlogindaysafter" to "1"
    And I click on "Search" "button" in the ".fitem_actionbuttons" "css_element"
    Then the field "user-lastlogindaysafterchkbox" matches value "1"
    And the field "user-lastlogindaysafter" matches value "1"
    And the field "user-lastlogin_sck" matches value "0"
    And the field "user-lastlogin_eck" matches value "0"
