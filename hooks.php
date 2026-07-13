<?php

spl_autoload_register(function ($class) {
    if (strpos($class, 'Ptisp\\') !== 0) return;
    $rel = str_replace('\\', '/', substr($class, 6));
    $file = __DIR__ . '/lib/' . $rel . '.php';
    if (is_file($file)) require_once $file;
});

use Ptisp\ContactVerificationTracker;
use Ptisp\PtispConfig;

/**
 * Suppress the Domain Registration Confirmation email for domains that are still
 * awaiting contact verification.
 *
 * A marker row (status = 'pending') is written by ptisp_RegisterDomain and
 * flipped to 'verified' by ptisp_Sync once the registry confirms the domain
 * is active. The confirmation email is held back until that transition occurs, so
 * it is sent exactly once — after the domain becomes active.
 */
add_hook('EmailPreSend', 1, function ($vars) {
    $messageName = $vars['messagename'] ?? '';
    $domainId    = (int) ($vars['relid'] ?? 0);

    if (!$domainId) {
        return;
    }

    // Only intercept the domain registration confirmation email.
    if ($messageName !== PtispConfig::EMAIL_REGISTRATION_CONFIRMATION) {
        return;
    }

    $pending = ContactVerificationTracker::isPending($domainId);

    if ($pending) {
        return ['abortsend' => true];
    }
});

/**
 * Immediately after a PTisp registration that comes back pending, send the
 * reseller-configured "pending contact verification" email template (if any).
 */
add_hook('AfterRegistrarRegistration', 1, function ($vars) {
    $registrar = $vars['params']['registrar'] ?? '';
    $domainId  = (int) ($vars['params']['domainid'] ?? 0);

    // Only act for PTisp domains.
    if ($registrar !== PtispConfig::MODULE_NAME) {
        return;
    }

    // This hook only receives 'params' (no registrar return value), so the
    // pending state is read from the tracker row written by RegisterDomain.
    if (!ContactVerificationTracker::isPending($domainId)) {
        return;
    }

    // The hook's params do not reliably carry module config settings, so read
    // the configured template straight from the registrar settings.
    $template = PtispConfig::getModuleSetting('PendingEmailTemplate');

    if ($domainId && !empty($template)) {
        localAPI('SendEmail', [
            'messagename' => $template,
            'id'          => $domainId,
            'clientid'    => (int) ($vars['params']['userid'] ?? 0),
        ]);
    }
});
