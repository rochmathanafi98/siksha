<?php

require_once($CFG->dirroot . '/mod/webinar/backup/moodle2/backup_webinar_stepslib.php'); // Because it exists (must)
require_once($CFG->dirroot . '/mod/webinar/backup/moodle2/backup_webinar_settingslib.php'); // Because it exists (optional)

/**
 * webinar backup task that provides all the settings and steps to perform one
 * complete backup of the activity
 */
class backup_webinar_activity_task extends backup_activity_task {

    /**
     * Define (add) particular settings this activity can have
     */
    protected function define_my_settings() {
        // No particular settings for this activity
    }

    /**
     * Define (add) particular steps this activity can have
     */
    protected function define_my_steps() {
        // webinar only has one structure step
        $this->add_step(new backup_webinar_activity_structure_step('webinar_structure', 'webinar.xml'));
    }

    /**
     * Code the transformations to perform in the activity in
     * order to get transportable (encoded) links
     */
    static public function encode_content_links($content) {
        global $CFG;

        $base = preg_quote($CFG->wwwroot,"/");

        // Link to the list of webinars
        $search="/(".$base."\/mod\/webinar\/index.php\?id\=)([0-9]+)/";
        $content= preg_replace($search, '$@WEBINARINDEX*$2@$', $content);

        // Link to webinar view by moduleid
        $search="/(".$base."\/mod\/webinar\/view.php\?id\=)([0-9]+)/";
        $content= preg_replace($search, '$@WEBINARVIEWBYID*$2@$', $content);

        return $content;
    }
}
