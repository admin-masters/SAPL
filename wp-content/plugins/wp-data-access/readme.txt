=== WP Data Access – No-Code App Builder with Tables, Forms, Charts & Maps ===
Plugin URI: https://wpdataaccess.com/
Contributors: wpdataaccess, peterschulznl, maxxschulz, kimmyx, freemius
Tags: table builder, data table, app builder, form builder, database app
Tested up to: 6.9
Requires PHP: 7.0
Stable tag: 5.5.63
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Turn your data into WordPress apps with tables, forms, charts & maps — no code required, with optional hooks for developers. Supports 35+ languages.

== Description ==
**WP Data Access** transforms your WordPress site into a platform for building **data-driven applications** — without writing a single line of code.

With the **intuitive App Builder**, you can create:

* **Interactive Data Tables** – manage and display data with ease
* **Custom Data Forms** – collect and process input seamlessly
* **Charts & Maps** – visualize data beautifully
* **Role-Based Access** – control who can view or manage apps
* **Multilingual Support** – 35+ languages ready to use

WP Data Access is designed as a **true no-code builder**: everything works out of the box, intuitive and effortless. For those who want more, a full set of **developer hooks** makes it possible to fine-tune behavior, extend functionality, or integrate with custom workflows. Hooks are completely optional and invisible to no-code users, but a powerful bonus for developers.

== App Builder ❤️ ==
> The ultimate **data-driven Rapid Application Development tool**. Build dynamic, interactive apps in minutes with **Builders**, **Managers**, and **Wizards** — all fully customizable with Hooks.

* **Table Builder** - Create powerful, interactive data tables effortlessly
  * Add static and dynamic data table filtering options 🔍
  * Real-time computed fields and aggregations for instant insights 📈
  * A Lookup Wizard to add lookups to your data table 🧙
  * Inline editing for instant updates 📝
  * Integrates with the WordPress media library for rich content 📷🎞
  * Export data tables to PDF, CSV, JSON, XML, SQL, and Excel 📄
  * Add charts and maps to your data table header and footer 📊
  * JavaScript hooks to customize data table layout and behavior 🔧
* **Form Builder** - Design forms that adapt to your workflow
  * Grid-based layouts for precise control ➕➖
  * Master-detail relationships for multi-level data structures 🔄
  * Lookup and Computed Field Wizards to add functionality instantly 🧙
  * Interactive client-side validations tied to your database constraints ✅
  * Full access to the WordPress media library 📷🎞
  * JavaScript hooks to customize business rules, validations and layout 🔧
* **Chart Builder** - Transform your data into visual insights
  * Google Charts integration 📊
  * Create charts from SQL queries 📑
  * Interactively adjustable chart configurations ✔
  * Print/export charts 🖨📄
* **Map Builder** - Visualize your data geographically
  * Google Maps integration for location-based apps 🌎
  * Query-driven location visualizations 📍
  * Interactively adjustable search radius 🔍
  * Customizable marker content and layout 📌
* **Theme Builder** - Make your app truly yours
  * Personalize your app’s appearance with ease 🎨
* **App Manager** - Control your apps with confidence
  * Authorization management based on WordPress user and role principles 🔒
  * Add apps to back-end menus or front-end pages via shortcodes 🔽🌐
  * Safe mode to temporarily disable hooks without breaking functionality 🔧
* Why App Builder?
  * Build dynamic, data-driven apps for both front-end and back-end 📱
  * Connect to local and remote databases ⚡
  * Real-time build and run capabilities 💻
  * Run apps in 35+ languages to reach a global audience 🌍

== SQL Query Builder ==
> **Run and schedule SQL queries** effortlessly from your WordPress dashboard.

* Schedule queries to run automatically at defined intervals 🕝
* Run **batch jobs** for **automated data exchange** across multiple databases 🤝
* AI Assistant to generate queries and fix common errors 🤖
* Tabbed interface for running multiple queries in parallel ▶
* Save and reuse queries - privately or globally 🔄
* Built-in safeguards to protect core WordPress tables and ensure data integrity 🔒
* Visual Query Builder to create complex queries without writing SQL 🎨

== Data Explorer ==
> Take full control of your data with a **GUI-driven interface**.

