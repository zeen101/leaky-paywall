=== Leaky Paywall for WordPress ===
Contributors: layotte, endocreative
Tags: magazine, issue, manager, paywall, leaky
Requires at least: 3.0
Tested up to: 4.1
Stable tag: 2.0.6

A premium leaky paywall add-on for WordPress. More info at http://leakypw.com

== Description ==

A premium leaky paywall add-on for WordPress. More info at http://leakypw.com

== Installation ==

1. Upload the entire `issuem-leaky-paywall` folder to your `/wp-content/plugins/` folder.
1. Go to the 'Plugins' page in the menu and activate the plugin.

== Frequently Asked Questions ==

= What are the minimum requirements for zeen101's Leaky Paywall? =

You must have:

* WordPress 3.3 or later
* PHP 5

= How is zeen101's Leaky Paywall Licensed? =

* Leaky Paywall for WordPress is GPL

== Changelog ==
= 2.0.6 =
* Pruned unused multisite settings
* Fixed JS bug

= 2.0.5 =
* Adding filters for demo.zeen101.com
* Removing unused code from BETA build

= 2.0.4 =
* Adding currency options for USD, GBP, and EUR

= 2.0.3 =
* Adding PayPal IPN txn_type case for max failed payments and suspended payments
* Fixed bug with PayPal IPNs being sent with no item_number field
* Properly trim search arguments on Subscribers table

= 2.0.2 =
* Better error reporting for payment processing
* Separated subscribe and login url replacement arguments
* Manual payment method is default option on subscriber table form now
* Force lowercase for status during bulk imports
* Switching back from wp_loaded to wp action hook
* Adding some styling, fixed bug in return variables for PayPal
* Adding options to show all WP users in subscriber table
* Setup update script to ensure previous versions installed stay on 'passwordless' login method, while new insta
* PayPal updates, to work better for people without PayPal accounts
* Adding language files
* Added ability to add/edit subscribers manually with correct Payment Gateway information
* Added better search capabilities for email addresses
* Added filter to change which roles get access to content without being a subscriber, preliminary work for PayP

= 2.0.1 =
* Fixing bug in calling Leaky Paywall class method

= 2.0.0 =
* Fixing save meta box bug
* Updating IssueM references to point to zeen101
* Fixed bug in visibility saving for custom post types, added new action for bbpress functionality
* Fixing PayPal Sandbox bug
* Adding Pay with PayPal text to subscription options
* Few subscriber table bugs, new subscriber update
* Removing debug output for testing
* Updating Stripe API, enabling subscription upgrades through Stripe
* Fixing bulk import with level-id, Adding mode arg to get subscriber by hash function
* Fixing bug in single() test during processing, and per-post visibility, and enqueueing scripts properly on new content
* Removing testing line for updater
* Removing some duplicate code, and return default restrictions if no subscriber ID is set
* Modified output for overridden pages, changed how issuem_leaky_paywall_attempt_login attempst to log in users, moved around text for non-valid accounts when logging in and needing to subscribe still
* Adding metabox to all available post types to override leaky paywall defaults
* Migrated all users to use WP user meta for all LP meta, debuging new cookie setup too
* Recommend Merchant ID over PayPal Email address, fixed a paypal PDT bug
* Extra security when creating usernames in WP
* Re-organize the Settings page a bit, add some better UI
* Adding details to subscription options to explain expiration, added GUI for updating cookie expiration, moved functions to a hook that happens sooner to prevent cookie warnings, fixed bug in susbcriber table (everyone listed as no-plan), setup better stripe and paypal processing
* Adding multilevel Integration
* Modified how the excerpt and paywall content is output and fixed a bug caused by 3rd party plugins calling content/excerpt functions in WP
* Do not block login and subscription pages if page type is being blocked. Adds ability to modify usernames and sets usernaes based on front of email, not whole email
* Completely modified LP to use WordPress Users Table... added migration functionality to move existing users to WP Users table, modified all functions to use WordPRess Users and Meta Tables and functions.

= 1.2.0 =
* Added new function to verify login hashes
* Moved login hash check inside of process_request function
* Added check for subscriber session to add to cookie if cookie is empty
* Set Stripe API version to latest version before they added multi-subscriptions (until we can handle it gracefully)

= 1.1.8=
* FFixing issue with non-recurring payments not adding to subscribers table
* Verify Stripe class has not been initiated before including Stripe SDK
* Verify PHP Session has not been initiated before starting a session

= 1.1.7 =
* Fixed bug causing some sites to not register logged in users
* Fixed empty/isset bug

= 1.1.6 =
* Major login redux/bug fix
* Added ability to restricted PDF downloads to subscribers
* Modified Subscription shortcode to offer login option to reduce confusion
* Modified subscription shortcode output to not rely on CSS to hide multiple unused shortcodes
* Removing my IPN notification debug code

= 1.1.5 =
* Removed issuem dependency for License Activated
* Fixed license_key activation workflow
* Fixed bug in member table pagination
* Removing invalid file

= 1.1.4 =
* Fixed bug in login link generation for sites not using permalinks

= 1.1.3 =
* Fixed bug in subscribe shortcode

= 1.1.2 =
* Fixed bugs related to licensing system

= 1.1.1 =
* Fixed bug causing articles to be duplicated in the free article count
* Added manual COOKIE setting
* Fixed typo in filter
* Fixed bug in stripe recurring setting UX
* Added options to shortcode to deal with multipl subscription options, and css to hide the extra subscription

= 1.1.0 =
* Fixed cookie expiration bug
* Updated Stripe SDK
* Added a few actions during update/add subscriber process
* Fixed selected() status on member dashboard
* Added subscriber page
* Bulk import
* Add/edit existing subscribers
* Added paypal as a gateway
* Moved Leaky Paywall out of IssueM as it's own menu
* Fixed typo in Stripe currency filter
* Added extra live/test mode SELECT query checks

= 1.0.2 =
* Extended zeen101's Leaky Paywall add-on to work without IssueM
* Fixed a few typos
* Fixed bug with using the same email address in Live or Test mode with Stripe.

= 1.0.1 =
* Fixed bug to allow changing charge description
* Fixed bug preventing users from logging out of subscription
* Fixed bug internationalization text domains

= 1.0.0 =
* Initial Release

== License ==

Leaky Paywall for WordPress
Copyright (C) 2011 The Complete Website, LLC.

This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program.  If not, see <http://www.gnu.org/licenses/>.
