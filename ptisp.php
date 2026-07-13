<?php

//v2.4.0

spl_autoload_register(function ($class) {
    if (strpos($class, 'Ptisp\\') !== 0) return;
    $rel = str_replace('\\', '/', substr($class, 6));
    $file = __DIR__ . '/lib/' . $rel . '.php';
    if (is_file($file)) require_once $file;
});
require_once __DIR__ . '/lib/vendor/RestRequest.inc.php';

use Ptisp\PtispApiClient;
use Ptisp\ContactVerificationTracker;
use Ptisp\PtispConfig;

function ptisp_getConfigArray($params) {
  $configarray = array(
    "Username" => array("FriendlyName" => "Username *", "Type" => "text", "Size" => "20", "Description" => "The email address associated with your PTisp account.",),
    "Hash" => array("FriendlyName" => "Hash *", "Type" => "password", "Size" => "100", "Description" => "Your PTisp API hash.",),
    "DisableFallback" => array("FriendlyName" => "Do not register domains with my PTisp profile data", "Type" => "yesno", "Default" => "on", "Description" => "When enabled, registrations fail if the client has no valid NIC-handle or Tax ID. When disabled, your PTisp reseller profile data is used as the registrant fallback."),
    "Nichandle" => array("FriendlyName" => "Default Technical Nic-handle", "Type" => "text", "Description" => "Fallback tech-contact NIC-handle used when no per-domain NIC-handle is provided at order time.",),
    "Nameserver" => array("FriendlyName" => "Default Name Server 1", "Type" => "text", "Description" => "Fallback nameserver used when name server 1 is not provided.",),
    "Nameserver2" => array("FriendlyName" => "Default Name Server 2", "Type" => "text", "Description" => "Fallback nameserver used when name server 2 is not provided.",),
    "Nameserver3" => array("FriendlyName" => "Default Name Server 3", "Type" => "text", "Description" => "Fallback nameserver used when name server 3 is not provided.",),
    "Nameserver4" => array("FriendlyName" => "Default Name Server 4", "Type" => "text", "Description" => "Fallback nameserver used when name server 4 is not provided.",),
  );
  if (!PtispConfig::isTaxIdEnabled()) {
    $options = PtispConfig::getCustomfieldDropdownOptions($params);
    $configarray["Vatcustom"] = array("FriendlyName" => "Tax ID Custom Field *", "Type" => "dropdown", "Description" => "Select the client custom field that stores the Tax ID. This field appears because the WHMCS built-in Tax ID feature is not enabled.", "Options" => $options, "Default" => "");
  }
  $configarray["PendingEmailTemplate"] = array(
    "FriendlyName" => "Pending Contact Verification Email",
    "Type" => "dropdown",
    "Options" => PtispConfig::getPendingEmailTemplateOptions(),
    "Default" => "",
    "Description" => "Email sent to the client immediately after registration when the domain is awaiting contact verification. Leave as 'None' to send no email until the domain is active.<br>To create a template, go to <a href=\"configemailtemplates.php\" target=\"_blank\">Email Templates</a> and create a new <strong>Domain</strong> type template.",
  );
  return $configarray;
}

function ptisp_TransferSync($params) {
  $api = new PtispApiClient($params["Username"], $params["Hash"]);
  $result = $api->getDomainInfo($params["sld"] . "." . $params["tld"]);

  $values = array();
  if ($result["result"] != "ok") {
    $values["error"] = empty($result["message"]) ? "unknown" : $result["message"];
  } else if ($result["data"]["status"] === PtispConfig::DOMAIN_STATUS_OK || $result["data"]["status"] === PtispConfig::DOMAIN_STATUS_ACTIVE) {
    $values["expirydate"] = $result["data"]["expires"];
    $values["completed"] = true;
  }

  return $values;
}

function ptisp_Sync($params) {
  $api = new PtispApiClient($params["Username"], $params["Hash"]);
  $result = $api->getDomainInfo($params["sld"] . "." . $params["tld"]);

  $values = array();
  if ($result["result"] != "ok") {
    $values["error"] = empty($result["message"]) ? "unknown" : $result["message"];
  } else if (!empty($result["data"]["expires"]) && !empty($result["data"]["status"])) {
    $isActive = $result["data"]["status"] == PtispConfig::DOMAIN_STATUS_OK
             || $result["data"]["status"] == PtispConfig::DOMAIN_STATUS_ACTIVE;
    $isPt = $params["tld"] === 'pt' || substr($params["tld"], -3) === '.pt';
    $ownedByPtisp = !$isPt || ($result["data"]["registrarid"] ?? '') == PtispConfig::REGISTRAR_ID_PT;
    if ($isActive && $ownedByPtisp) {
      $values["expirydate"] = $result["data"]["expires"];
      $values["active"] = true;

      // Only trigger the confirmation email if contact verification had explicitly
      // blocked this registration. Domains that registered immediately (no pending
      // marker) are not affected.
      $domainId = (int) ($params["domainid"] ?? 0);
      if ($domainId && ContactVerificationTracker::isPending($domainId)) {
        $rows = ContactVerificationTracker::markVerified($domainId);
        if ($rows > 0) {
          localAPI("SendEmail", array(
            "messagename" => PtispConfig::EMAIL_REGISTRATION_CONFIRMATION,
            "id" => $domainId,
          ));
        }
      }
    }
  }

  return $values;
}

