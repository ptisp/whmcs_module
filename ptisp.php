<?php

//v2.2.10

require_once("RestRequest.inc.php");
use WHMCS\Database\Capsule;

function ptisp_getConfigArray($params) {
  $configarray = array(
    "Username" => array("FriendlyName" => "Username", "Type" => "text", "Size" => "20", "Description" => "Enter your username here.",),
    "Hash" => array("FriendlyName" => "Hash", "Type" => "password", "Size" => "100", "Description" => "Enter your access hash here.",),
    "DisableFallback" => array("FriendlyName" => "Do not create contacts with my PTisp profile data", "Contact", "Type" => "yesno", "Description" => "When this option is checked the module won't use your profile data on contact creation, whenever client's data is invalid."),
    "Nichandle" => array("FriendlyName" => "Default Technical Nic-handle", "Type" => "text", "Description" => "Default Tech contact used on domain registrations.",),
    "Nameserver" => array("FriendlyName" => "Default Name Server 1", "Type" => "text", "Description" => "Default nameserver to use in registration.",),
    "Nameserver2" => array("FriendlyName" => "Default Name Server 2", "Type" => "text", "Description" => "Default nameserver to use in registration.",),
  );
  if (!ptisp_isTaxIdEnabled()) {
    $options = ptisp_getCustomfieldDropdownOptions($params);
    $configarray["Vatcustom"] = array("FriendlyName" => "Tax ID Custom Field", "Type" => "dropdown", "Description" => "The custom field which stores the client's Tax ID.", "Options" => $options, "Default" => "");
  }
  return $configarray;
}

function ptisp_TransferSync($params) {
  $username = $params["Username"];
  $password = $params["Hash"];
  $tld = $params["tld"];
  $sld = $params["sld"];


  $request = new RestRequest("https://api.ptisp.pt/domains/" . $sld . "." . $tld . "/info", "GET");
  $request->setUsername($username);
  $request->setPassword($password);
  $request->execute();

  $result = json_decode($request->getResponseBody(), true);

  if ($result["result"] != "ok") {
    if(empty($result["message"])) {
      $values["error"] = "unknown";
    } else {
      $values["error"] = $result["message"];
    }
  } else if ($result["data"]["status"] === "ok" || $result["data"]["status"] === "active") {
    $values["expirydate"] = $result["data"]["expires"];
    $values["completed"] = true;
  }

  return $values;
}

function ptisp_Sync($params) {
  $username = $params["Username"];
  $password = $params["Hash"];
  $tld = $params["tld"];
  $sld = $params["sld"];


  $request = new RestRequest("https://api.ptisp.pt/domains/" . $sld . "." . $tld . "/info", "GET");
  $request->setUsername($username);
  $request->setPassword($password);
  $request->execute();

  $result = json_decode($request->getResponseBody(), true);

  if ($result["result"] != "ok") {
    if(empty($result["message"])) {
      $values["error"] = "unknown";
    } else {
      $values["error"] = $result["message"];
    }
  } else if(!empty($result["data"]["expires"]) && !empty($result["data"]["status"])) {
    if($result["data"]["status"] == "ok" || $result["data"]["status"] == "active") {
      $values["expirydate"] = $result["data"]["expires"];
      $values["active"] = true;
    }
  }

  return $values;
}

