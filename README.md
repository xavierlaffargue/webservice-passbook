# webservice-passbook

## Implentation with Silex in PHP

Passbook is an iOS 6 feature that manages boarding passes, movie tickets, retail coupons, & loyalty cards. Using the PassKit API, developers can register web services to automatically update content on the pass, such as gate changes on a boarding pass, or adding credit to a loyalty card.

Apple provides a specification for a REST-style web service protocol to communicate with Passbook, with endpoints to get the latest version of a pass, register / unregister devices to receive push notifications for a pass, and query for passes registered for a device.

> This implentation respect the specification of Apple :
> https://developer.apple.com/library/content/documentation/PassKit/Reference/PassKit_WebService/WebService.html

## Installation

Execute SQL file database.sql
Copy your certificate pem file in the directory "certificates"
Update the web/config.php with your login/pass...

## Requirement

Activate URL Rewriting in your server (for test use https://yourserver.net/index.php/hello/world if display Hello world it's ok)
HTTPS for a production environment