function ptisp_GetContactDetails($params) {
  $api = new PtispApiClient($params["Username"], $params["Hash"]);
  $result = $api->getDomainContacts($params["sld"] . "." . $params["tld"]);

  $values = array();
  if ($params["tld"] === 'pt' || substr($params["tld"], -3) === '.pt') {
    $values["Tech"]["Nic"] = $result["contact"]["nic"];
    $values["Tech"]["Name"] = $result["contact"]["name"];
    $values["Tech"]["Street"] = $result["contact"]["street"];
    $values["Tech"]["City"] = $result["contact"]["city"];
    $values["Tech"]["Postal"] = $result["contact"]["postal"];
    $values["Tech"]["Country"] = $result["contact"]["country"];
    $values["Tech"]["Email"] = $result["contact"]["email"];
    $values["Tech"]["Phone"] = $result["contact"]["phone"];
    $values["Tech"]["Id"] = $result["contact"]["id"];
  }

  return $values;
}

function ptisp_SaveContactDetails($params) {
  $api = new PtispApiClient($params["Username"], $params["Hash"]);
  $sld = $params["sld"];
  $tld = $params["tld"];
  $result = array();
  $nichandle = null;

  if ($tld === 'pt' || substr($tld, -3) === '.pt') {
    if (empty($params["contactdetails"]["Tech"]["Nic"])) {
      $par = array(
        "name" => PtispApiClient::utf8ToUnicode($params["contactdetails"]["Tech"]["Name"]),
        "company" => $params["companyname"],
        "vat" => $params["contactdetails"]["Tech"]["Id"],
        "postalcode" => $params["contactdetails"]["Tech"]["Postal"],
        "country" => $params["contactdetails"]["Tech"]["Country"],
        "address" => PtispApiClient::utf8ToUnicode($params["contactdetails"]["Tech"]["Street"]),
        "phone" => $params["contactdetails"]["Tech"]["Phone"],
        "mail" => PtispApiClient::utf8ToUnicode($params["contactdetails"]["Tech"]["Email"]),
        "city" => PtispApiClient::utf8ToUnicode($params["contactdetails"]["Tech"]["City"])
      );
      $result = $api->createContact("{$sld}.{$tld}", $par);
      $nichandle = $result["nichandle"] ?? null;
    } else {
      $nichandle = $params["contactdetails"]["Tech"]["Nic"];
    }
  } else {
    $par = array(
      "name" => PtispApiClient::utf8ToUnicode($params["contactdetails"]["Registrant"]["Name"]),
      "company" => $params["contactdetails"]["Registrant"]["Company"],
      "postalcode" => $params["contactdetails"]["Registrant"]["Postal"],
      "country" => $params["contactdetails"]["Registrant"]["Country"],
      "address" => PtispApiClient::utf8ToUnicode($params["contactdetails"]["Registrant"]["Street"]),
      "phone" => $params["contactdetails"]["Registrant"]["Phone"],
      "mail" => PtispApiClient::utf8ToUnicode($params["contactdetails"]["Registrant"]["Email"]),
      "city" => PtispApiClient::utf8ToUnicode($params["contactdetails"]["Registrant"]["City"])
    );
    $result = $api->createContact("{$sld}.{$tld}", $par);
    $nichandle = $result["nichandle"] ?? null;
  }

  if (!empty($nichandle)) {
    $result = $api->updateContact("{$sld}.{$tld}", $nichandle);
  }

  $values["error"] = $result["message"] ?? '';
  return $values;
}

function ptisp_TransferDomain($params) {
  $api = new PtispApiClient($params["Username"], $params["Hash"]);
  $result = $api->transferDomain($params["sld"] . "." . $params["tld"], $params["eppcode"]);

  $values = array();
  if ($result["result"] != "ok") {
    $values["error"] = empty($result["message"]) ? "unknown" : $result["message"];
  }

  return $values;
}

