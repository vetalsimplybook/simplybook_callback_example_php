Simplybook API Callback Example
===============================

This code is a callback example of Simplybook.me API
Please find detailed info [here](https://simplybook.me/api/developer-api/tab/doc_api).
We can use Callback API in order to get details about event, for example, booking creation or change

---
The callback data from Simplybook will be presented in the raw post data in JSON format. The following fields are available:
```
booking_id - Booking ID
booking_hash - Booking hash
company - Your company login
notification_type - Notification type. Can be 'create', 'cancel', 'notify', 'change'
```
---

This example shows us that using Callback API we can get detailed info about booking and save it in the local database:
1) get details via callback
2) connect to Simplybook.me Company public service API using external [JsonRPC library](https://github.com/fguillot/JsonRPC)
3) get booking details using [getBookingsDetails](https://simplybook.me/api/developer-api/tab/doc_api#getBookingDetailsdefault) method
4) connect SQLite data to the local database
5) add booking details to the database


Please note: you can use another JsonRPC library or any other library in your software
This example is created in order to show Callback API possibilities