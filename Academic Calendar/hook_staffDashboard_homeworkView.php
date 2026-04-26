<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

/**
 * Staff Dashboard hook renderer.
 *
 * Returns an embedded Homework Calendar iframe for staff users
 * with access to the module action.
 *
 * @return string HTML fragment for the dashboard tab.
 */
$output = '';

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
