ptisp.pt WHMCS Registrar module
============

PTisp Registrar module for WHMCS

Collaborations are welcome!

Contact us at dev at ptisp.pt if you need any help installing this module.

#Contributions
##### franciscomelo (May 8, 2013) - Added domain sync and transfer sync module.

Installation instructions to enable Sync:

 - Please add to the cron job the file "whmcs.installation"/crons/domainsync.php
   I advise to run it every 24 hours
 - Create an email template called Domain Transfer Completed
 - On the WHMCS-> General Settings -> Domains, uncheck the option Tick this box to not auto update any domain dates 
