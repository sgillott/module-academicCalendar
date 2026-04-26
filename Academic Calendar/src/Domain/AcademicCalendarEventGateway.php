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

namespace Gibbon\Module\AcademicCalendar\Domain;

use Gibbon\Domain\QueryableGateway;
use Gibbon\Domain\Traits\TableAware;

class AcademicCalendarEventGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonPlannerEntry';
    private static $primaryKey = 'gibbonPlannerEntryID';
    private static $searchableColumns = [];

    /**
     * Get year groups linked to classes taught by a staff member.
     *
     * @param string $gibbonSchoolYearID Active school year ID.
     * @param string $gibbonPersonID Staff person ID.
     *
     * @return array<int, array<string, mixed>> Year-group rows.
     */
    public function selectYearGroupsForStaff(string $gibbonSchoolYearID, string $gibbonPersonID): array
    {
        $data = [
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
            'gibbonPersonID' => $gibbonPersonID,
        ];

        $sql = "
            SELECT DISTINCT yg.gibbonYearGroupID, yg.name, yg.nameShort, yg.sequenceNumber
            FROM gibbonCourseClassPerson ccp
            INNER JOIN gibbonCourseClass cc ON cc.gibbonCourseClassID = ccp.gibbonCourseClassID
            INNER JOIN gibbonCourse c ON c.gibbonCourseID = cc.gibbonCourseID
            INNER JOIN gibbonYearGroup yg ON FIND_IN_SET(yg.gibbonYearGroupID, REPLACE(c.gibbonYearGroupIDList, ' ', '')) > 0
            WHERE ccp.gibbonPersonID = :gibbonPersonID
              AND ccp.role IN ('Teacher', 'Assistant')
              AND c.gibbonSchoolYearID = :gibbonSchoolYearID
            ORDER BY yg.sequenceNumber, yg.name
        ";

        return $this->db()->select($sql, $data)->fetchAll();
    }

    /**
     * Select homework events visible to a student.
     *
     * @param string $gibbonSchoolYearID Active school year ID.
     * @param string $gibbonPersonID Student person ID.
     * @param string $dateStart Inclusive datetime lower bound (`Y-m-d H:i:s`).
     * @param string $dateEnd Exclusive datetime upper bound (`Y-m-d H:i:s`).
     *
     * @return array<int, array<string, mixed>> Planner/markbook event rows.
     */
    public function selectStudentEvents(
        string $gibbonSchoolYearID,
        string $gibbonPersonID,
        string $dateStart,
        string $dateEnd
    ): array {
        $data = [
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
            'gibbonPersonID' => $gibbonPersonID,
            'dateStart' => $dateStart,
            'dateEnd' => $dateEnd,
        ];

        $sql = $this->baseEventSql()."
            INNER JOIN gibbonCourseClassPerson ccp
                ON ccp.gibbonCourseClassID = cc.gibbonCourseClassID
               AND ccp.gibbonPersonID = :gibbonPersonID
               AND ccp.role = 'Student'
            WHERE c.gibbonSchoolYearID = :gibbonSchoolYearID
              AND pe.homework = 'Y'
              AND pe.viewableStudents = 'Y'
              AND pe.homeworkDueDateTime IS NOT NULL
              AND pe.homeworkDueDateTime >= :dateStart
              AND pe.homeworkDueDateTime < :dateEnd
            ORDER BY pe.homeworkDueDateTime
        ";

        return $this->db()->select($sql, $data)->fetchAll();
    }

    /**
     * Select homework events visible to a parent for a specific child.
     *
     * @param string $gibbonSchoolYearID Active school year ID.
     * @param string $parentPersonID Parent person ID.
     * @param string $childPersonID Child person ID.
     * @param string $dateStart Inclusive datetime lower bound (`Y-m-d H:i:s`).
     * @param string $dateEnd Exclusive datetime upper bound (`Y-m-d H:i:s`).
     *
     * @return array<int, array<string, mixed>> Planner/markbook event rows.
     */
    public function selectParentEvents(
        string $gibbonSchoolYearID,
        string $parentPersonID,
        string $childPersonID,
        string $dateStart,
        string $dateEnd
    ): array {
        $data = [
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
            'parentPersonID' => $parentPersonID,
            'childPersonID' => $childPersonID,
            'dateStart' => $dateStart,
            'dateEnd' => $dateEnd,
        ];

        $sql = $this->baseEventSql()."
            INNER JOIN gibbonCourseClassPerson ccp
                ON ccp.gibbonCourseClassID = cc.gibbonCourseClassID
               AND ccp.gibbonPersonID = :childPersonID
               AND ccp.role = 'Student'
            INNER JOIN gibbonFamilyChild fc ON fc.gibbonPersonID = ccp.gibbonPersonID
            INNER JOIN gibbonFamilyAdult fa ON fa.gibbonFamilyID = fc.gibbonFamilyID
               AND fa.gibbonPersonID = :parentPersonID
               AND fa.childDataAccess = 'Y'
            WHERE c.gibbonSchoolYearID = :gibbonSchoolYearID
              AND pe.homework = 'Y'
              AND pe.viewableParents = 'Y'
              AND pe.homeworkDueDateTime IS NOT NULL
              AND pe.homeworkDueDateTime >= :dateStart
              AND pe.homeworkDueDateTime < :dateEnd
            ORDER BY pe.homeworkDueDateTime
        ";

        return $this->db()->select($sql, $data)->fetchAll();
    }

    /**
     * Select homework events for staff calendar view.
     *
     * @param string $gibbonSchoolYearID Active school year ID.
     * @param string $dateStart Inclusive datetime lower bound (`Y-m-d H:i:s`).
     * @param string $dateEnd Exclusive datetime upper bound (`Y-m-d H:i:s`).
     * @param string|null $yearGroupID Optional year-group filter.
     *
     * @return array<int, array<string, mixed>> Planner/markbook event rows.
     */
    public function selectStaffEvents(
        string $gibbonSchoolYearID,
        string $dateStart,
        string $dateEnd,
        ?string $yearGroupID = null
    ): array {
        $data = [
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
            'dateStart' => $dateStart,
            'dateEnd' => $dateEnd,
        ];

        $whereYearGroup = '';
        if (!empty($yearGroupID)) {
            $whereYearGroup = " AND FIND_IN_SET(:yearGroupID, REPLACE(c.gibbonYearGroupIDList, ' ', '')) > 0 ";
            $data['yearGroupID'] = $yearGroupID;
        }

        $sql = $this->baseEventSql()."
            WHERE c.gibbonSchoolYearID = :gibbonSchoolYearID
              AND pe.homework = 'Y'
              AND pe.homeworkDueDateTime IS NOT NULL
              AND pe.homeworkDueDateTime >= :dateStart
              AND pe.homeworkDueDateTime < :dateEnd
              {$whereYearGroup}
            ORDER BY pe.homeworkDueDateTime
        ";

        return $this->db()->select($sql, $data)->fetchAll();
    }

    /**
     * Select markbook assessment events visible to a student.
     *
     * @param string $gibbonSchoolYearID Active school year ID.
     * @param string $gibbonPersonID Student person ID.
     * @param string $dateStart Inclusive datetime lower bound (`Y-m-d H:i:s`).
     * @param string $dateEnd Exclusive datetime upper bound (`Y-m-d H:i:s`).
     *
     * @return array<int, array<string, mixed>> Markbook assessment rows.
     */
    public function selectStudentAssessmentEvents(
        string $gibbonSchoolYearID,
        string $gibbonPersonID,
        string $dateStart,
        string $dateEnd
    ): array {
        $data = [
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
            'gibbonPersonID' => $gibbonPersonID,
            'dateStart' => date('Y-m-d', strtotime($dateStart)),
            'dateEnd' => date('Y-m-d', strtotime($dateEnd)),
        ];

        $sql = $this->baseAssessmentSql()."
            INNER JOIN gibbonCourseClassPerson ccp
                ON ccp.gibbonCourseClassID = cc.gibbonCourseClassID
               AND ccp.gibbonPersonID = :gibbonPersonID
               AND ccp.role = 'Student'
            WHERE c.gibbonSchoolYearID = :gibbonSchoolYearID
              AND mc.viewableStudents = 'Y'
              AND mc.date IS NOT NULL
              AND mc.date >= :dateStart
              AND mc.date < :dateEnd
            ORDER BY mc.date, mc.sequenceNumber, mc.gibbonMarkbookColumnID
        ";

        return $this->db()->select($sql, $data)->fetchAll();
    }

    /**
     * Select markbook assessment events visible to a parent for a child.
     *
     * @param string $gibbonSchoolYearID Active school year ID.
     * @param string $parentPersonID Parent person ID.
     * @param string $childPersonID Child person ID.
     * @param string $dateStart Inclusive datetime lower bound (`Y-m-d H:i:s`).
     * @param string $dateEnd Exclusive datetime upper bound (`Y-m-d H:i:s`).
     *
     * @return array<int, array<string, mixed>> Markbook assessment rows.
     */
    public function selectParentAssessmentEvents(
        string $gibbonSchoolYearID,
        string $parentPersonID,
        string $childPersonID,
        string $dateStart,
        string $dateEnd
    ): array {
        $data = [
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
            'parentPersonID' => $parentPersonID,
            'childPersonID' => $childPersonID,
            'dateStart' => date('Y-m-d', strtotime($dateStart)),
            'dateEnd' => date('Y-m-d', strtotime($dateEnd)),
        ];

        $sql = $this->baseAssessmentSql()."
            INNER JOIN gibbonCourseClassPerson ccp
                ON ccp.gibbonCourseClassID = cc.gibbonCourseClassID
               AND ccp.gibbonPersonID = :childPersonID
               AND ccp.role = 'Student'
            INNER JOIN gibbonFamilyChild fc ON fc.gibbonPersonID = ccp.gibbonPersonID
            INNER JOIN gibbonFamilyAdult fa ON fa.gibbonFamilyID = fc.gibbonFamilyID
               AND fa.gibbonPersonID = :parentPersonID
               AND fa.childDataAccess = 'Y'
            WHERE c.gibbonSchoolYearID = :gibbonSchoolYearID
              AND mc.viewableParents = 'Y'
              AND mc.date IS NOT NULL
              AND mc.date >= :dateStart
              AND mc.date < :dateEnd
            ORDER BY mc.date, mc.sequenceNumber, mc.gibbonMarkbookColumnID
        ";

        return $this->db()->select($sql, $data)->fetchAll();
    }

    /**
     * Select markbook assessment events for staff calendar view.
     *
     * @param string $gibbonSchoolYearID Active school year ID.
     * @param string $dateStart Inclusive datetime lower bound (`Y-m-d H:i:s`).
     * @param string $dateEnd Exclusive datetime upper bound (`Y-m-d H:i:s`).
     * @param string|null $yearGroupID Optional year-group filter.
     *
     * @return array<int, array<string, mixed>> Markbook assessment rows.
     */
    public function selectStaffAssessmentEvents(
        string $gibbonSchoolYearID,
        string $dateStart,
        string $dateEnd,
        ?string $yearGroupID = null
    ): array {
        $data = [
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
            'dateStart' => date('Y-m-d', strtotime($dateStart)),
            'dateEnd' => date('Y-m-d', strtotime($dateEnd)),
        ];

        $whereYearGroup = '';
        if (!empty($yearGroupID)) {
            $whereYearGroup = " AND FIND_IN_SET(:yearGroupID, REPLACE(c.gibbonYearGroupIDList, ' ', '')) > 0 ";
            $data['yearGroupID'] = $yearGroupID;
        }

        $sql = $this->baseAssessmentSql()."
            WHERE c.gibbonSchoolYearID = :gibbonSchoolYearID
              AND mc.date IS NOT NULL
              AND mc.date >= :dateStart
              AND mc.date < :dateEnd
              {$whereYearGroup}
            ORDER BY mc.date, mc.sequenceNumber, mc.gibbonMarkbookColumnID
        ";

        return $this->db()->select($sql, $data)->fetchAll();
    }

    /**
     * Shared select block for planner entries and related course/class fields.
     *
     * Includes first related markbook column data via correlated subqueries.
     *
     * @return string SQL select block.
     */
    private function baseEventSql(): string
    {
        return "
            SELECT
                pe.gibbonPlannerEntryID,
                pe.gibbonCourseClassID,
                pe.homeworkDueDateTime,
                pe.name AS homeworkName,
                c.gibbonYearGroupIDList,
                c.name AS courseName,
                c.nameShort AS courseNameShort,
                d.name AS learningArea,
                cc.name AS className,
                cc.nameShort AS classNameShort,
                mb.gibbonMarkbookColumnID AS markbookColumnID,
                mb.name AS markbookName,
                mb.type AS markbookType
            FROM gibbonPlannerEntry pe
            INNER JOIN gibbonCourseClass cc ON cc.gibbonCourseClassID = pe.gibbonCourseClassID
            INNER JOIN gibbonCourse c ON c.gibbonCourseID = cc.gibbonCourseID
            LEFT JOIN gibbonDepartment d ON d.gibbonDepartmentID = c.gibbonDepartmentID
            LEFT JOIN (
                SELECT
                    mb1.gibbonPlannerEntryID,
                    mb1.gibbonMarkbookColumnID,
                    mb1.name,
                    mb1.type
                FROM gibbonMarkbookColumn mb1
                INNER JOIN (
                    SELECT
                        gibbonPlannerEntryID,
                        MIN(gibbonMarkbookColumnID) AS firstMarkbookColumnID
                    FROM gibbonMarkbookColumn
                    GROUP BY gibbonPlannerEntryID
                ) mbFirst ON mbFirst.firstMarkbookColumnID = mb1.gibbonMarkbookColumnID
            ) mb ON mb.gibbonPlannerEntryID = pe.gibbonPlannerEntryID
        ";
    }

    /**
     * Shared select block for markbook assessment calendar rows.
     *
     * @return string SQL select block.
     */
    private function baseAssessmentSql(): string
    {
        return "
            SELECT DISTINCT
                mc.gibbonMarkbookColumnID,
                mc.gibbonCourseClassID,
                mc.date AS assessmentDate,
                mc.name AS assessmentName,
                mc.description AS assessmentDescription,
                mc.type AS assessmentType,
                mc.columnColor AS assessmentColor,
                c.gibbonYearGroupIDList,
                c.name AS courseName,
                c.nameShort AS courseNameShort,
                d.name AS learningArea,
                cc.name AS className,
                cc.nameShort AS classNameShort
            FROM gibbonMarkbookColumn mc
            INNER JOIN gibbonCourseClass cc ON cc.gibbonCourseClassID = mc.gibbonCourseClassID
            INNER JOIN gibbonCourse c ON c.gibbonCourseID = cc.gibbonCourseID
            LEFT JOIN gibbonDepartment d ON d.gibbonDepartmentID = c.gibbonDepartmentID
        ";
    }
}
