# PTisp Registrar Module for WHMCS

## Introduction

The PTisp Registrar Module integrates WHMCS with the PTisp domain registration platform, enabling resellers to register, transfer, and manage domains directly from their WHMCS installation. The module handles the full domain lifecycle, including contact creation, nameserver management, WHOIS visibility, and the contact verification workflow required by DNS.pt for certain `.pt` domain registrations.

## Requirements

- WHMCS 7.x or higher
- PHP 7.0 or higher
- PTisp client account with a generated API hash

## Installation

1. Upload the module files to `<whmcs_root>/modules/registrars/ptisp/`.
2. In the WHMCS Admin Area, navigate to the Domain Registrars settings and activate the **PTisp** registrar module.
3. Add the additional domain fields below to `<whmcs_root>/resources/domains/additionalfields.php`. Create the file if it does not exist — do **not** edit `dist.additionalfields.php` directly.

```php
$additionaldomainfields[".pt"][]      = array("Name" => "Nichandle", "LangVar" => "nichandle", "Type" => "text",    "Size" => "15", "Default" => "", "Required" => false, "Description" => "Nic-handle for domain registration");
$additionaldomainfields[".pt"][]      = array("Name" => "Visible",   "LangVar" => "visible",   "Type" => "tickbox",                                  "Required" => false, "Description" => "Show registrant data on WHOIS");
$additionaldomainfields[".com.pt"][]  = array("Name" => "Nichandle", "LangVar" => "nichandle", "Type" => "text",    "Size" => "15", "Default" => "", "Required" => false, "Description" => "Nic-handle for domain registration");
$additionaldomainfields[".com.pt"][]  = array("Name" => "Visible",   "LangVar" => "visible",   "Type" => "tickbox",                                  "Required" => false, "Description" => "Show registrant data on WHOIS");
$additionaldomainfields[".org.pt"][]  = array("Name" => "Nichandle", "LangVar" => "nichandle", "Type" => "text",    "Size" => "15", "Default" => "", "Required" => false, "Description" => "Nic-handle for domain registration");
$additionaldomainfields[".org.pt"][]  = array("Name" => "Visible",   "LangVar" => "visible",   "Type" => "tickbox",                                  "Required" => false, "Description" => "Show registrant data on WHOIS");
$additionaldomainfields[".edu.pt"][]  = array("Name" => "Nichandle", "LangVar" => "nichandle", "Type" => "text",    "Size" => "15", "Default" => "", "Required" => false, "Description" => "Nic-handle for domain registration");
$additionaldomainfields[".edu.pt"][]  = array("Name" => "Visible",   "LangVar" => "visible",   "Type" => "tickbox",                                  "Required" => false, "Description" => "Show registrant data on WHOIS");
```

## Configuration

Module configuration is accessed from the Domain Registrars settings in the WHMCS Admin Area. Click **Configure** next to the PTisp registrar to open the settings described below.

| Setting | Required | Description |
|---|---|---|
| `Username` | Required | Your PTisp email address. |
| `Hash` | Required | Your PTisp API hash. Generate it at [my.ptisp.pt/profile/apihash](https://my.ptisp.pt/profile/apihash). |
| `Default Technical Nic-handle` | Optional | Fallback tech-contact NIC-handle used when no per-domain NIC-handle is provided at order time. Clients can override this via the `Nichandle` additional domain field during checkout. |
| `Do not register domains with my PTisp profile data` | Optional | When enabled, registrations fail if the client provides neither a NIC-handle nor a valid Tax ID. When disabled, your PTisp reseller profile data is used as the registrant fallback. |
| `Tax ID Custom Field` | Conditional | Appears only when the WHMCS built-in Tax ID feature is disabled. Required in that case — select the client custom field that stores the Tax ID used for contact creation. |
| `Default Name Server 1` – `Default Name Server 4` | Optional | Four independent fallback nameserver slots. Each is applied only when the client leaves the corresponding field empty at registration. |
| `Pending Contact Verification Email` | Optional | Email template sent immediately after registration when the domain is awaiting contact verification. Must be a **Domain** type template. Leave as *None* to send no email until the domain becomes active. |

## Cron Configuration

The WHMCS domain sync cron is required for domain expiry date updates, transfer completion notifications, and the contact verification flow. Ensure the following are configured before going live.

**1. Enable domain sync**

Add `<whmcs_root>/crons/domainsync.php` to your server's cron scheduler. The cron can run as frequently as needed; 24 hours is the maximum recommended interval.

**2. Allow WHMCS to auto-update domain dates**

In the WHMCS Domains settings, ensure the option to prevent automatic domain date updates is **disabled**. When this option is active, domain expiry dates are not updated by the cron, which prevents the contact verification flow from completing and domains from transitioning to **Active** status.

## Contact Verification Flow

Some `.pt` domains require contact verification before they become active at the registry. When a domain registration is placed in pending contact verification status, the following process applies:

1. The domain is placed in **Pending** status in WHMCS.
2. The **Domain Registration Confirmation** email is suppressed until verification completes.
3. If a **Pending Contact Verification Email** template is configured, it is sent immediately to inform the client their domain is awaiting verification.
4. PTisp, acting as the registrar, sends the client a verification email requesting them to confirm their email address and verify their phone number via SMS.
5. Once the client completes both verifications, PTisp validates the contact and submits the domain registration to the .pt registry.
6. The domain transitions to **Active** status in WHMCS automatically as soon as the domain sync cron runs and detects that the domain is active at the registry. The **Domain Registration Confirmation** email is then sent to the client.

### Configuring the pending email template

1. Navigate to the Email Templates section in the WHMCS Admin Area.
2. Create a new template of type **Domain**. The template must belong to this category — templates of other types will not appear in the module configuration dropdown.
3. Select the template under **Pending Contact Verification Email** in the module configuration.
