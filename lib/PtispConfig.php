<?php

namespace Ptisp;

use WHMCS\Database\Capsule;

/**
 * Database query helpers used when building the WHMCS module config array
 * and when resolving client tax ID / custom field settings.
 */
class PtispConfig {

    /** WHMCS registrar module identifier — used for hook guards and log calls. */
    const MODULE_NAME = 'ptisp';

    /** PTisp API registration response when contact verification is required. */
    const DOMAIN_STATUS_PENDING_CONTACT_VERIFICATION = 'pending_contact_verification';

    /** PTisp registrar ID at the .pt registry. */
    const REGISTRAR_ID_PT = 'A-SD-098616-FCCN';

    /** PTisp API domain statuses that indicate the domain is live at the registry. */
    const DOMAIN_STATUS_OK     = 'ok';
    const DOMAIN_STATUS_ACTIVE = 'active';

    /** WHMCS built-in email template sent when a domain registration completes. */
    const EMAIL_REGISTRATION_CONFIRMATION = 'Domain Registration Confirmation';

    /**
     * Reads a single decrypted registrar module setting value.
     * Uses WHMCS's getRegistrarConfigOptions() so values are decrypted (raw
     * tblregistrars rows are encrypted). Returns '' when absent or on error.
     * Used by hooks (e.g. EmailPreSend) that do not receive module params.
     */
    public static function getModuleSetting($setting) {
        try {
            $options = \getRegistrarConfigOptions(self::MODULE_NAME);
            return isset($options[$setting]) ? (string) $options[$setting] : '';
        } catch (\Exception $e) {
            error_log($e->getMessage());
            return '';
        }
    }

    /**
     * Returns an associative array of custom domain-type email templates,
     * suitable for a WHMCS dropdown config field.
     * Always includes a leading "None" entry.
     */
    public static function getPendingEmailTemplateOptions() {
        try {
            $templates = Capsule::table('tblemailtemplates')
                ->where('type', 'domain')
                ->where('custom', 1)
                ->orderBy('name', 'ASC')
                ->pluck('name');

            $options = ['' => 'None'];
            foreach ($templates as $name) {
                $options[$name] = $name;
            }
            return $options;
        } catch (\Exception $e) {
            error_log($e->getMessage());
            return ['' => 'None'];
        }
    }

    /**
     * Returns dropdown options for the "Tax ID Custom Field" config setting.
     * Lists all client text custom fields, with the currently selected one first.
     */
    public static function getCustomfieldDropdownOptions(array $params) {
        $fields = self::getClientCustomFields() ?? [];
        $selectedField = self::getSelectedCustomField($params);

        if (!is_null($selectedField)) {
            $options = [$selectedField->id => $selectedField->fieldname];
        } else {
            $options = ['' => 'None'];
        }

        foreach ($fields as $field) {
            if ($field->fieldtype == 'text' && $field->id != ($selectedField->id ?? null)) {
                $options[$field->id] = $field->fieldname;
            }
        }
        return $options;
    }

    /**
     * Returns true when WHMCS's built-in Tax ID feature is active.
     * Returns null on database error (treated as false by callers).
     */
    public static function isTaxIdEnabled() {
        try {
            $setting = Capsule::table('tblconfiguration')
                ->where('setting', 'TaxIDDisabled')
                ->first();
            if (is_null($setting)) {
                return false;
            }
            return !$setting->value;
        } catch (\Exception $e) {
            error_log($e->getMessage());
            return null;
        }
    }

    /**
     * Reads the Tax ID value from the client custom field configured in the
     * module settings (Vatcustom). Returns null if not configured.
     */
    public static function getCustomTaxId(array $params) {
        $selectedField = self::getSelectedCustomField($params);
        if (!is_null($selectedField) && isset($params['customfields'])) {
            $key = array_search($selectedField->id, array_column($params['customfields'], 'id'));
            return trim($params['customfields'][$key]['value']);
        }
        return null;
    }

    /**
     * Resolves the custom field object selected by the Vatcustom setting.
     * Supports both legacy "customfields{N}" format and plain ID values.
     */
    public static function getSelectedCustomField(array $params) {
        $vatCustomSetting = $params['Vatcustom'] ?? '';
        if (empty(trim($vatCustomSetting))) {
            return null;
        }

        $fields = self::getClientCustomFields() ?? [];

        // Retrocompatible with old "customfields1", "customfields2", ... format.
        preg_match('/^customfields(\d+)$/', $vatCustomSetting, $matches);
        if (isset($matches[1])) {
            $index = $matches[1] - 1;
            return isset($fields[$index]) ? $fields[$index] : null;
        }

        foreach ($fields as $field) {
            if ($field->id == $vatCustomSetting) {
                return $field;
            }
        }
        return null;
    }

    /**
     * Fetches all client-type custom fields ordered by ID.
     */
    private static function getClientCustomFields() {
        try {
            return Capsule::table('tblcustomfields')
                ->where('type', 'client')
                ->orderBy('id', 'ASC')
                ->get();
        } catch (\Exception $e) {
            error_log($e->getMessage());
            return null;
        }
    }
}
