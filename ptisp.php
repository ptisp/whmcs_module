<?php

require_once("RestRequest.inc.php");

function ptisp_getConfigArray() {
    $configarray = array(
        "Username" => array("Type" => "text", "Size" => "20", "Description" => "Enter your username here",),
        "Hash" => array("Type" => "password", "Size" => "100", "Description" => "Enter your access hash here",),
    );
    return $configarray;
}

function ptisp_GetNameservers($params) {
    $username = $params["Username"];
    $password = $params["Hash"];
    $tld = $params["tld"];
    $sld = $params["sld"];

    $request = new RestRequest('https://api.ptisp.pt/domains/' . $sld . "." . $tld . '/info', 'GET');
    $request->setUsername($username);
    $request->setPassword($password);
    $request->execute();

    $result = json_decode($request->getResponseBody(), true);

    if ($result['result'] != "ok") {
        $values["error"] = $result['error'];
    } else {
        $values["ns1"] = $result['data']['ns'][0];
        $values["ns2"] = $result['data']['ns'][1];
        $values["ns3"] = $result['data']['ns'][2];
        $values["ns4"] = $result['data']['ns'][3];
    }

    return $values;
}

function ptisp_SaveNameservers($params) {
    $username = $params["Username"];
    $password = $params["Hash"];
    $tld = $params["tld"];
    $sld = $params["sld"];
    $nameserver1 = $params["ns1"];
    $nameserver2 = $params["ns2"];
    $nameserver3 = $params["ns3"];
    $nameserver4 = $params["ns4"];

    $request = new RestRequest('https://api.ptisp.pt/domains/' . $sld . "." . $tld . '/update/ns/' . $nameserver1 . '/' . $nameserver2 . '/' . $nameserver3 . '/' . $nameserver4, 'GET');
    $request->setUsername($username);
    $request->setPassword($password);
    $request->execute();

    $result = json_decode($request->getResponseBody(), true);

    if ($result['result'] != "ok") {
        $values["error"] = $result['error'];
    }

    return $values;
}

function ptisp_RegisterDomain($params) {
    $username = $params["Username"];
    $password = $params["Hash"];
    $tld = $params["tld"];
    $sld = $params["sld"];
    $regperiod = $params["regperiod"];
    $nameserver1 = $params["ns1"];
    $nameserver2 = $params["ns2"];
    $nameserver3 = $params["ns3"];
    $nameserver4 = $params["ns4"];
    # Registrant Details
    $RegistrantFirstName = $params["firstname"];
    $RegistrantLastName = $params["lastname"];
    $RegistrantAddress1 = $params["address1"];
    $RegistrantAddress2 = $params["address2"];
    $RegistrantCity = $params["city"];
    $RegistrantStateProvince = $params["state"];
    $RegistrantPostalCode = $params["postcode"];
    $RegistrantCountry = $params["country"];
    $RegistrantEmailAddress = $params["email"];
    $RegistrantPhone = $params["phonenumber"];
    # Admin Details
    $AdminFirstName = $params["adminfirstname"];
    $AdminLastName = $params["adminlastname"];
    $AdminAddress1 = $params["adminaddress1"];
    $AdminAddress2 = $params["adminaddress2"];
    $AdminCity = $params["admincity"];
    $AdminStateProvince = $params["adminstate"];
    $AdminPostalCode = $params["adminpostcode"];
    $AdminCountry = $params["admincountry"];
    $AdminEmailAddress = $params["adminemail"];
    $AdminPhone = $params["adminphonenumber"];

    $request = new RestRequest('https://api.ptisp.pt/domains/' . $sld . "." . $tld . '/register/' . $regperiod, 'POST');
    $request->setUsername($username);
    $request->setPassword($password);
    $request->execute(array("ns" => $params["ns1"]));

    $result = json_decode($request->getResponseBody(), true);

    if ($result['result'] != "ok") {
        $values["error"] = $result['error'];
    }

    return $values;
}

?>