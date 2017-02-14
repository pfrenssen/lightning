@lightning @media @api @javascript @errors
Feature: Uploading media assets through the media browser

  @test_module
  Scenario: Uploading an image from within the media browser
    Given I am logged in as a user with the media_manager role
    When I visit "/entity-browser/iframe/media_browser"
    And I upload "test.jpg"
    And I enter "Foobazzz" for "Media name"
    And I press "Place"
    And I visit "/admin/content/media"
    Then I should see "Foobazzz"

  @test_module
  Scenario: Uploading a document from within the media browser
    Given I am logged in as a user with the media_manager role
    When I visit "/entity-browser/iframe/media_browser"
    And I upload "test.pdf"
    And I enter "A test file" for "Media name"
    And I press "Place"
    And I visit "/admin/content/media"
    Then I should see "A test file"

  Scenario: Media browser upload widget should require a file
    Given I am logged in as a user with the media_manager role
    When I visit "/entity-browser/iframe/media_browser"
    And I click "Upload"
    And I press "Place"
    Then I should see the following error message:
      | error messages          |
      | You must upload a file. |

  Scenario: Media browser upload widget validates file size
    Given I am logged in as a user with the media_manager role
    And "media.image.image" has a maximum upload size of "5 KB"
    When I visit "/entity-browser/iframe/media_browser"
    And I click "Upload"
    And I attach the file "test.jpg" to "input_file"
    And I wait for AJAX to finish
    # This is a weak-sauce assertion but I can't tell exactly what the error
    # message will say.
    Then I should see a ".messages [role='alert']" element
    And I should see an "input.form-file.error" element

  Scenario: Media browser upload widget should ensure that input can be matched to a media bundle
    Given I am logged in as a user with the media_manager role
    And "media.document.field_document" accepts foo files
    When I visit "/entity-browser/iframe/media_browser"
    And I click "Upload"
    And I attach the file "test.pdf" to "input_file"
    And I wait for AJAX to finish
    And I press "Place"
    Then I should see the following error message:
      | error messages                               |
      | No media types can be matched to this input. |
