=== Leaky Paywall for WordPress ===
Contributors: zeen101, layotte, pericson, endocreative
Tags: magazine, issue, manager, paywall, leaky
Requires at least: 3.3
Tested up to: 4.6
Stable tag: 4.0.4
Tags: leaky paywall, membership, metered paywall, metered access 
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

The #1 metered paywall for WordPress. Sell subscriptions without sacrificing search and social visibility. 

== Description ==

[Leaky Paywall](https://zeen101.com/leaky-paywall) is perfect for bloggers, news, and magazine sites that need content to be discovered in Google search and shared socially on Facebook and the rest. Set your meter to allow X number of free articles before requiring a subscription.

Pioneered by the Financial Times, popularized by the New York Times and used by 1000’s of publishers large and small, the metered paywall is now the most successful paywall approach in the publishing industry.

**Leaky Paywall integrates with**

* [IssueM](http://zeen101.com/issuem) issue publishing: Our IssueM plugin allows publishers to manage live web issues that are mobile, search engine, and social sharing friendly. Issues and articles reach today’s modern reader. Now you can promote and sell subscriptions to special or traditional recurring issues. 

* iOS and Android apps! Leaky Paywall integrates with [UniPress](http://getunipress.com) to give your subscribers native mobile apps for the best reading experience available today. Your subscribers will have automatic access to your content in the app after subscribing with Leaky Paywall. All content and apps are managed right in WordPress for true 1 click publishing.

**Features**

* Metered paywall: Let your readers read X number of free articles before they need to subscribe
* Get found in Google: Allows all your content to be indexed by search engines
* Viral sharing: All your articles can be shared 1000s of times in social media
* Sell live web issues: Integrates with [IssueM](https://zeen101.com/issuem) to sell access to individual special issues or traditional weekly, monthly, quarterly, etc issues
* iOS and Android app publishing: Integrates with [UniPress](https://getunipress.com/) to easily give your subscribers access to apps and sell subscriptions in the app stores
* Build your email list with a Free subscription level
* Granular content restriction: Optionally set any article as subscriber only or always free/public
* Much more

**Add-ons available here [https://zeen101.com/leakypaywall/leaky-paywall-add-ons/](https://zeen101.com/leakypaywall/leaky-paywall-add-ons/)**

* Native iOS and Android apps 
* Offer multiple subscription levels 
* Coupons
* Gift Subscriptions
* IP Exceptions
* Corporate subscriptions via domain name EG abcbiz.com or top level domain EG .edu, .gov
* Reporting Tool
* File restrictions
* MailChimp/MailPoet subscriber list signups
* Article Countdown Nag							
* More

For documentation see: [https://zeen101.com/documentation](http://zeen101.com/documentation)

We love contributions! Head over to our GitHub page if your feeling inspired [https://github.com/zeen101/leaky-paywall](https://github.com/zeen101/leaky-paywall)


A plugin for WordPress that allows you to sell metered access to your content. More info at [https://zeen101.com/](https://zeen101.com/)

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

= 4.0.4 =
* Do not show Payment Information for non-recurring payments

= 4.0.3 =
* Fix a bug in Stripe onetime payment, causing HUGE payments
* Fix credit card form display
* Style updates and code cleanup

= 4.0.2 =
* Set defaults for visibility metaboxes
* Fixing bug w/ Stripe cancellations not displaying content if not yet expired
* Checking for zero based level ID in registration form output w/ LP subscription shortcode

= 4.0.1 =
* Hide dashboard widget from non-admin users
* Set stripe key based on mode inside of has_user_paid function

= 4.0.0 =
* Payment Gateway Redux
* Free Subscriptions Updates
* Default Gateway Stripe Popup
* Added Credit Card Form for Stripe
* Cleanup PayPal processing
* Cleanup Bulk Import Code
* General Code Cleanup

= 3.8.0 =

* Add recent subscribers dashboard widget
* Add a check to the new subscriber email to make sure the user does not already exist in the system
* Add leaky_paywall_after_new_subscriber_form action on subscriber page
* Adding HTTP Version to PayPal API calls... fixing get current user function calls
* Fixing deprecated function call
* Add bulk csv uploader to subscribers pagee

= 3.7.0 =
* Setup 'rememberme' to true for set_auth_cookie calls
* Adding support for Zero-Decimal currencies in Stripe

= 3.6.0 =
* Update add ons page with publisher bundle banner
* Fixing bug when detecting empty users and level IDs
* Fixing bug when updating subscriber information
* Adding filters for new payment gateways
* Adding text to profile shortcode for unpaid accounts to resubscribe.

= 3.5.0 =
* Fixing bug in PayPal IPN EOT/Suspend/Cancel
* General Code cleanup
* Add after subscribe page redirect after a new user is created

= 3.4.0 =
* Adding text-domain
* Updating POT file

= 3.3.1 =
* Fixing Bulk Importer bug

= 3.3.0 =
* Remove reference to EDD updater class
* Add function to build post type row in access options when add new post type button is clicked
* Removing EDD Updater references

= 3.2.1 =
* Fix: Don't show deleted levels on frontend

= 3.2.0 =
* Release to Public on WordPress.org
* Create Add-ons page
* Fix for int/string compare on subscription options shortcode
* Expiration Fix
* Adding new filters and actions
* General code cleanup
* General styling cleanup

= 3.1.3 =
* Fixed bug causing updater to always say there is an update

= 3.1.2 =
* Fixed bug when manual payments are unlimited
* Fixed bug with trim() vs empty() calls on content availability

= 3.1.1 =
* Fixed different bug causing default restrictions to not be found

= 3.1.0 =
* Fixed bug causing default restrictions to not be found
* Fixed bug caused when multiple payments exist for a user
* Updated for Leaky Paywal Coupons
* Updated various texts and tags
* Fixed bugs related to emails not being sent
* Remove duplicate entries in subscriber table
* Fix php errors on subscription page
* Add multisite checks to subscriber table user data
* Only add blog id to subscriber query if is multisite
* Add level id to each subscription option subscribe box
* Adding all the Stripe supported currencies
* Fixing bug w/ Free Susbcription disappearing on upgrade, username changing on upgrade, email notifications not wokring, and email notifications not replacing args

= 3.0.0 =
* Initial Multisite Support

= 2.1.0 =
* Escaping add/remove_query_arg calls properly

= 2.0.9 =
* Adding Australian Dollars
* Add EDD SL plugin updater class and functionality to lp
* Add invalid notice to license key field when an incorrect license key is entered, or it is already active on another site
* Update new user notification for free users so the email sends

= 2.0.8 =
* Fixed bug when a new free user was updated

= 2.0.7 =
* Fixed currency bug in Stripe

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
