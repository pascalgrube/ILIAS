<?php
/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * Calendar schedule filter for individual timings
 *
 * @author Stefan Meyer <smeyer.ilias@gmx.de>
 *
 * @ingroup ModulesCourse
 */
class ilCalendarScheduleFilterTimings implements ilCalendarScheduleFilter
{
    const CAL_TIMING_START = 1;
    const CAL_TIMING_END = 2;

    /**
     * @var int
     */
    private $user_id = 0;


    /**
     * @var \ilLogger
     */
    private $logger;

    /**
     * ilCalendarScheduleFilterTimings constructor.
     * @param int a_usr_id
     */
    public function __construct($a_usr_id)
    {
        $this->user_id = $a_usr_id;
        $this->logger = ilLoggerFactory::getLogger('crs');
    }

    /**
     * Get logger
     * @return \ilLogger
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * Filter categories
     * All categories are show no filtering (support for individual folder appointments)
     * @param int[] $a_cats
     */
    public function filterCategories(array $a_cats)
    {
        return $a_cats;
    }

    /**
     * modify event => return false for not presenting event
     * @param \ilCalendarEntry $a_event
     * @return bool|ilCalendarEntry
     */
    public function modifyEvent(ilCalendarEntry $a_event)
    {
        $this->getLogger()->debug('Modifying events for timings');
        if (!$a_event->isAutoGenerated()) {
            $this->getLogger()->debug($a_event->getTitle() . ' is not autogenerated => no modification');
            return $a_event;
        }

        if (
            $a_event->getContextId() != ilObjCourse::CAL_COURSE_TIMING_START &&
            $a_event->getContextId() != ilObjCourse::CAL_COURSE_TIMING_END
        ) {
            $this->getLogger()->debug('Non Timing event: unmodified');
            return $a_event;
        }

        // check course calendar
        $cat_id = ilCalendarCategoryAssignments::_lookupCategory($a_event->getEntryId());
        $category = $this->isCourseCategory($cat_id);
        if (!$category) {
            // no course category
            return $a_event;
        }

        // check absolute timings
        // category object type is folder
        $obj_id = $category->getObjId();
        $ref_ids = ilObject::_getAllReferences($obj_id);
        $ref_id = end($ref_ids);
        if (ilObjCourse::lookupTimingMode($obj_id) == ilCourseConstants::IL_CRS_VIEW_TIMING_RELATIVE) {
            // relative timings => always modify event
            $this->getLogger()->debug('Filtering event since mode is relative timing: ' . $a_event->getPresentationTitle(true));
            return false;
        }

        // timings enabled?
        if (!$this->enabledCourseTimings($ref_id)) {
            return false;
        }

        // check context info
        if (!$a_event->getContextInfo()) {
            $this->getLogger()->warning('Missing context info');
            return false;
        }

        $item_ref_id = (int) $a_event->getContextInfo();
        $timing_item = ilObjectActivation::getItem($item_ref_id);
        if ($timing_item['timing_type'] != ilObjectActivation::TIMINGS_PRESETTING) {
            $this->getLogger()->debug('Delete event with disabled timing settings');
            return false;
        }

        if ($timing_item['changeable']) {
            // check if scheduled
            $user_data = new ilTimingUser($item_ref_id, $this->user_id);
            if ($user_data->isScheduled()) {
                $this->getLogger()->debug('Filtering event since item is scheduled by user: ' . $a_event->getPresentationTitle(true));
                return false;
            }
        }

        // valid event => refresh title
        $this->getLogger()->debug('Valid timings event. Update title');
        $a_event->setTitle(
            ilObject::_lookupTitle(ilObject::_lookupObjId($item_ref_id))
        );
        return $a_event;
    }