function ptisp_GetNameservers($params) {
  $api = new PtispApiClient($params["Username"], $params["Hash"]);
  $result = $api->getDomainInfo($params["sld"] . "." . $params["tld"]);

  $values = array();
  if ($result["result"] != "ok") {
    $values["error"] = empty($result["message"]) ? "unknown" : $result["message"];
  } else {
    $values["ns1"] = $result["data"]["ns"][0];
    $values["ns2"] = $result["data"]["ns"][1];
    $values["ns3"] = $result["data"]["ns"][2];
    $values["ns4"] = $result["data"]["ns"][3];
  }

  return $values;
}

function ptisp_SaveNameservers($params) {
  $api = new PtispApiClient($params["Username"], $params["Hash"]);
  $result = $api->saveNameservers(
    $params["sld"] . "." . $params["tld"],
    array($params["ns1"], $params["ns2"], $params["ns3"], $params["ns4"])
  );

  $values = array();
  if ($result["result"] != "ok") {
    $values["error"] = empty($result["message"]) ? "unknown" : $result["message"];
  }

  return $values;
}

function ptisp_RenewDomain($params) {
  $api = new PtispApiClient($params["Username"], $params["Hash"]);
  $result = $api->renewDomain($params["sld"] . "." . $params["tld"], $params["regperiod"]);

  $values = array();
  if ($result["result"] != "ok") {
    $values["error"] = empty($result["message"]) ? "unknown" : $result["message"];
  }

  return $values;
}

function ptisp_RegisterDomain($params) {
  $values = array();
  $fallback = $params["DisableFallback"];

  $tld = $params["tld"];
  $sld = $params["sld"];
  $regperiod = $params["regperiod"];
  $domain = "{$sld}.{$tld}";

  if (PtispConfig::isTaxIdEnabled()) {
    $vatid = trim($params["tax_id"]);
  } else {
    $vatid = PtispConfig::getCustomTaxId($params);
    if (is_null($vatid)) {
      $values["error"] = "Cannot get the Tax ID. Please check the 'Tax ID Custom Field' setting in the module configuration.";
      return $values;
    }
  }

  $par = array();
  $registrantNicHandle = null;
  $techNicHandle = $params["Nichandle"] ?? null;

  if (!empty($params["additionalfields"]["Nichandle"])) {
    $registrantNicHandle = $params["additionalfields"]["Nichandle"];
  } else if (!empty($vatid)) {
    $phone = $params["fullphonenumber"] ?? ('+' . $params["phonecc"] . '.' . $params["phonenumber"]);
    $par = array("name" => $params["firstname"] . " " . $params["lastname"], "company" => $params["companyname"], "nif" => $vatid, "postalcode" => $params["postcode"], "country" => $params["country"], "address" => $params["address1"], "phone" => $phone, "mail" => $params["email"], "city" => $params["city"]);
  } else if ($fallback === "on") {
    $values["error"] = "Invalid Tax ID";
    return $values;
  }

  if (!empty($params["additionalfields"]["Visible"])) {
    $par['visible'] = ($params["additionalfields"]["Visible"] == 'on' ? true : false);
  }

  $par["ns1"] = $params["ns1"];
  $par["ns2"] = $params["ns2"];
  $par["ns3"] = $params["ns3"];
  $par["ns4"] = $params["ns4"];

  if (empty($params["ns1"]) && !empty($params["Nameserver"])) {
    $par["ns1"] = $params["Nameserver"];
  }
  if (empty($params["ns2"]) && !empty($params["Nameserver2"])) {
    $par["ns2"] = $params["Nameserver2"];
  }
  if (empty($params["ns3"]) && !empty($params["Nameserver3"])) {
    $par["ns3"] = $params["Nameserver3"];
  }
  if (empty($params["ns4"]) && !empty($params["Nameserver4"])) {
    $par["ns4"] = $params["Nameserver4"];
  }

  if (!empty($registrantNicHandle)) {
    $par["contact"] = $registrantNicHandle;
  }

  if (!empty($techNicHandle)) {
    $par["nichandle"] = $techNicHandle;
  }

  $api = new PtispApiClient($params["Username"], $params["Hash"]);
  $result = $api->registerDomain($domain, $regperiod, $par);

  if ($result["result"] != "ok") {
    $values["error"] = empty($result["message"]) ? "unknown" : $result["message"];
  } else {
    if (($result["data"] ?? null) === PtispConfig::DOMAIN_STATUS_PENDING_CONTACT_VERIFICATION) {
      $domainId = (int) ($params["domainid"] ?? 0);
      if ($domainId) {
        ContactVerificationTracker::markPending($domainId);
      }
      $values["pending"] = true;
    }
  }

  return $values;
}
