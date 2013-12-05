=== IssueM - Leaky Paywall ===
Contributors: layotte
Tags: magazine, issue, manager, paywall, leaky
Requires at least: 3.0
Tested up to: 3.7
Stable tag: 1.1.2

A premium leaky paywall add-on for IssueM. More info at http://issuem.com

== Description ==

A premium leaky paywall add-on for IssueM. More info at http://issuem.com

== Installation ==

1. Upload the entire `issuem-leaky-paywall` folder to your `/wp-content/plugins/` folder.
1. Go to the 'Plugins' page in the menu and activate the plugin.

== Frequently Asked Questions ==

= What are the minimum requirements for IssueM's Leaky Paywall? =

You must have:

* WordPress 3.3 or later
* PHP 5

= How is IssueM's Leaky Paywall Licensed? =

* Like IssueM, Leaky Paywall is GPL

== Changelog ==
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
* Extended IssueM's Leaky Paywall add-on to work without IssueM
* Fixed a few typos
* Fixed bug with using the same email address in Live or Test mode with Stripe.

= 1.0.1 =
* Fixed bug to allow changing charge description
* Fixed bug preventing users from logging out of subscription
* Fixed bug internationalization text domains

= 1.0.0 =
* Initial Release

== License ==

IssueM - Leaky Paywall
Copyright (C) 2011 The Complete Website, LLC.

This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program.  If not, see <http://www.gnu.org/licenses/>.
