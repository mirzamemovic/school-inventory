<?php

namespace App\Support;

/**
 * The full set of audit log actions from spec section 5. Constants rather than
 * loose strings so a typo cannot quietly create a new action nobody filters on.
 */
final class EventAction
{
    public const ASSET_CREATED = 'asset.created';

    public const ASSET_MOVED = 'asset.moved';

    public const ASSET_ASSIGNED = 'asset.assigned';

    public const ASSET_STATUS_CHANGED = 'asset.status_changed';

    public const ASSET_EDITED = 'asset.edited';

    public const ASSET_RETIRED = 'asset.retired';

    public const ASSET_INSPECTED = 'asset.inspected';

    public const REPORT_CREATED = 'report.created';

    public const REPORT_VIEWED = 'report.viewed';

    public const REPORT_ACKNOWLEDGED = 'report.acknowledged';

    public const REPORT_RESOLVED = 'report.resolved';

    public const REPORT_DISMISSED = 'report.dismissed';

    public const BOOK_ISSUED = 'book.issued';

    public const BOOK_RETURNED = 'book.returned';

    /* A class book-debt link was withdrawn and a new one made in its place. */
    public const CLASS_LINK_REPLACED = 'book.class_link_replaced';

    public const SUPPLY_TAKEN = 'supply.taken';

    public const SUPPLY_RECOUNTED = 'supply.recounted';

    public const PERSON_ADDED = 'person.added';

    public const PERSON_DEACTIVATED = 'person.deactivated';

    /*
     * Spelled `licence.*` exactly as the specification writes them, even though
     * the table and class use the American spelling. These strings end up in
     * stored audit rows, so they are not something to tidy up later.
     */
    public const LICENCE_CREATED = 'licence.created';

    public const LICENCE_EDITED = 'licence.edited';

    public const LICENCE_RENEWED = 'licence.renewed';

    public const LICENCE_DELETED = 'licence.deleted';

    /*
     * Retention and erasure. Both are person-scoped and neither ever names the
     * person in its meta.
     */
    public const PERSON_ERASED = 'person.erased';

    public const DATA_RETENTION_ENFORCED = 'data.retention_enforced';

    /*
     * Exporting records that name people is itself a processing event
     * (spec section 16). The event says who, which dataset and how many rows,
     * never the contents.
     */
    public const DATA_EXPORTED = 'data.exported';

    /*
     * The device block list is the answer to the most likely attack, so who
     * blocked and unblocked what is kept like everything else.
     */
    public const DEVICE_BLOCKED = 'device.blocked';

    public const DEVICE_UNBLOCKED = 'device.unblocked';

    /*
     * Phones whose safety checks count. Invited and withdrawn by an admin; set
     * up from the phone itself, so that step is self-declared like any other
     * public action.
     */
    public const INSPECTOR_PHONE_INVITED = 'inspector_phone.invited';

    public const INSPECTOR_PHONE_CLAIMED = 'inspector_phone.claimed';

    public const INSPECTOR_PHONE_WITHDRAWN = 'inspector_phone.withdrawn';

    /** @return array<int, string> */
    public static function all(): array
    {
        return array_values((new \ReflectionClass(self::class))->getConstants());
    }
}