    /**
     * Add custom events: relative timings, modified timings
     *
     * @param ilDate $start
     * @param ilDate $end
     * @param array $a_categories
     */
    public function addCustomEvents(ilDate $start, ilDate $end, array $a_categories)
    {
        // @fixme
        // @todo categories can appear more than once
        $a_categories = array_unique($a_categories);


        $all_events = [];
        foreach ($a_categories as $cat_id) {
            $category = $this->isCourseCategory($cat_id);
            if (!$category) {
                continue;
            }
            $course_obj_id = $category->getObjId();
            $ref_ids = ilObject::_getAllReferences($course_obj_id);
            $course_ref_id = end($ref_ids);
            $course_timing_mode = ilObjCourse::lookupTimingMode($course_obj_id);
            if (!$this->enabledCourseTimings($course_ref_id)) {
                ilLoggerFactory::getLogger('cal')->debug('Timings disabled for course: ');
                continue;
            }
            $active = ilObjectActivation::getTimingsItems($course_ref_id);
            foreach ($active as $null => $item) {
                if ($item['timing_type'] != ilObjectActivation::TIMINGS_PRESETTING) {
                    $this->getLogger()->debug('timings not active for: ' . $item['ref_id']);
                    continue;
                }
                if (
                    !$item['changeable'] &&
                    $course_timing_mode == ilCourseConstants::IL_CRS_VIEW_TIMING_ABSOLUTE
                ) {
                    $this->getLogger('cal')->debug('Not creating new event since item is unchangeable and absolute');
                    continue;
                }

                $user_data = new ilTimingUser($item['ref_id'], $this->user_id);
                if (!$user_data->isScheduled()) {
                    $this->getLogger()->debug('No scheduled timings for user');
                    continue;
                }
                if (
                    ilDateTime::_within($user_data->getStart(), $start, $end) or
                    ilDateTime::_within($user_data->getEnd(), $start, $end)
                ) {
                    $entries = $this->findCalendarEntriesForItem($category->getCategoryID(), $item['ref_id']);

                    $this->logger->dump($item);
                    $this->logger->debug('Number of found entries is: ' . count($entries));

                    foreach ($entries as $entry) {
                        if ($entry->getContextId() == ilObjCourse::CAL_COURSE_TIMING_START) {
                            $entry->setStart(new ilDate($user_data->getStart()->get(IL_CAL_DATE), IL_CAL_DATE));
                            $entry->setEnd(new ilDate($user_data->getStart()->get(IL_CAL_DATE), IL_CAL_DATE));
                        }
                        if ($entry->getContextId() == ilObjCourse::CAL_COURSE_TIMING_END) {
                            $entry->setStart(new ilDate($user_data->getEnd()->get(IL_CAL_DATE), IL_CAL_DATE));
                            $entry->setEnd(new ilDate($user_data->getEnd()->get(IL_CAL_DATE), IL_CAL_DATE));
                        }
                        $all_events[] = $entry;
                    }
                }
            }
        }
        return $all_events;
    }

    /**
     * @param $category_id
     * @param $item_ref_id
     * @return ilCalendarEntry[]
     */
    protected function findCalendarEntriesForItem($category_id, $item_ref_id)
    {
        $app_ids = ilCalendarCategoryAssignments::_getAssignedAppointments([$category_id]);
        $entries = [];
        foreach ($app_ids as $app_id) {
            $entry = new ilCalendarEntry($app_id);

            if (
                (
                    $entry->getContextId() == ilObjCourse::CAL_COURSE_TIMING_START ||
                    $entry->getContextId() == ilObjCourse::CAL_COURSE_TIMING_END

                ) &&
                $entry->getContextInfo() == $item_ref_id
            ) {
                $entries[] = $entry;
            }
        }
        return $entries;
    }


    /**
     * @param int $a_category_id
     * @return bool|ilCalendarCategory
     */
    protected function isCourseCategory($a_category_id)
    {
        $category = ilCalendarCategory::getInstanceByCategoryId($a_category_id);

        if ($category->getType() != ilCalendarCategory::TYPE_OBJ) {
            $this->getLogger()->debug('No object calendar => not modifying event.');
            return false;
        }
        if ($category->getObjType() != 'crs') {
            $this->getLogger()->debug('Category object type is != crs => not modifying event');
            return false;
        }
        return $category;
    }

    /**
     * Check if timings enabled for ref_id
     * @param int $a_course_ref
     * @return bool
     */
    protected function enabledCourseTimings($a_course_ref)
    {
        if (ilObjCourse::_lookupViewMode(ilObject::_lookupObjId($a_course_ref)) != ilContainer::VIEW_TIMING) {
            $this->getLogger()->debug('Parent course has other view mode than timings. course ref_id = ' . $a_course_ref);
            return false;
        }
        return true;
    }
}