function ptisp_GetContactDetails($params) {
  $username = $params["Username"];
  $password = $params["Hash"];
  $tld = $params["tld"];
  $sld = $params["sld"];

  $request = new RestRequest("https://api.ptisp.pt/domains/" . $sld . "." . $tld . "/contacts/info", "GET");
  $request->setUsername($username);
  $request->setPassword($password);
  $request->execute();
  $result = json_decode($request->getResponseBody(), true);

  if (strpos($tld, "pt") !== false) {
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
  $username = $params["Username"];
  $password = $params["Hash"];
  $tld = $params["tld"];
  $sld = $params["sld"];

  $request = new RestRequest("https://api.ptisp.pt/domains/" . $sld . "." . $tld . "/contacts/create", "POST");
  $request->setUsername($username);
  $request->setPassword($password);

  if (strpos($tld, "pt") !== false) {
    if (empty($params["contactdetails"]["Tech"]["Nic"])) {
      $par = array(
        "name" => utf8ToUnicode($params["contactdetails"]["Tech"]["Name"]),
        "vat" => $params["contactdetails"]["Tech"]["Id"],
        "postalcode" => $params["contactdetails"]["Tech"]["Postal"],
        "country" => $params["contactdetails"]["Tech"]["Country"],
        "address" => utf8ToUnicode($params["contactdetails"]["Tech"]["Street"]),
        "phone" => $params["contactdetails"]["Tech"]["Phone"],
        "mail" => utf8ToUnicode($params["contactdetails"]["Tech"]["Email"]),
        "city" => utf8ToUnicode($params["contactdetails"]["Tech"]["City"])
      );
      $request->execute($par);
      $result = json_decode($request->getResponseBody(), true);
      $nichandle = $result["nichandle"];
    } else {
      $nichandle = $params["contactdetails"]["Tech"]["Nic"];
    }
  } else {
    $par = array(
      "name" => utf8ToUnicode($params["contactdetails"]["Registrant"]["Name"]),
      "company" => $params["contactdetails"]["Registrant"]["Company"],
      "postalcode" => $params["contactdetails"]["Registrant"]["Postal"],
      "country" => $params["contactdetails"]["Registrant"]["Country"],
      "address" => utf8ToUnicode($params["contactdetails"]["Registrant"]["Street"]),
      "phone" => $params["contactdetails"]["Registrant"]["Phone"],
      "mail" => utf8ToUnicode($params["contactdetails"]["Registrant"]["Email"]),
      "city" => utf8ToUnicode($params["contactdetails"]["Registrant"]["City"])
    );
    $request->execute($par);
    $result = json_decode($request->getResponseBody(), true);
    $nichandle = $result["nichandle"];
  }

  if (!empty($nichandle)) {
    $contact = $nichandle;
    $request = new RestRequest("https://api.ptisp.pt/domains/" . $sld . "." . $tld . "/contacts/update/" . $contact, "POST");
    $request->setUsername($username);
    $request->setPassword($password);
    $request->execute(array());
    $result = json_decode($request->getResponseBody(), true);
  }

  $values["error"] = $result["message"];

  return $values;
}

function ptisp_TransferDomain($params) {
    $username = $params["Username"];
    $password = $params["Hash"];
    $tld = $params["tld"];
    $sld = $params["sld"];
    $transfersecret = $params["eppcode"];

    $request = new RestRequest("https://api.ptisp.pt/domains/" . $sld . "." . $tld . "/transfer/", "POST");
    $request->setUsername($username);
    $request->setPassword($password);

    $request->execute(array("authcode" => $transfersecret));

    $result = json_decode($request->getResponseBody(), true);

    if ($result["result"] != "ok") {
        if (empty($result["message"])) {
            $values["error"] = "unknown";
        } else {
            $values["error"] = $result["message"];
        }
    }

    return $values;
}

function ptisp_GetNameservers($params) {
  $username = $params["Username"];
  $password = $params["Hash"];
  $tld = $params["tld"];
  $sld = $params["sld"];

  $request = new RestRequest("https://api.ptisp.pt/domains/" . $sld . "." . $tld . "/info", "GET");
  $request->setUsername($username);
  $request->setPassword($password);
  $request->execute();

  $result = json_decode($request->getResponseBody(), true);

  if ($result["result"] != "ok") {
    if(empty($result["message"])) {
      $values["error"] = "unknown";
    } else {
      $values["error"] = $result["message"];
    }
  } else {
    $values["ns1"] = $result["data"]["ns"][0];
    $values["ns2"] = $result["data"]["ns"][1];
    $values["ns3"] = $result["data"]["ns"][2];
    $values["ns4"] = $result["data"]["ns"][3];
  }

  return $values;
}

function ptisp_SaveNameservers($params) {
  $username = $params["Username"];
  $password = $params["Hash"];
  $tld = $params["tld"];
  $sld = $params["sld"];
  $nameserver1 = $params["ns1"];
  if ($params["ns2"])
    $nameserver2 = "/" . $params["ns2"];
  if ($params["ns3"])
    $nameserver3 = "/" . $params["ns3"];
  if ($params["ns4"])
    $nameserver4 = "/" . $params["ns4"];

  $request = new RestRequest("https://api.ptisp.pt/domains/" . $sld . "." . $tld . "/update/ns/" . $nameserver1 . $nameserver2 . $nameserver3 . $nameserver4, "GET");
  $request->setUsername($username);
  $request->setPassword($password);
  $request->execute();

  $result = json_decode($request->getResponseBody(), true);

  if ($result["result"] != "ok") {
    if(empty($result["message"])) {
      $values["error"] = "unknown";
    } else {
      $values["error"] = $result["message"];
    }
  }

  return $values;
}

function ptisp_RenewDomain($params) {
  $username = $params["Username"];
  $password = $params["Hash"];
  $tld = $params["tld"];
  $sld = $params["sld"];
  $regperiod = $params["regperiod"];

  $request = new RestRequest("https://api.ptisp.pt/domains/" . $sld . "." . $tld . "/renew/" . $regperiod, "POST");
  $request->setUsername($username);
  $request->setPassword($password);

  $request->execute(array());
  $result = json_decode($request->getResponseBody(), true);

  if ($result["result"] != "ok") {
    if(empty($result["message"])) {
      $values["error"] = "unknown";
    } else {
      $values["error"] = $result["message"];
    }
  }

  return $values;
}

function ptisp_RegisterDomain($params) {
  $username = $params["Username"];
  $password = $params["Hash"];
  $fallback = $params["DisableFallback"];

  $tld = $params["tld"];
  $sld = $params["sld"];
  $regperiod = $params["regperiod"];

  if (ptisp_isTaxIdEnabled()) {
    $vatid = $params["tax_id"];
  } else {
    $vatcustomfield = ptisp_getTaxIdCustomfieldRef($params);
    $vatid = !is_null($vatcustomfield) ? $params[$vatcustomfield] : null;
  }

  if (!empty($params["additionalfields"]["Nichandle"])) {
    $contact = $params["additionalfields"]["Nichandle"];
  } else if(!empty($vatid)){
    $request = new RestRequest("https://api.ptisp.pt/domains/" . $sld . "." . $tld . "/contacts/create", "POST");
    $request->setUsername($username);
    $request->setPassword($password);

    $phone = $params["fullphonenumber"] ?? ('+' . $params["phonecc"] . '.' . $params["phonenumber"]);
    $par = array("name" => $params["firstname"] . " " . $params["lastname"], "nif" => $vatid, "postalcode" => $params["postcode"], "country" => $params["country"], "address" => $params["address1"], "phone" => $phone, "mail" => $params["email"], "city" => $params["city"]);
    $request->execute($par);
    $result = json_decode($request->getResponseBody(), true);
    if ($result["result"] === "ok") {
      $contact = $result["nichandle"];
    } else {
      $values["error"] = $result["message"];
    }
  } else {
    $values["error"] = "Invalid VAT ID";
  }

  if (!empty($params["additionalfields"]["Visible"])) {
    $par['visible'] = ($params["additionalfields"]["Visible"] == 'on' ? true : false);
  }

  if($fallback !== "on" || ($fallback === "on" && !empty($contact))) {

    $par["ns1"] = $params["ns1"];
    $par["ns2"] = $params["ns2"];
    $par["ns3"] = $params["ns3"];
    $par["ns4"] = $params["ns4"];

    if (empty($params["ns1"]) && !empty($params["Nameserver"])) {
      $par["ns"] = $params["Nameserver"];
    }
    if (empty($params["ns2"]) && !empty($params["Nameserver2"])) {
      $par["ns2"] = $params["Nameserver2"];
    }


    if (!empty($contact)) {
      $par["contact"] = $contact;
    }

    if (!empty($params["Nichandle"])) {
      $par["nichandle"] = $params["Nichandle"];
    }

    $request = new RestRequest("https://api.ptisp.pt/domains/" . $sld . "." . $tld . "/register/" . $regperiod, "POST");
    $request->setUsername($username);
    $request->setPassword($password);
    $request->execute($par);

    $result = json_decode($request->getResponseBody(), true);

    if ($result["result"] != "ok") {
      if(empty($result["message"])) {
        $values["error"] = "unknown";
      } else {
        $values["error"] = $result["message"];
      }
    }
  } else if(!isset($values["error"]) || empty($values["error"])) {
    $values["error"] = "unknown";
  }

  return $values;
}

function utf8ToUnicode($str) {
  return preg_replace_callback("/./u", function ($m) {
    $ord = ord($m[0]);
    if ($ord <= 127) {
      return $m[0];
    } else {
      return trim(json_encode($m[0]), "\"");
    }
  }, $str);
}

function ptisp_getCustomfieldDropdownOptions($params) {
  try {
    $fields = Capsule::table("tblcustomfields")
      ->select()
      ->where("type", "=", "client")
      ->where("fieldtype", "=", "text")
      ->get();

    preg_match('/^customfields(\d+)$/', $params["Vatcustom"], $matches);
    //retrocompatible with old configuration settings
    if (isset($matches[1])) {
      $fieldName = ptisp_getCustomFieldName($matches[1]);
      $options = array($fieldName => $fieldName);
    } else {
      $options = array("" => "None");
    }

    foreach ($fields as $field) {
      $options[$field->fieldname] = $field->fieldname;
    }

    return $options;
  } catch (\Exception $e) {
    error_log($e->getMessage());
    return "";
  }
}

function ptisp_isTaxIdEnabled() {
  try {
    $setting = Capsule::table("tblconfiguration")
      ->select()
      ->where("setting", "=", "TaxIDDisabled")
      ->first();
    if (is_null($setting)) {
      $isTaxIdEnabled = false;
    } else {
      $isTaxIdEnabled = !$setting->value;
    }
    return $isTaxIdEnabled;
  } catch (\Exception $e) {
    error_log($e->getMessage());
    return null;
  }
}

function ptisp_getTaxIdCustomfieldRef($params) {
  $taxIdField = $params["Vatcustom"];
  $hasOldSetting = preg_match('/^customfields(\d+)$/', $taxIdField);

  //retrocompatible with old configuration settings
  if ($hasOldSetting) {
    return $taxIdField;
  }

  try {
    $field = Capsule::table("tblcustomfields")
      ->select()
      ->where("fieldname", "=", $taxIdField)
      ->first();
    if (is_null($field)) {
      return null;
    } else {
      return "customfields" . $field->sortorder;
    }
  } catch (\Exception $e) {
    error_log($e->getMessage());
    return null;
  }
}

function ptisp_getCustomFieldName($index) {
  try {
    $field = Capsule::table("tblcustomfields")
      ->select()
      ->where("sortorder", "=", $index)
      ->first();
    if (is_null($field)) {
      return null;
    } else {
      return $field->fieldname;
    }
  } catch (\Exception $e) {
    error_log($e->getMessage());
    return null;
  }
}

?>