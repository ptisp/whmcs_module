<?php

//v2.2.4

require_once("RestRequest.inc.php");

function ptisp_getConfigArray() {
  $configarray = array(
    "Username" => array("Type" => "text", "Size" => "20", "Description" => "Enter your username here",),
    "Hash" => array("Type" => "password", "Size" => "100", "Description" => "Enter your access hash here",),
    "Vatcustom" => array("Type" => "text", "Size" => "100", "Description" => "VAT customer's customfield name",),
    "DisableFallback" => array("Type" => "yesno", "Description" => "If customer data is invalid, domain registration will fail with fallback disabled. Fallback uses your info to register a domain when your customer's info is invalid",),
    "Nichandle" => array("Type" => "text", "Description" => "Specify your nichandle, it will be used as Tech Contact after a domain registration.",),
    "Nameserver" => array("Type" => "text", "Description" => "Default nameserver to use in registration.",),
  );
  return $configarray;
}

function ptisp_GetRegistrarLock($params) {
  $username = $params["Username"];
  $password = $params["Hash"];
  $tld = $params["tld"];
  $sld = $params["sld"];

  $request = new RestRequest("https://api.ptisp.pt/domains/" . $sld . "." . $tld . "/protection/lock", "GET");
  $request->setUsername($username);
  $request->setPassword($password);
  $request->execute();

  $result = json_decode($request->getResponseBody(), true);

  if($result["locked"] == "true") {
    $lockstatus = "locked";
  } else {
    $lockstatus = "unlocked";
  }

  return $lockstatus;
}

function ptisp_GetEPPCode($params) {
  $username = $params["Username"];
  $password = $params["Hash"];
  $tld = $params["tld"];
  $sld = $params["sld"];

  $request = new RestRequest("https://api.ptisp.pt/domains/" . $sld . "." . $tld . "/epp", "GET");
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
    $values["eppcode"] = $result["authcode"];
  }

  return $values;
}

function ptisp_SaveRegistrarLock($params) {
  $username = $params["Username"];
  $password = $params["Hash"];
  $tld = $params["tld"];
  $sld = $params["sld"];

	if ($params["lockenabled"]) {
		$lockstatus = "true";
	} else {
		$lockstatus = "false";
	}

  $request = new RestRequest("https://api.ptisp.pt/domains/" . $sld . "." . $tld . "/protection/lock/" . $lockstatus, "POST");
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
    $transfersecret = $params["transfersecret"];

    $request = new RestRequest("https://api.ptisp.pt/domains/" . $sld . "." . $tld . "/transfer/", "POST");
    $request->setUsername($username);
    $request->setPassword($password);

    $request->execute(array("authcode" => $transfersecret));

    $result = json_decode($request->getResponseBody(), true);

    error_log(print_r($result, true));

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


  if (!empty($params["additionalfields"]["Nichandle"])) {
    $contact = $params["additionalfields"]["Nichandle"];
  } else if (!empty($params[$params["Vatcustom"]])) {
    $request = new RestRequest("https://api.ptisp.pt/domains/" . $sld . "." . $tld . "/contacts/create", "POST");
    $request->setUsername($username);
    $request->setPassword($password);
    $par = array("name" => $params["firstname"], "nif" => $params[$params["Vatcustom"]], "postalcode" => $params["postcode"], "country" => $params["country"], "address" => $params["address1"], "phone" => $params["phonenumber"], "mail" => $params["email"], "city" => $params["city"]);
    $request->execute($par);
    $result = json_decode($request->getResponseBody(), true);
    if ($result["result"] === "ok") {
      $contact = $result["nichandle"];
    } else {
      $values["error"] = $result["message"];
    }
  }

  if($fallback !== "on" || ($fallback === "on" && !empty($contact))) {

    $par = array("ns" => $params["ns1"]);

    if (empty($params["ns1"]) && !empty($params["Nameserver"])) {
      $par["ns"] = $params["Nameserver"];
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

?>