* Manage local and remote data 🗺
* Perform global search and replace across multiple databases and tables 🔍
* Import SQL and CSV files, with ZIP support for handling large datasets 📄
* Export data in various formats, including SQL, CSV, JSON, and XML 📄
* Rename, copy, truncate, drop, optimize, or alter tables 👤
* Advanced table and column options, such as geolocation, and enhanced search ✔

== Premium Data Services ==
> **Connect, sync, and manage remote databases and data files.**

* Compatible with all plugin features ✅
* Premium Remote Connection Wizard 🧙
  * Remote Databases: Connect to SQL Server, Oracle, PostgreSQL, MariaDB, MySQL, and MS Access (file-based) 💻
  * Remote Data Files: Sync with CSV, JSON, and XML files for dynamic updates (e.g., Google Sheets sync) 📄

== Legacy Tools ==
> Will be replaced by the **App Builder**.

* Available until at least december 2026 🕝
* Featuring
  * Data Tables 🔍 - Can be replaced with Data Table app.
  * Data Forms ✅ - Can be replaced with Data Management app or Registration Form.
  * Maps 🌎 - Can be replaced with Map app.
  * Charts 📊 - Can be replaced with Chart app.
  * Dashboards 🎛️
* Use to maintain old solutions 🙏
* Use App Builder for new projects 🚀

== Dashboards and Widgets ==
> Customizable widgets for dashboards (back-end), webpages (front-end), and external websites. (functionality will be moved to App Builder)

* Centralized data management
* Share data widgets anywhere
* Give specific users and user groups access to locked dashboards
* Support for user-created dashboards

