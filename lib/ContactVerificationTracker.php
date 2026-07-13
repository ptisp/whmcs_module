<?php

namespace Ptisp;

use WHMCS\Database\Capsule;

/**
 * Tracks domains awaiting PTisp contact verification after registration.
 *
 * A row is inserted (status = 'pending') by ptisp_RegisterDomain when the
 * PTisp API returns a pending contact-verification response. ptisp_Sync
 * transitions the row to 'verified' on the first sync that confirms the
 * domain is active, which also triggers the registration confirmation email.
 *
 * Rows are never deleted — they serve as an audit trail.
 */
class ContactVerificationTracker {

    const TABLE = 'mod_ptisp_pending_registration';

    /** Marker states stored in the `status` column. */
    const STATUS_PENDING  = 'pending';
    const STATUS_VERIFIED = 'verified';

    /**
     * Lazily creates the tracking table if it does not yet exist.
     */
    public static function ensureTable() {
        try {
            if (!Capsule::schema()->hasTable(self::TABLE)) {
                Capsule::schema()->create(self::TABLE, function ($table) {
                    $table->integer('domain_id')->primary();
                    $table->string('status', 32)->default(self::STATUS_PENDING);
                    $table->timestamp('created_at')->nullable();
                    $table->timestamp('verified_at')->nullable();
                });
            }
        } catch (\Exception $e) {
            // table creation failed; subsequent calls will retry
        }
    }

    /**
     * Insert or reset a pending marker for the given domain.
     * Called only from ptisp_RegisterDomain.
     */
    public static function markPending($domainId) {
        self::ensureTable();
        Capsule::table(self::TABLE)->updateOrInsert(
            ['domain_id' => (int) $domainId],
            ['status' => self::STATUS_PENDING, 'created_at' => date('Y-m-d H:i:s'), 'verified_at' => null]
        );
    }

    /**
     * Transition the marker from 'pending' to 'verified'.
     *
     * Returns the number of rows updated: 1 on the first successful sync,
     * 0 on every subsequent call (idempotent — no duplicate emails).
     */
    public static function markVerified($domainId) {
        self::ensureTable();
        $rows = Capsule::table(self::TABLE)
            ->where('domain_id', (int) $domainId)
            ->where('status', self::STATUS_PENDING)
            ->update(['status' => self::STATUS_VERIFIED, 'verified_at' => date('Y-m-d H:i:s')]);
        return $rows;
    }

    /**
     * Returns true if the domain has an unresolved pending marker.
     * Used by the EmailPreSend hook to suppress premature emails.
     */
    public static function isPending($domainId) {
        try {
            $result = Capsule::table(self::TABLE)
                ->where('domain_id', (int) $domainId)
                ->where('status', self::STATUS_PENDING)
                ->exists();
            return $result;
        } catch (\Exception $e) {
            // If the table does not exist yet, no domain is pending.
            return false;
        }
    }
}
