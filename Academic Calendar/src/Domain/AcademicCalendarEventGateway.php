<?php

namespace Gibbon\Module\AcademicCalendar\Domain;

use Gibbon\Domain\QueryableGateway;
use Gibbon\Domain\Traits\TableAware;

class AcademicCalendarEventGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonPlannerEntry';
    private static $primaryKey = 'gibbonPlannerEntryID';
    private static $searchableColumns = [];

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

    public function selectAllYearGroupsBySchoolYear(string $gibbonSchoolYearID): array
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID];

        $sql = "
            SELECT DISTINCT yg.gibbonYearGroupID, yg.name, yg.nameShort, yg.sequenceNumber
            FROM gibbonYearGroup yg
            INNER JOIN gibbonStudentEnrolment se ON se.gibbonYearGroupID = yg.gibbonYearGroupID
            WHERE se.gibbonSchoolYearID = :gibbonSchoolYearID
            ORDER BY yg.sequenceNumber, yg.name
        ";

        return $this->db()->select($sql, $data)->fetchAll();
    }

    public function selectChildrenForParent(string $gibbonPersonID): array
    {
        $data = ['gibbonPersonID' => $gibbonPersonID];

        $sql = "
            SELECT DISTINCT
                fc.gibbonPersonID AS childPersonID,
                p.preferredName,
                p.surname
            FROM gibbonFamilyAdult fa
            INNER JOIN gibbonFamily f ON f.gibbonFamilyID = fa.gibbonFamilyID
            INNER JOIN gibbonFamilyChild fc ON fc.gibbonFamilyID = f.gibbonFamilyID
            INNER JOIN gibbonPerson p ON p.gibbonPersonID = fc.gibbonPersonID
            WHERE fa.gibbonPersonID = :gibbonPersonID
              AND fa.childDataAccess = 'Y'
              AND p.status = 'Full'
            ORDER BY p.surname, p.preferredName
        ";

        return $this->db()->select($sql, $data)->fetchAll();
    }

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

    private function baseEventSql(): string
    {
        return "
            SELECT
                pe.gibbonPlannerEntryID,
                pe.gibbonCourseClassID,
                pe.homeworkDueDateTime,
                pe.name AS homeworkName,
                c.gibbonYearGroupIDList,
                c.nameShort AS courseNameShort,
                cc.nameShort AS classNameShort,
                (
                    SELECT mb.gibbonMarkbookColumnID
                    FROM gibbonMarkbookColumn mb
                    WHERE mb.gibbonPlannerEntryID = pe.gibbonPlannerEntryID
                    ORDER BY mb.gibbonMarkbookColumnID ASC
                    LIMIT 1
                ) AS markbookColumnID,
                (
                    SELECT mb.name
                    FROM gibbonMarkbookColumn mb
                    WHERE mb.gibbonPlannerEntryID = pe.gibbonPlannerEntryID
                    ORDER BY mb.gibbonMarkbookColumnID ASC
                    LIMIT 1
                ) AS markbookName,
                (
                    SELECT mb.type
                    FROM gibbonMarkbookColumn mb
                    WHERE mb.gibbonPlannerEntryID = pe.gibbonPlannerEntryID
                    ORDER BY mb.gibbonMarkbookColumnID ASC
                    LIMIT 1
                ) AS markbookType
            FROM gibbonPlannerEntry pe
            INNER JOIN gibbonCourseClass cc ON cc.gibbonCourseClassID = pe.gibbonCourseClassID
            INNER JOIN gibbonCourse c ON c.gibbonCourseID = cc.gibbonCourseID
        ";
    }
}
