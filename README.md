# nf-rebidder

This script is used to automate "bidding" for people who participate in the Phone Sex website (https://www.niteflirt.com).

Typically you can "bid" your listing to show up higher on their Listings. When you bid a listing, you can bid any amount but you will only be charged 1 cent more than the listing next-in-line to yours. Other people can bid higher for your listing, which would eliminate your spot.

To be economical, and more consistent with your position in the listings you'd need to constantly bid your position during busy times. This script, assuming you don't run it every single minute, would be used to make sure your "postion" in the listing stays the same.

# usage

Install php, then configure the variables:

"listing": the URL of your listing that you'd be bidding on. The URL is the URL where you'd be able to select the categories for the listing that you would be bidding on

"position": Where you would like to be. Better to pick a number between 1 and 20. Best to pick a number between 1 and 12.

"login_name" and "login_password": Your credentials to sign into Niteflirt.

"debug": Provides insight into what the program is doing every time. If you don't want noise, set to false.

Once you've configured up things, execute:

```
php -f nf-rebidder.php
```

# limited liability

I am the author of this program. Usage of this program is _at your risk_. Any damage you do with it is not my fault.

Yes, it's dangerous to use this program. If there was not a warning about it then it wouldn't be dangerous or give you an unfair advantage.
