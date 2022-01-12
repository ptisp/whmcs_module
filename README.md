ptisp.pt WHMCS Registrar module
============

[PTisp](https://www.ptisp.pt) Registrar module for [WHMCS](https://www.whmcs.com/)

Collaborations are welcome!

Contact us at dev at ptisp.pt if you need any help installing this module.

#Installation
 * Upload the module's files to the folder `whmcs_root/modules/registrars/ptisp/`
 * Add the following code to `whmcs_root/includes/additionaldomainfields.php` file (WHMCS v6.X) OR add to `whmcs_root/resources/domains/dist.additionalfields.php` (WHMCS v7 +)

``` php
$additionaldomainfields[".pt"][] = array("Name" => "Nichandle", "LangVar" => "nichandle", "Type" => "text", "Size" => "15", "Default" => "", "Required" => false, "Description" => "Nic-handle for domain registration",);
$additionaldomainfields[".pt"][] = array("Name" => "Visible", "LangVar" => "visible", "Type" => "tickbox", "Required" => false, "Description" => "Show registrant data on whois",);
$additionaldomainfields[".com.pt"][] = array("Name" => "Nichandle", "LangVar" => "nichandle", "Type" => "text", "Size" => "15", "Default" => "", "Required" => false, "Description" => "Nic-handle for domain registration",);
$additionaldomainfields[".com.pt"][] = array("Name" => "Visible", "LangVar" => "visible", "Type" => "tickbox", "Required" => false, "Description" => "Show registrant data on whois",);
$additionaldomainfields[".org.pt"][] = array("Name" => "Nichandle", "LangVar" => "nichandle", "Type" => "text", "Size" => "15", "Default" => "", "Required" => false, "Description" => "Nic-handle for domain registration",);
$additionaldomainfields[".org.pt"][] = array("Name" => "Visible", "LangVar" => "visible", "Type" => "tickbox", "Required" => false, "Description" => "Show registrant data on whois",);
$additionaldomainfields[".edu.pt"][] = array("Name" => "Nichandle", "LangVar" => "nichandle", "Type" => "text", "Size" => "15", "Default" => "", "Required" => false, "Description" => "Nic-handle for domain registration",);
$additionaldomainfields[".edu.pt"][] = array("Name" => "Visible", "LangVar" => "visible", "Type" => "tickbox", "Required" => false, "Description" => "Show registrant data on whois",);

```

###Module configuration:
 * `Username` - PTisp customer username. (usually your email address)
 * `Hash` - API Hash, you may find it at https://my.ptisp.pt/profile/apihash
 * `Default Technical Nic-handle` - Nichandle to be used as tech contact.
 * `Default Name Server 1` - First default nameserver used on registrations.
 * `Default Name Server 2` - Second default nameserver used on registrations.
 * `Do not create contacts with my PTisp profile data` - By default the module uses the nichandle specified in the whmcs domain order (additionaldomainfields makes this possible), if there isn't any it will try to create a contact using your customer's profile data if this fails it will register the domain using your reseller contact. If you want to disable this last fallback to your reseller data, check this checkbox.
 * `Tax ID Custom Field` - The customfield which stores client's Vat Number/Tax ID. Available only if the "Customer Tax IDs/VAT Number" setting is disabled or versions prior to WHMCS 7.7.


#Contributions
##### franciscomelo (May 8, 2013) - Added domain sync and transfer sync module.

Installation instructions to enable Sync:

 - Please add to the cron job the file "whmcs.installation"/crons/domainsync.php
   I advise to run it every 24 hours
 - Create an email template called Domain Transfer Completed
 - On the WHMCS-> General Settings -> Domains, uncheck the option Tick this box to not auto update any domain dates
