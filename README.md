# NAB Transact DP Payment Gateway for Gravity Forms
Gravity Forms and NAB Transact make collecting credit card payments quick and painless! With the this Add-On you can capture credit card payments along with any additional data you want from the user right on your site, without sending the user to a 3rd party to complete the transaction.

## Seamless integration 
Automatically capture credit card payments with NAB Transact when a form is submitted.

## System requirements
The Gravity Forms NAB Transact Add-On requires Gravity Forms v1.6.2+, WordPress v3.3+ and a valid SSL Certificate installed and configured on your WordPress site.

## Public Test API Credentials
These should be populated in plugin settings page to test transactions coming through

Merchant ID: XYZ0010
Transaction Password: abcd1234

## NAB Transact Portal – Public Test Login Details
Login to see transactions coming through

NAB Transact Portal: https://demo.transact.nab.com.au/nabtransact 
Client ID: XYZ
Username: demo 
Password: abcd1234

## Test Creditcard number (test in sandbox environment)

Card Number: 4444333322221111
Card Type: VISA Card
CCV: 123
Card Expiry: 08 / 17 (or any date in the future)

### Credit Card Types Accepted by default via NAB merchant facility

• Visa
• MasterCard

### Payment amounts to simulate approved transactions: $1.00
$1.08
$105.00
$105.08
(or any total ending in 00, 08)

### Payment amounts to simulate declined transactions: $1.51
$1.05
$105.51
$105.05
(or any totals not ending in 00, 08)
