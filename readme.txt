=== LabelFill – EU Energy Label (EPREL) for WooCommerce ===
Contributors: facilo
Tags: energy label, eprel, etiqueta energetica, energielabel, woocommerce
Requires at least: 6.4
Tested up to: 7.2
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Fill in EU energy labels from EPREL by registration number: class arrow next to the price, official label and product sheet.

== Description ==

Selling lamps, TVs, appliances, tyres or phones in the EU? The law asks you to show the energy class arrow next to every price, with the official label and product sheet one click away. LabelFill does it for you: paste the EPREL registration number from the label, and it fills in the class, the label and the sheet in your shop's language, straight from the EU registry. Shoppers see the right label, and you stop copying data by hand.

= Show the EU energy label in WooCommerce =

* The coloured class arrow next to the price, at the price's size, with the class letter in white and the range of classes, on product pages and (optionally) in shop and category lists.
* A tap or click on the arrow opens the official label, on desktop and mobile.
* A "Product information sheet" link under the price, in your shop's language.
* Works with classic and block themes.
* Since June 2025, smartphones and tablets need an energy label too. LabelFill covers them, along with lighting, displays, appliances, tyres and every other EPREL group.

= EPREL lookup =

* One field per product and per variation. Paste the number or the link from the label's QR code.
* "Fill from EPREL" fetches the class, scale, supplier, model, label and sheets, and shows a preview of what your customer will see.
* Clear messages when a number isn't found, the supplier hasn't verified it, or EPREL is down.
* A manual mode for products that aren't in EPREL: pick the class and the arrow shows. The label and sheet are optional.
* A "Class" column and a filter in the products list: no number, with problems, or changed.
* Monthly refresh in the background, so stored data stays current, and a note when EPREL changes a product.
* A three-step setup wizard.

= LabelFill Pro =

For shops with many labelled products:

* Fill the whole catalogue at once, from a product field, the descriptions or a CSV.
* Weekly recheck with a change log and a weekly email when something changed.
* A coverage dashboard: which products still miss a label.
* Local copies of labels and sheets, so the shopper's browser never contacts EPREL.
* Four arrow styles, a side panel or inline label, a shortcode and a block.
* Sheets in the shopper's language with WPML, Polylang or TranslatePress.
* Quick edit, bulk edit and CSV import of the number.
* Energy class fields for Google Merchant Center feeds.

USD 39 a year for one shop, USD 99 a year for five.

= Third-party service: EPREL =

LabelFill reads public data from EPREL, the European Product Registry for Energy Labelling, run by the European Commission.

* What is sent: only the registration number, when you fill a product, when the monthly refresh runs, and, in the free version, from the shopper's browser when they open a label or sheet (the files are served by EPREL).
* Service: https://eprel.ec.europa.eu
* Terms of use and data reuse: https://eprel.ec.europa.eu/screen/requirements/tos
* Privacy: https://commission.europa.eu/privacy-policy-websites-managed-european-commission_en

Every label carries the line "Source: EPREL, European Commission". If the Commission stops or changes the service, LabelFill keeps the last data it fetched and the manual mode keeps working.

LabelFill is not affiliated with, endorsed or sponsored by the European Commission or EPREL.

= Important =

LabelFill shows what EPREL holds, the way the law describes. You are still responsible for checking that each model matches the product you sell. It is not legal advice.

= Your data =

LabelFill sends nothing to Facilo. Your shop stores the EPREL data with each product. In the free version the shopper's browser loads the label and sheet from EPREL when they open them; if you'd rather it didn't, LabelFill Pro serves local copies from your shop.

== Installation ==

1. Install and activate WooCommerce.
2. In Plugins > Add New, search for "LabelFill", install and activate it.
3. The setup wizard opens: pick your labelled categories, where the arrow goes, and try a number.
4. Open a product, paste its EPREL number in the "Energy label" box and click "Fill from EPREL".

== Frequently Asked Questions ==

= Where do I find the EPREL number? =

On the energy label, next to the QR code, or in the QR code's link (eprel.ec.europa.eu/qr/123456). Your supplier can also give it to you. You can paste the number or the whole link.

= Does it work with block themes? =

Yes. It works with block themes like Twenty Twenty-Five and classic themes like Storefront and Astra.

= What if my supplier isn't in EPREL? =

Switch on "Fill in by hand" in the product and pick the class: the arrow shows next to the price. The label and sheet from your supplier are optional, and open from the arrow and the sheet link when you add them.

= Does it make my shop compliant? =

It shows what EPREL holds, the way the law describes. You still check that each model matches.

= Which languages? =

The admin is in English and Spanish. The shopper side (the arrow, the label and "Product information sheet") is translated into Spanish, German, French, Italian, Dutch, Portuguese and Polish, with each language's legal wording. Translations arrive through translate.wordpress.org, so a language shows in your shop once it has been approved there.

= Energy label in other languages =

* Spanish: etiqueta energética
* German: Energielabel / Energieeffizienzklasse
* French: étiquette énergie
* Italian: etichetta energetica
* Dutch: energielabel
* Portuguese: etiqueta energética
* Polish: etykieta energetyczna

= What happens if I uninstall it? =

Your data stays unless you tick "Delete all LabelFill data when the plugin is deleted" in Settings.

== Screenshots ==

1. Product page with the class arrow next to the price.
2. The energy label pop-up on mobile.
3. The "Energy label" box in the product, with the shopper preview.
4. The products list with the Class column.
5. Setup wizard.
6. LabelFill Pro coverage page.

== Changelog ==

= 1.0.0 =
* First release.

== Upgrade Notice ==

= 1.0.0 =
First release.
