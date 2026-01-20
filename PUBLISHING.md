# Publishing Guide

To publish the Rasedi WooCommerce SDK, follow these steps to package the plugin into a distributable ZIP file.

## Prerequisites

- Terminal access (Mac/Linux)
- `zip` utility installed

## Packaging the Plugin

We have provided a script to automatically bundle the plugin files and exclude valid development files.

1.  Open your terminal in the project root.
2.  Run the packaging script:
    ```bash
    ./package.sh
    ```
3.  This will create a file named **`rasedi-woocommerce.zip`** in the current directory.

## Distribution

### Option 1: Manual Distribution (Direct Download)
- You can send `rasedi-woocommerce.zip` directly to merchants.
- Merchants install it via **WordPress Admin > Plugins > Add New > Upload Plugin**.

### Option 2: Self-Hosted
- Host the zip file on your website.
- Provide a link for users to download.

### Option 3: WordPress Plugin Directory
- If you intend to publish to the official WordPress.org repository, you must use SVN.
- The `rasedi-woocommerce` folder structure inside `build/` (created by the script) is compatible, but you will need to follow distinct [WordPress.org Plugin Submission](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/) procedures.
