# shopping-cart-BoxBilling
BoxBilling 
=================================================
PlugnPay - SSv2 Method Payment Module for BoxBilling v4.21

***** IMPORTANT NOTES *****

This module is being provided "AS IS". Limited technical support assistance will be given to help diagnose/address problems with this module. The amount of support provided is up to PlugnPay's staff.

It is recommended if you experience a problem with this module, first seek assistance through this module's readme file, then check with the BoxBilling community at 'www.boxbilling.com', and if you are still unable to resolve the issue, contact us via PlugnPay's Online Helpdesk.

This module does not requires your server to have SSL abilities or CURL. BoxBilling will redirect the customer to a secure billing pages hosted by our payment gateay. The authorization will be done via PlugnPay's Smart Screens v2 payment method. The cart itself will store the customer's orders, but not its card data. This module is for processing the authorization only.

If you want to change the behavior of this module, please feel free to make changes to the files yourself. However, customized modules will not be provided support assistance.



- PlugnPaySS1.php is for the [legacy] Smart Screens v1 payment method (alpha) 
- PlugnPaySS2.php is for the [current] Smart Screens v2 payment method (alpha)
- PlugnPayAPI.php is for the API payment method (unfinished - DO NOT USE)

Installation:

For a default installation, simply upload the provided file into the normal payment module location (./bb-library/Payment/Adapter/) & then enable/configure the the module via the BoxBilling admin area.

For a custom installations, please refer to BoxBilling online documentation for how to manually install a module. (https://docs.boxbilling.com/en/latest/reference/extension.html#extension-payment-gateway)


USAGE NOTES:

1 - This is a bare bones payment module, so PnP only sends minimal data at time of authorization. The cart pretty much tracks all of the details itself.

2 - When configuring the payment module, the client only needs to enter their PnP username and the card types allowed. Everything else is either hard coded within the module or is handled directly by the cart/gateway itself.

3 - Once the module is configured/active, the payment option will only appear at time of checkout when, when payment is due.

4 - On some servers, you may need to enable the 'register_globals' settings within your php.ini file, in order for the 'parse_str' operation within the module to work correctly.

Also realize that this code itself is not very complex. Practically every person that has contacted us about errors found that some other code not associated with this contribution was responsible, or because their implementation was not properly set up or working.

Please do your own debugging before contacting anyone for assistance.

Updates:

11/30/2020
- [alpha] payment module released for Smart Screens v1 (PlugnPaySS2.php)
- module was designed/tested with BoxBilling v4.21
- single payment checkout works, with response data collection working.
- recurrent payment checkout is unavailble, but hope to offer it in a future update.
- the code is not production ready, but is far enough long for testing & feedback purposes.
- code still needs refinement, such as better validation & updating the invoice status as to paid
- [alpha] payment module for Smart Screens v2 was updated
- corrected the response data collection matter
- cleaned up some of the code's logic & removed some commented data
- code still needs refinement, such as better validation & updating the invoice status as to paid

11/27/2020
- [alpha] payment module released for Smart Screens v2 (PlugnPaySS2.php)
- module was designed/tested with BoxBilling v4.21
- single payment checkout works, but response data collection needs tweaking.
- recurrent payment checkout is unavailble, but hope to offer it in a future update.
- the code is not production ready, but is far enough long for testing & feedback purposes.