== Useful Links ==
- [Plugin Website](https://wpdataaccess.com/)
- [Tool Guide Documentation](https://docs.wpdataaccess.com/)
- [App Builder Documentation](https://docs.rad.wpdataaccess.com/)
- [SQL Query Builder Documentation](https://docs.sql.wpdataaccess.com/)
- [Plugin Settings Documentation](https://docs.settings.wpdataaccess.com/)
- [Old Documentation](https://wpdataaccess.com/documentation/)

== Installation ==
(1) Upload the WP Data Access plugin to your WordPress site
(2) Activate the plugin
(3) Navigate to the WP Data Access menu

And you're all set! 🚀

== Changelog ==

= 5.5.63 =
* Released 2025-12-03
* Added: Enable | disable remote database connection from the Data Explorer
* Added: Option to disable spinner and progress bar then calling built-in function requery
* Added: Option to disable ENTER key submission
* Updated: Allow disabling global search for columns containing a column filter
* Updated: Documentation URLs
* Fixed: onChange hook for drop-down list not working
* Fixed: onChange hook for lookups not working
* Fixed: Open global search on startup forces a page scroll
* Fixed: Top pagination context text overlap on mobile devices
* Fixed: Search fields on mobiles devices not working
* Fixed: Checkbox beside row actions disappear when all actions are disabled
* Fixed: Handle current_timestamp as default value
* Fixed: Data Explorer crashes on full-screen toggle
* Fixed: PDF detail print out.
* Removed: Default value current_timestamp() from old forms

= 5.5.62 =
* Released 2025-11-12
* Added: Table display mode supporting switching between Table View and Card View
* Added: Hide expand icons option (when all detail panels are expanding by default)
* Added: Open global search box on page load
* Added: Parameter recalcRowCount to requery builtin to force row count recalculation
* Added: Disable navigation buttons during navigation
* Added: Disable APPLY, OK and CANCEL buttons while transaction is in progress
* Added: Server call authorization
* Added: Hard row count estimation for improved performance
* Added: Action wpda_set_hard_row_count to support automated hard row count updates
* Added: Select expand column position (LEFT | RIGHT)
* Improved: Handling media consistently on different devices
* Improved: Large table support in App Builder
* Updated: Documentation URLs
* Updated: Freemius SDK
* Fixed: In view form/mode show media instead of non-editable data entry fields
* Fixed: Background color actions column
* Fixed: Actions column width
* Fixed: Detail panel layout
* Fixed: Master-detail navigation does not update row count
* Fixed: Dynamic lookups not updated
* Fixed: Drop-down list text color
* Fixed: Height and color inline editing fields

= 5.5.61 =
* Released 2025-10-22
* Added: UTF encoding selection to CSV file import
* Fixed: CSV bulk delete not working

= 5.5.60 =
* Released 2025-10-16
* Added: Interactive detail render column selection
* Added: Built-in styled app button
* Added: Built-in dynamic server call
* Added: Edit code directly from hook overview
* Added: Open all detail panels by default
* Added: Computed Text Fields to free version
* Added: Pagination to free version
* Added: Style ID to loadStyle function.
* Added: Current date to PDF footer (removed powered by message)
* Fixed: Moved builtins to parallel container to prevent unnecessary rendering
* Fixed: No space between bulk actions row selection elements
* Fixed: Invalid encoding with CSV file import
* Fixed: Height empty footer App Manager action panel
* Fixed: Console errors not logged with debug mode disabled
* Fixed: Deleting a lookup value does not delete the state of a depending lookup
* Fixed: Manually added line breaks in hooks break hook import
* Fixed: Color settings defined in hooks overwritten on refresh
* Fixed: Cannot insert @wpda_wp_user_id into text field

= 5.5.57 =
* Released 2025-09-26
* Optimized: Responsive column hiding
* Added: Context sensitive help to Builders
* Added: Interactive documentation search
* Added: Parameters app and data to renderDetailPanel hook
* Fixed: Form column hooks not available in old forms
* Fixed: Full-screen mode on iOS
* Fixed: Full-screen mode on Android
* Fixed: Action column width too small
* Fixed: SQL Query Builder overwrites global mail settings

= 5.5.56 =
* Released 2025-09-18
* Added: Free trial activation from toolbar
* Fixed: App details not shown correctly in App Builder on iOS
* Fixed: App details not shown correctly in App Builder on Android
* Fixed: Removed double slashes from path
* Updated freemius SDK

= 5.5.55 =
* Released 2025-09-17
* Added: CCS classes now also available for free users
* Changed: Dynamic permission changes stored independently
* Fixed: Pagination BOTTOM and TOP not equally aligned
* Fixed: Built-in httpRequest not working in DEFAULT where
* Fixed: Chart source field does not preserve all SQL operators
* Fixed: Computed field displaying wrong extension
* Fixed: App Manager allows app type update for maps
* Fixed: Wrong labels for width and height in chart setup panel
* Fixed: Debug info missing in GET response

= 5.5.54 =
* Released 2025-09-03
* Fixed: UK date format not recognized
* Fixed: Skip PDS SSL verification

= 5.5.53 =
* Released 2025-08-28
* Added: loadScript and loadStyle to anAppOpen hook
* Changed: Parameter column name optional in form column hooks
* Client update

= 5.5.52 =
* Released 2025-08-23
* Changed: Updated computed field label
* Fixed: Multi-level relationship misses key
* Fixed: Detail key lost on row navigation

= 5.5.51 =
* Released 2025-08-22
* Added: Ability to disable insert into relation table
* Added: Alter column settings via app instance in hooks
* Fixed: Master-detail synchronization
* Fixed: Memory not freed after closing M2M row selection component
* Fixed: Close button not visible on modal form to anonymous users
* Fixed: Table not rendering correctly with column filters enabled on startup
* Fixed: Minimum columns shown in responsive mode
* Fixed: Action column too wide
* Fixed: Empty time fields breaks form validation
* Fixed: Unchecked columns in App Manager remain shown in app
* Fixed: Master detail relationship not working with divergent keys

= 5.5.49 =
* Released 2025-08-16
* Updated: Tooltips icon buttons toolbar
* Fixed: Hyperlinks in data tables not working
* Fixed: Multi level relationships missing builder buttons
* Fixed: Hook postFormSubmit does not fire after insert

= 5.5.48 =
* Released 2025-08-06
* Fixed: Export all rows with client-side processing exports only rows on current page
* Fixed: Show column filters on startup not working
* Fixed: Cursor exits inline editing field on user input
* Fixed: Detail table refreshed on every key press

= 5.5.47 =
* Released 2025-07-28
* Fixed: Column settings added via hooks lost on navigation
* Fixed: Column code variable returns undefined
* Fixed: Conditional lookup does not use arguments
* Fixed: Lookups loaded after (re)configuration
* Fixed: Removed HTML from PDF output

= 5.5.46 =
* Released 2025-07-26
* Fixed: Column filters not loading correctly

= < 5.5.46 =
* See changelog.txt