<?php

require_once __DIR__.'/moduleFunctions.php';

/**
 * Student Dashboard hook renderer.
 *
 * Returns an embedded Homework Calendar iframe, or a warning when
 * the current student is outside enabled year groups.
 *
 * @return string HTML fragment for the dashboard tab.
 */
$output = '';
$enabledYearGroupIDs = ac_getEnabledYearGroupIDsFromConnection($connection2);
$gibbonSchoolYearID = (string) $session->get('gibbonSchoolYearID');
$gibbonPersonIDCurrent = (string) $session->get('gibbonPersonID');

if (!ac_isPersonInEnabledYearGroups($connection2, $gibbonSchoolYearID, $gibbonPersonIDCurrent, $enabledYearGroupIDs)) {
    $output .= "<div class='warning'>";
    $output .= __('Homework Calendar is not enabled for your year group.');
    $output .= '</div>';
    return $output;
}

if (!isActionAccessible($guid, $connection2, '/modules/Academic Calendar/calendar_view.php')) {
    $output .= "<div class='error'>";
    $output .= __('You do not have access to this action.');
    $output .= '</div>';
    return $output;
}

$src = $session->get('absoluteURL').'/fullscreen.php?q='.rawurlencode('/modules/Academic Calendar/calendar_view.php').'&embed=1';

$output .= '<div style="min-height:520px">';
$output .= "<iframe title='".htmlspecialchars(__('Homework Calendar'), ENT_QUOTES)."' src='".htmlspecialchars($src, ENT_QUOTES)."' style='width:100%; height:72vh; min-height:520px; border:0; background:#fff;' loading='lazy'></iframe>";
$output .= '</div>';

return $output;
