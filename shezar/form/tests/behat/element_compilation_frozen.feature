@totara @totara_form
Feature: Totara form frozen element compilation tests
  In order to test a compilation of frozen elements
  As an admin
  I use the test form to confirm behaviour

  Background:
    Given I log in as "admin"
    And I navigate to the Totara test form
    And I should see "Form acceptance testing page"

  Scenario: Test a compilation of frozen elements in a Totara form with JavaScript disabled
    When I select "Compilation of frozen elements [totara_form\form\testform\element_compilation_frozen]" from the "Test form" singleselect
    Then I should see "Form: Compilation of frozen elements"
    And I should see "Static HTML test"

    When I press "Save changes"
    Then I should see "The form has been submit"
    And "checkbox" row "Value" column of "form_results" table should contain "checked"
    And "checkboxes" row "Value" column of "form_results" table should contain "[ '1' , '-1' ]"
    And "datetime_tz" row "Value" column of "form_results" table should contain "1457346240"
    And "datetime" row "Value" column of "form_results" table should contain "1457346240"
    And "editor" row "Value" column of "form_results" table should contain "<div><h2>Title</h2><p>Some random text, Some random text<br />Some random text, Some random text</p></div>"
    And "email" row "Value" column of "form_results" table should contain "admin@example.com"
    And "hidden" row "Value" column of "form_results" table should contain "Invisible"
    And "multiselect" row "Value" column of "form_results" table should contain "[ 'orange' , 'green' ]"
    And "number" row "Value" column of "form_results" table should contain "73.48"
    And "passwordunmask" row "Value" column of "form_results" table should contain "Secr3t!"
    And "radios" row "Value" column of "form_results" table should contain "--null--"
    And "select" row "Value" column of "form_results" table should contain "orange"
    And "tel" row "Value" column of "form_results" table should contain "+202-555-0174"
    And "text" row "Value" column of "form_results" table should contain "Totara 9.0"
    And "textarea" row "Value" column of "form_results" table should contain "Some random text, Some random text, Some random text, Some random text"
    And "url" row "Value" column of "form_results" table should contain "https://www.totaralms.com"
    And "yesno" row "Value" column of "form_results" table should contain "1"

    And "checkbox" row "Post data" column of "form_results" table should contain "No post data"
    And "checkboxes" row "Post data" column of "form_results" table should contain "No post data"
    And "datetime_tz" row "Post data" column of "form_results" table should contain "No post data"
    And "datetime" row "Post data" column of "form_results" table should contain "No post data"
    And "editor" row "Post data" column of "form_results" table should contain "No post data"
    And "email" row "Post data" column of "form_results" table should contain "No post data"
    And "hidden" row "Post data" column of "form_results" table should contain "Data present, type string"
    And "multiselect" row "Post data" column of "form_results" table should contain "No post data"
    And "number" row "Post data" column of "form_results" table should contain "No post data"
    And "passwordunmask" row "Post data" column of "form_results" table should contain "No post data"
    And "radios" row "Post data" column of "form_results" table should contain "No post data"
    And "select" row "Post data" column of "form_results" table should contain "No post data"
    And "tel" row "Post data" column of "form_results" table should contain "No post data"
    And "text" row "Post data" column of "form_results" table should contain "No post data"
    And "textarea" row "Post data" column of "form_results" table should contain "No post data"
    And "url" row "Post data" column of "form_results" table should contain "No post data"
    And "yesno" row "Post data" column of "form_results" table should contain "No post data"

    And "checkbox_novalue" row "Value" column of "form_results" table should contain "--null--"
    And "checkboxes_novalue" row "Value" column of "form_results" table should contain "--null--"
    And "datetime_tz_novalue" row "Value" column of "form_results" table should contain "--null--"
    And "datetime_novalue" row "Value" column of "form_results" table should contain "--null--"
    And "editor_novalue" row "Value" column of "form_results" table should contain "--null--"
    And "email_novalue" row "Value" column of "form_results" table should contain "--null--"
    And "hidden_novalue" row "Value" column of "form_results" table should contain "--null--"
    And "multiselect_novalue" row "Value" column of "form_results" table should contain "--null--"
    And "number_novalue" row "Value" column of "form_results" table should contain "--null--"
    And "passwordunmask_novalue" row "Value" column of "form_results" table should contain "--null--"
    And "radios_novalue" row "Value" column of "form_results" table should contain "--null--"
    And "select_novalue" row "Value" column of "form_results" table should contain "--null--"
    And "tel_novalue" row "Value" column of "form_results" table should contain "--null--"
    And "text_novalue" row "Value" column of "form_results" table should contain "--null--"
    And "textarea_novalue" row "Value" column of "form_results" table should contain "--null--"
    And "url_novalue" row "Value" column of "form_results" table should contain "--null--"
    And "yesno_novalue" row "Value" column of "form_results" table should contain "--null--"

    And "checkbox_novalue" row "Post data" column of "form_results" table should contain "No post data"
    And "checkboxes_novalue" row "Post data" column of "form_results" table should contain "No post data"
    And "datetime_tz_novalue" row "Post data" column of "form_results" table should contain "No post data"
    And "datetime_novalue" row "Post data" column of "form_results" table should contain "No post data"
    And "editor_novalue" row "Post data" column of "form_results" table should contain "No post data"
    And "email_novalue" row "Post data" column of "form_results" table should contain "No post data"
    And "hidden_novalue" row "Post data" column of "form_results" table should contain "Provided but empty"
    And "multiselect_novalue" row "Post data" column of "form_results" table should contain "No post data"
    And "number_novalue" row "Post data" column of "form_results" table should contain "No post data"
    And "passwordunmask_novalue" row "Post data" column of "form_results" table should contain "No post data"
    And "radios_novalue" row "Post data" column of "form_results" table should contain "No post data"
    And "select_novalue" row "Post data" column of "form_results" table should contain "No post data"
    And "tel_novalue" row "Post data" column of "form_results" table should contain "No post data"
    And "text_novalue" row "Post data" column of "form_results" table should contain "No post data"
    And "textarea_novalue" row "Post data" column of "form_results" table should contain "No post data"
    And "url_novalue" row "Post data" column of "form_results" table should contain "No post data"
    And "yesno_novalue" row "Post data" column of "form_results" table should contain "No post data"

    And "form_select" row "Value" column of "form_results" table should contain "totara_form\form\testform\element_compilation_frozen"
    And "submitbutton" row "Value" column of "form_results" table should contain "1"
    And "filemanager" row "Value" column of "form_results" table should contain "/, /bonus.txt"
    And "filepicker" row "Value" column of "form_results" table should contain ""
    And "filepicker_novalue" row "Value" column of "form_results" table should contain ""
    And "filepicker_novalue" row "Value" column of "form_results" table should contain ""

  @javascript
  Scenario: Test a compilation of elements in a Totara form with JavaScript enabled
    When I select "Compilation of frozen elements [totara_form\form\testform\element_compilation_frozen]" from the "Test form" singleselect
    Then I should see "Form: Compilation of frozen elements"
    And I should see "Static HTML test"

    When I press "Save changes"
    Then I should see "The form has been submit"
    And "checkbox" row "Value" column of "form_results" table should contain "checked"
    And "checkboxes" row "Value" column of "form_results" table should contain "[ '1' , '-1' ]"
    And "datetime_tz" row "Value" column of "form_results" table should contain "1457346240"
    And "datetime" row "Value" column of "form_results" table should contain "1457346240"
    And "editor" row "Value" column of "form_results" table should contain "<div><h2>Title</h2><p>Some random text, Some random text<br />Some random text, Some random text</p></div>"
    And "email" row "Value" column of "form_results" table should contain "admin@example.com"
    And "hidden" row "Value" column of "form_results" table should contain "Invisible"
    And "multiselect" row "Value" column of "form_results" table should contain "[ 'orange' , 'green' ]"
    And "number" row "Value" column of "form_results" table should contain "73.48"
    And "passwordunmask" row "Value" column of "form_results" table should contain "Secr3t!"
    And "radios" row "Value" column of "form_results" table should contain "--null--"
    And "select" row "Value" column of "form_results" table should contain "orange"
    And "tel" row "Value" column of "form_results" table should contain "+202-555-0174"
    And "text" row "Value" column of "form_results" table should contain "Totara 9.0"
    And "textarea" row "Value" column of "form_results" table should contain "Some random text, Some random text, Some random text, Some random text"
    And "url" row "Value" column of "form_results" table should contain "https://www.totaralms.com"
    And "yesno" row "Value" column of "form_results" table should contain "1"

    And "checkbox" row "Post data" column of "form_results" table should contain "No post data"
    And "checkboxes" row "Post data" column of "form_results" table should contain "No post data"
    And "datetime_tz" row "Post data" column of "form_results" table should contain "No post data"
    And "datetime" row "Post data" column of "form_results" table should contain "No post data"
    And "editor" row "Post data" column of "form_results" table should contain "No post data"
    And "email" row "Post data" column of "form_results" table should contain "No post data"
    And "hidden" row "Post data" column of "form_results" table should contain "Data present, type string"
    And "multiselect" row "Post data" column of "form_results" table should contain "No post data"
    And "number" row "Post data" column of "form_results" table should contain "No post data"
    And "passwordunmask" row "Post data" column of "form_results" table should contain "No post data"
    And "radios" row "Post data" column of "form_results" table should contain "No post data"
    And "select" row "Post data" column of "form_results" table should contain "No post data"
    And "tel" row "Post data" column of "form_results" table should contain "No post data"
    And "text" row "Post data" column of "form_results" table should contain "No post data"
    And "textarea" row "Post data" column of "form_results" table should contain "No post data"
    And "url" row "Post data" column of "form_results" table should contain "No post data"
    And "yesno" row "Post data" column of "form_results" table should contain "No post data"

    And "checkbox_novalue" row "Value" column of "form_results" table should contain "--null--"
    And "checkboxes_novalue" row "Value" column of "form_results" table should contain "--null--"
    And "datetime_tz_novalue" row "Value" column of "form_results" table should contain "--null--"
    And "datetime_novalue" row "Value" column of "form_results" table should contain "--null--"
    And "editor_novalue" row "Value" column of "form_results" table should contain "--null--"
    And "email_novalue" row "Value" column of "form_results" table should contain "--null--"
    And "hidden_novalue" row "Value" column of "form_results" table should contain "--null--"
    And "multiselect_novalue" row "Value" column of "form_results" table should contain "--null--"
    And "number_novalue" row "Value" column of "form_results" table should contain "--null--"
    And "passwordunmask_novalue" row "Value" column of "form_results" table should contain "--null--"
    And "radios_novalue" row "Value" column of "form_results" table should contain "--null--"
    And "select_novalue" row "Value" column of "form_results" table should contain "--null--"
    And "tel_novalue" row "Value" column of "form_results" table should contain "--null--"
    And "text_novalue" row "Value" column of "form_results" table should contain "--null--"
    And "textarea_novalue" row "Value" column of "form_results" table should contain "--null--"
    And "url_novalue" row "Value" column of "form_results" table should contain "--null--"
    And "yesno_novalue" row "Value" column of "form_results" table should contain "--null--"

    And "checkbox_novalue" row "Post data" column of "form_results" table should contain "No post data"
    And "checkboxes_novalue" row "Post data" column of "form_results" table should contain "No post data"
    And "datetime_tz_novalue" row "Post data" column of "form_results" table should contain "No post data"
    And "datetime_novalue" row "Post data" column of "form_results" table should contain "No post data"
    And "editor_novalue" row "Post data" column of "form_results" table should contain "No post data"
    And "email_novalue" row "Post data" column of "form_results" table should contain "No post data"
    And "hidden_novalue" row "Post data" column of "form_results" table should contain "Provided but empty"
    And "multiselect_novalue" row "Post data" column of "form_results" table should contain "No post data"
    And "number_novalue" row "Post data" column of "form_results" table should contain "No post data"
    And "passwordunmask_novalue" row "Post data" column of "form_results" table should contain "No post data"
    And "radios_novalue" row "Post data" column of "form_results" table should contain "No post data"
    And "select_novalue" row "Post data" column of "form_results" table should contain "No post data"
    And "tel_novalue" row "Post data" column of "form_results" table should contain "No post data"
    And "text_novalue" row "Post data" column of "form_results" table should contain "No post data"
    And "textarea_novalue" row "Post data" column of "form_results" table should contain "No post data"
    And "url_novalue" row "Post data" column of "form_results" table should contain "No post data"
    And "yesno_novalue" row "Post data" column of "form_results" table should contain "No post data"

    And "form_select" row "Value" column of "form_results" table should contain "totara_form\form\testform\element_compilation_frozen"
    And "submitbutton" row "Value" column of "form_results" table should contain "1"
    And "filemanager" row "Value" column of "form_results" table should contain "/, /bonus.txt"
    And "filepicker" row "Value" column of "form_results" table should contain ""
    And "filepicker_novalue" row "Value" column of "form_results" table should contain ""
    And "filepicker_novalue" row "Value" column of "form_results" table should contain ""
