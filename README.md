# webservice-passbook

## Implentation with Silex in PHP

Passbook is an iOS 6 feature that manages boarding passes, movie tickets, retail coupons, & loyalty cards. Using the PassKit API, developers can register web services to automatically update content on the pass, such as gate changes on a boarding pass, or adding credit to a loyalty card.

Apple provides a specification for a REST-style web service protocol to communicate with Passbook, with endpoints to get the latest version of a pass, register / unregister devices to receive push notifications for a pass, and query for passes registered for a device.

>> This implentation respect the specification of Apple :
>> https://developer.apple.com/library/content/documentation/PassKit/Reference/PassKit_WebService/WebService.html
